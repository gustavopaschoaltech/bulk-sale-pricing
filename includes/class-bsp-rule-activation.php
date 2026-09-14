<?php
/**
 * Discount-rule activation workflow.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates and applies a rule before marking it active.
 */
class BSP_Rule_Activation {

	/** @var BSP_Product_Targets */
	private $targets;

	/** @var BSP_Active_Discount_Rules */
	private $active_rules;

	/** @var BSP_Product_Matcher */
	private $matcher;

	/** @var BSP_Discount_Applicator */
	private $applicator;

	/**
	 * @param BSP_Product_Targets|null       $targets Target discovery.
	 * @param BSP_Active_Discount_Rules|null $active_rules Active-rule retrieval.
	 * @param BSP_Product_Matcher|null       $matcher Product matcher.
	 * @param BSP_Discount_Applicator|null   $applicator Price applicator.
	 */
	public function __construct( $targets = null, $active_rules = null, $matcher = null, $applicator = null ) {
		$this->matcher      = $matcher instanceof BSP_Product_Matcher ? $matcher : new BSP_Product_Matcher();
		$this->targets      = $targets instanceof BSP_Product_Targets ? $targets : new BSP_Product_Targets( $this->matcher );
		$this->active_rules = $active_rules instanceof BSP_Active_Discount_Rules ? $active_rules : new BSP_Active_Discount_Rules();
		$this->applicator   = $applicator instanceof BSP_Discount_Applicator ? $applicator : new BSP_Discount_Applicator( $this->matcher );
	}

	/**
	 * Inspects activation blockers without changing prices.
	 *
	 * @param WP_Post $rule Discount rule.
	 * @return array{status:string,targets:WC_Product[],conflicts:int[],existing_sale_prices:int[]}
	 */
	public function prepare( $rule ) {
		$targets   = $this->targets->find( $rule );
		$conflicts = $this->conflicting_rules( $targets, $rule );

		return array(
			'status'               => empty( $conflicts ) ? 'ready' : 'conflict',
			'targets'              => $targets,
			'conflicts'            => $conflicts,
			'existing_sale_prices' => empty( $conflicts ) ? $this->existing_sale_prices( $targets ) : array(),
		);
	}

	/**
	 * Activates a rule when there are no blockers or confirmation was supplied.
	 *
	 * @param WP_Post $rule Discount rule.
	 * @param bool    $confirmed Whether existing sale-price overwrite was confirmed.
	 * @return array{status:string,conflicts:int[],existing_sale_prices:int[],updated:int[],skipped:int[]}
	 */
	public function activate( $rule, $confirmed = false ) {
		$prepared = $this->prepare( $rule );
		if ( 'conflict' === $prepared['status'] ) {
			return array(
				'status'               => 'conflict',
				'conflicts'            => $prepared['conflicts'],
				'existing_sale_prices' => array(),
				'updated'              => array(),
				'skipped'              => array(),
			);
		}

		if ( ! $confirmed && ! empty( $prepared['existing_sale_prices'] ) ) {
			return array(
				'status'               => 'confirmation',
				'conflicts'            => array(),
				'existing_sale_prices' => $prepared['existing_sale_prices'],
				'updated'              => array(),
				'skipped'              => array(),
			);
		}

		$result = array(
			'status'               => 'activated',
			'conflicts'            => array(),
			'existing_sale_prices' => array(),
			'updated'              => array(),
			'skipped'              => array(),
		);

		foreach ( $prepared['targets'] as $product ) {
			$application = $this->applicator->apply( $product, $rule );
			foreach ( $application['updated'] as $product_id ) {
				update_post_meta( $product_id, '_bsp_rule_id', $rule->ID );
				$result['updated'][] = $product_id;
			}
			$result['skipped'] = array_merge( $result['skipped'], $application['skipped'] );
		}

		update_post_meta( $rule->ID, '_bsp_active', '1' );
		BSP_Active_Discount_Rules::reset();
		return $result;
	}

	/**
	 * Finds active rules that overlap a target product.
	 *
	 * @param WC_Product[] $targets Target products.
	 * @param WP_Post      $rule Rule being activated.
	 * @return int[]
	 */
	private function conflicting_rules( $targets, $rule ) {
		$conflicts = array();
		foreach ( $this->active_rules->get() as $active_rule ) {
			if ( $active_rule->ID === $rule->ID ) {
				continue;
			}

			foreach ( $targets as $product ) {
				if ( $this->matcher->matches( $product, $active_rule ) ) {
					$conflicts[] = $active_rule->ID;
					break;
				}
			}
		}

		return array_values( array_unique( $conflicts ) );
	}

	/**
	 * Gets priced objects that already have a native sale price.
	 *
	 * @param WC_Product[] $targets Target products.
	 * @return int[]
	 */
	private function existing_sale_prices( $targets ) {
		$product_ids = array();
		foreach ( $targets as $product ) {
			foreach ( $this->applicator->priced_objects( $product ) as $priced_product ) {
				if ( '' !== $priced_product->get_sale_price() ) {
					$product_ids[] = $priced_product->get_id();
				}
			}
		}

		return array_values( array_unique( $product_ids ) );
	}
}
