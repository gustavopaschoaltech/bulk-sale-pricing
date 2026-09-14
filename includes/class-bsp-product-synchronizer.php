<?php
/**
 * Live active-rule product synchronization.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps one saved product or variation aligned with its active BSP rule.
 */
class BSP_Product_Synchronizer {

	/** @var int[] */
	private static $processing = array();

	/** @var bool[] */
	private static $recorded_conflicts = array();

	/** @var BSP_Active_Discount_Rules */
	private $active_rules;

	/** @var BSP_Product_Matcher */
	private $matcher;

	/** @var BSP_Discount_Applicator */
	private $applicator;

	/** @var BSP_Rule_Deactivation */
	private $cleanup;

	/** @var BSP_Product_Targets */
	private $targets;

	/** @var BSP_Product_History */
	private $history;

	/** @var int[] */
	private $conflicts = array();

	/**
	 * @param BSP_Active_Discount_Rules|null $active_rules Active-rule retrieval.
	 * @param BSP_Product_Matcher|null       $matcher Product matcher.
	 * @param BSP_Discount_Applicator|null   $applicator Price applicator.
	 * @param BSP_Rule_Deactivation|null     $cleanup Ownership cleanup.
	 * @param BSP_Product_Targets|null       $targets Target discovery.
	 * @param BSP_Product_History|null       $history Product history.
	 */
	public function __construct( $active_rules = null, $matcher = null, $applicator = null, $cleanup = null, $targets = null, $history = null ) {
		$this->active_rules = $active_rules instanceof BSP_Active_Discount_Rules ? $active_rules : new BSP_Active_Discount_Rules();
		$this->matcher      = $matcher instanceof BSP_Product_Matcher ? $matcher : new BSP_Product_Matcher();
		$this->targets      = $targets instanceof BSP_Product_Targets ? $targets : new BSP_Product_Targets( $this->matcher );
		$this->history      = $history instanceof BSP_Product_History ? $history : new BSP_Product_History();
		$this->applicator   = $applicator instanceof BSP_Discount_Applicator ? $applicator : new BSP_Discount_Applicator( $this->matcher, $this->history );
		$this->cleanup      = $cleanup instanceof BSP_Rule_Deactivation ? $cleanup : new BSP_Rule_Deactivation( $this->history );
	}

	/**
	 * Registers public WooCommerce lifecycle hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'woocommerce_new_product', array( $this, 'synchronize_saved_product' ), 20, 2 );
		add_action( 'woocommerce_update_product', array( $this, 'synchronize_saved_product' ), 20, 2 );
		add_action( 'woocommerce_new_product_variation', array( $this, 'synchronize_saved_variation' ), 20 );
		add_action( 'woocommerce_update_product_variation', array( $this, 'synchronize_saved_variation' ), 20 );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'render_conflict_notice' ) );
		}
	}

	/**
	 * Synchronizes one product after WooCommerce persisted it.
	 *
	 * @param int        $product_id Product or variation ID.
	 * @param WC_Product $product Persisted product object.
	 * @return void
	 */
	public function synchronize_saved_product( $product_id, $product ) {
		if ( $product instanceof WC_Product && $product->get_id() === (int) $product_id ) {
			$this->synchronize_product( $product, 'product_sync' );
		}
	}

	/**
	 * Synchronizes one variation after WooCommerce persisted it.
	 *
	 * @param int $variation_id Variation ID.
	 * @return void
	 */
	public function synchronize_saved_variation( $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if ( $variation instanceof WC_Product && $variation->is_type( 'variation' ) ) {
			$this->synchronize_product( $variation, 'variation_sync' );
		}
	}

	/**
	 * Synchronizes all current targets of an active rule after its percentage changes.
	 *
	 * @param WP_Post $rule Active discount rule.
	 * @return void
	 */
	public function synchronize_rule( $rule ) {
		if ( ! $rule instanceof WP_Post || 'publish' !== $rule->post_status || '1' !== get_post_meta( $rule->ID, '_bsp_active', true ) ) {
			return;
		}

		foreach ( $this->targets->find( $rule ) as $product ) {
			$this->synchronize_product( $product, 'rule_percentage_update' );
		}
	}

	/**
	 * Synchronizes a simple product, variable parent, or individual variation.
	 *
	 * @param WC_Product $product Product to synchronize.
	 * @param string     $source Execution context.
	 * @return void
	 */
	private function synchronize_product( $product, $source ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( BSP_Rule_Deactivation::is_cleaning_product( $product->get_id() ) ) {
			return;
		}

		$guard_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		if ( $guard_id <= 0 || isset( self::$processing[ $guard_id ] ) ) {
			return;
		}

		self::$processing[ $guard_id ] = $guard_id;
		try {
			$matching_rules = $this->matching_rules( $product );
			if ( count( $matching_rules ) > 1 ) {
				$this->cleanup_conflicting_ownership( $product, $matching_rules, $source );
				return;
			}

			$this->cleanup_invalid_ownership( $product, empty( $matching_rules ) ? null : $matching_rules[0], $source );
			if ( empty( $matching_rules ) ) {
				return;
			}

			$rule        = $matching_rules[0];
			$application = $this->applicator->apply( $product, $rule, $source );
			foreach ( $application['updated'] as $product_id ) {
				update_post_meta( $product_id, '_bsp_rule_id', $rule->ID );
			}

			foreach ( $application['skipped'] as $product_id ) {
				$this->cleanup->cleanup_products( array( $product_id ), $rule->ID, $source, 'invalid_regular_price_cleanup', __( 'Bulk Sale Pricing removed its Sale Price because the Regular Price is empty, zero, or invalid.', 'bulk-sale-pricing' ) );
			}
		} finally {
			unset( self::$processing[ $guard_id ] );
		}
	}

	/**
	 * Clears BSP ownership when an item has no unambiguous active rule.
	 *
	 * @param WC_Product $product Product or variation being synchronized.
	 * @param WP_Post[]  $matching_rules Active rules matching the product.
	 * @param string     $source Execution context.
	 * @return void
	 */
	private function cleanup_conflicting_ownership( $product, $matching_rules, $source ) {
		foreach ( $this->owned_objects( $product ) as $owned_product ) {
			$owner_id = absint( get_post_meta( $owned_product->get_id(), '_bsp_rule_id', true ) );
			if ( $owner_id > 0 ) {
				$this->cleanup->cleanup_products( array( $owned_product->get_id() ), $owner_id, $source, 'live_conflict_cleanup', __( 'Bulk Sale Pricing removed its Sale Price because multiple active rules match this item.', 'bulk-sale-pricing' ) );
			}
		}

		$target_id   = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$conflict_key = $this->conflict_key( $target_id, $matching_rules );
		if ( isset( self::$recorded_conflicts[ $conflict_key ] ) ) {
			return;
		}

		self::$recorded_conflicts[ $conflict_key ] = true;
		$this->conflicts[]                         = $target_id;
		$this->history->record(
			$product,
			array(
				'status'  => 'conflict',
				'action'  => 'multiple_active_rules',
				'source'  => 'conflict',
				'message' => sprintf( __( 'Bulk Sale Pricing found multiple active rules matching this item: %s. BSP pricing was removed and will remain uncontrolled until the conflict is resolved.', 'bulk-sale-pricing' ), $this->rule_labels( $matching_rules ) ),
			)
		);
	}

	/**
	 * Builds a request-local key for one logical product conflict.
	 *
	 * @param int       $target_id Parent product or simple product ID.
	 * @param WP_Post[] $matching_rules Active rules matching the product.
	 * @return string
	 */
	private function conflict_key( $target_id, $matching_rules ) {
		$rule_ids = array_map( 'absint', wp_list_pluck( $matching_rules, 'ID' ) );
		sort( $rule_ids, SORT_NUMERIC );
		return $target_id . ':' . implode( ',', $rule_ids );
	}

	/**
	 * Formats matching active rules for the conflict history message.
	 *
	 * @param WP_Post[] $matching_rules Active rules matching the product.
	 * @return string
	 */
	private function rule_labels( $matching_rules ) {
		$labels = array();
		foreach ( $matching_rules as $rule ) {
			$labels[] = sprintf( '%1$s (#%2$d)', $rule->post_title, $rule->ID );
		}

		return implode( ', ', $labels );
	}

	/**
	 * Gets active rules currently matching a product or its variable parent.
	 *
	 * @param WC_Product $product Product to evaluate.
	 * @return WP_Post[]
	 */
	private function matching_rules( $product ) {
		$matches = array();
		foreach ( $this->active_rules->get() as $rule ) {
			if ( $this->matcher->matches( $product, $rule ) ) {
				$matches[] = $rule;
			}
		}

		return $matches;
	}

	/**
	 * Removes ownership that no longer corresponds to the one current active rule.
	 *
	 * @param WC_Product  $product Product or variation being synchronized.
	 * @param WP_Post|null $matching_rule The sole current matching rule, if any.
	 * @param string       $source Execution context.
	 * @return void
	 */
	private function cleanup_invalid_ownership( $product, $matching_rule, $source ) {
		foreach ( $this->owned_objects( $product ) as $owned_product ) {
			$owner_id = absint( get_post_meta( $owned_product->get_id(), '_bsp_rule_id', true ) );
			if ( 0 === $owner_id ) {
				continue;
			}

			$owner_rule = $this->active_rule( $owner_id );
			if ( ! $owner_rule || ! $this->matcher->matches( $owned_product, $owner_rule ) || ! $matching_rule || $owner_rule->ID !== $matching_rule->ID ) {
				$this->cleanup->cleanup_products( array( $owned_product->get_id() ), $owner_id, $source, 'rule_departure_cleanup', __( 'Bulk Sale Pricing removed its Sale Price because this item no longer matches its owning rule.', 'bulk-sale-pricing' ) );
			}
		}
	}

	/**
	 * Gets the objects that can store ownership for a save event.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return WC_Product[]
	 */
	private function owned_objects( $product ) {
		if ( $product->is_type( 'variation' ) || ! $product->is_type( 'variable' ) ) {
			return array( $product );
		}

		$objects = array();
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation instanceof WC_Product ) {
				$objects[] = $variation;
			}
		}

		return $objects;
	}

	/**
	 * Gets an active rule by ID from the request-local collection.
	 *
	 * @param int $rule_id Rule ID.
	 * @return WP_Post|null
	 */
	private function active_rule( $rule_id ) {
		foreach ( $this->active_rules->get() as $rule ) {
			if ( $rule->ID === $rule_id ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Shows a current-request notice for a live matching conflict in wp-admin.
	 *
	 * @return void
	 */
	public function render_conflict_notice() {
		if ( empty( $this->conflicts ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Bulk Sale Pricing found multiple active rules matching a saved product. BSP pricing was removed until the conflict is resolved.', 'bulk-sale-pricing' ) );
	}
}
