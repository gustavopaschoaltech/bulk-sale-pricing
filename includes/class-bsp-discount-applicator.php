<?php
/**
 * Native WooCommerce sale-price application.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies an eligible rule to matching simple or variable products.
 */
class BSP_Discount_Applicator {

	/**
	 * Product matcher.
	 *
	 * @var BSP_Product_Matcher
	 */
	private $matcher;

	/** @var BSP_Product_History */
	private $history;

	/**
	 * Initializes the product matcher.
	 *
	 * @param BSP_Product_Matcher|null $matcher Product matcher.
	 * @param BSP_Product_History|null $history Product history.
	 */
	public function __construct( $matcher = null, $history = null ) {
		$this->matcher = $matcher instanceof BSP_Product_Matcher ? $matcher : new BSP_Product_Matcher();
		$this->history = $history instanceof BSP_Product_History ? $history : new BSP_Product_History();
	}

	/**
	 * Applies a rule to a matching simple product or each matching variable product variation.
	 *
	 * @param WC_Product $product Product to process.
	 * @param WP_Post    $rule Discount rule.
	 * @param string     $source Execution context.
	 * @return array{matched:bool,updated:int[],skipped:int[]}
	 */
	public function apply( $product, $rule, $source = 'activation' ) {
		$result = array(
			'matched' => false,
			'updated' => array(),
			'skipped' => array(),
		);

		if ( ! $product instanceof WC_Product || ! $this->matcher->matches( $product, $rule ) ) {
			return $result;
		}

		$result['matched'] = true;
		$percentage = $this->discount_percentage( $rule );
		if ( null === $percentage ) {
			return $result;
		}

		$products          = $product->is_type( 'variable' ) ? $this->variations( $product ) : array( $product );

		foreach ( $products as $priced_product ) {
			if ( $this->apply_to_product( $priced_product, $rule, $percentage, $source ) ) {
				$result['updated'][] = $priced_product->get_id();
			} else {
				$result['skipped'][] = $priced_product->get_id();
			}
		}

		if ( $product->is_type( 'variable' ) && ! empty( $result['updated'] ) ) {
			WC_Product_Variable::sync( $product->get_id() );
			wc_delete_product_transients( $product->get_id() );
		}

		return $result;
	}

	/**
	 * Gets the simple product or valid variations that would receive a sale price.
	 *
	 * @param WC_Product $product Product to inspect.
	 * @return WC_Product[]
	 */
	public function priced_objects( $product ) {
		$products = $product instanceof WC_Product && $product->is_type( 'variable' ) ? $this->variations( $product ) : array( $product );
		$priced   = array();

		foreach ( $products as $priced_product ) {
			if ( $priced_product instanceof WC_Product && $this->regular_price_is_usable( $priced_product->get_regular_price() ) ) {
				$priced[] = $priced_product;
			}
		}

		return $priced;
	}

	/**
	 * Gets valid variations for a variable product.
	 *
	 * @param WC_Product $product Variable product.
	 * @return WC_Product[]
	 */
	private function variations( $product ) {
		$variations = array();
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation instanceof WC_Product ) {
				$variations[] = $variation;
			}
		}

		return $variations;
	}

	/**
	 * Applies a calculated sale price to one product through WooCommerce CRUD.
	 *
	 * @param WC_Product $product Product or variation.
	 * @param WP_Post    $rule Discount rule.
	 * @param int        $percentage Discount percentage.
	 * @param string     $source Execution context.
	 * @return bool Whether the product was updated.
	 */
	private function apply_to_product( $product, $rule, $percentage, $source ) {
		$regular_price = $product->get_regular_price();
		$sale_price    = $this->sale_price( $regular_price, $percentage );
		if ( null === $sale_price ) {
			$this->history->record(
				$product,
				$this->event( $rule, 'skipped', 'sale_price_skipped', $source, __( 'Bulk Sale Pricing skipped this item because its Regular Price is empty, zero, or invalid.', 'bulk-sale-pricing' ), array( 'regular_price' => $regular_price, 'sale_price_before' => $product->get_sale_price() ) )
			);
			return false;
		}

		$previous_sale_price = $product->get_sale_price();
		$state_changed       = $previous_sale_price !== $sale_price || $product->get_date_on_sale_from() || $product->get_date_on_sale_to();
		if ( ! $state_changed ) {
			return true;
		}

		try {
			$product->set_sale_price( $sale_price );
			$product->set_date_on_sale_from( null );
			$product->set_date_on_sale_to( null );
			$product->save();
		} catch ( Throwable $throwable ) {
			$this->history->record(
				$product,
				$this->event( $rule, 'error', 'sale_price_application_failed', $source, __( 'Bulk Sale Pricing could not apply the Sale Price.', 'bulk-sale-pricing' ), array( 'regular_price' => $regular_price, 'sale_price_before' => $previous_sale_price, 'sale_price_after' => $sale_price ) )
			);
			throw $throwable;
		}

		if ( $state_changed ) {
			$owned_by_rule = (string) $rule->ID === get_post_meta( $product->get_id(), '_bsp_rule_id', true );
			$action        = '' === $previous_sale_price ? 'sale_price_applied' : ( $owned_by_rule ? 'sale_price_repriced' : 'sale_price_overwritten' );
			$message       = 'sale_price_overwritten' === $action ? __( 'Bulk Sale Pricing overwrote the existing Sale Price.', 'bulk-sale-pricing' ) : __( 'Bulk Sale Pricing applied a Sale Price.', 'bulk-sale-pricing' );
			if ( 'sale_price_repriced' === $action ) {
				$message = __( 'Bulk Sale Pricing recalculated the Sale Price.', 'bulk-sale-pricing' );
			}
			$this->history->record(
				$product,
				$this->event( $rule, 'success', $action, $source, $message, array( 'regular_price' => $regular_price, 'sale_price_before' => $previous_sale_price, 'sale_price_after' => $sale_price ) )
			);
		}

		return true;
	}

	/**
	 * Builds common structured history data for a rule operation.
	 *
	 * @param WP_Post $rule Discount rule.
	 * @param string  $status Event status.
	 * @param string  $action Event action.
	 * @param string  $source Execution source.
	 * @param string  $message Human-readable message.
	 * @param array   $prices Optional price data.
	 * @return array
	 */
	private function event( $rule, $status, $action, $source, $message, $prices = array() ) {
		return array_merge(
			array(
				'status'    => $status,
				'action'    => $action,
				'source'    => $source,
				'rule_id'   => $rule->ID,
				'rule_name' => $rule->post_title,
				'message'   => $message,
			),
			$prices
		);
	}

	/**
	 * Calculates a sale price using WooCommerce-normalized decimal values.
	 *
	 * The calculation uses integer minor units to avoid binary floating-point
	 * arithmetic, then returns a WooCommerce-formatted decimal string.
	 *
	 * @param string $regular_price Regular price.
	 * @param int    $percentage Discount percentage.
	 * @return string|null Sale price, or null for an unusable regular price.
	 */
	private function sale_price( $regular_price, $percentage ) {
		if ( ! $this->regular_price_is_usable( $regular_price ) ) {
			return null;
		}

		$decimals        = wc_get_price_decimals();
		$normalized_price = wc_format_decimal( $regular_price, $decimals );
		$parts           = explode( '.', $normalized_price, 2 );
		$whole           = isset( $parts[0] ) ? ltrim( $parts[0], '0' ) : '';
		$fraction        = isset( $parts[1] ) ? $parts[1] : '';
		$whole           = '' === $whole ? '0' : $whole;
		$fraction        = str_pad( substr( $fraction, 0, $decimals ), $decimals, '0' );
		$minor_units     = ltrim( $whole . $fraction, '0' );

		if ( '' === $minor_units || ! ctype_digit( $minor_units ) ) {
			return null;
		}

		$discounted_minor_units = intdiv( ( (int) $minor_units * ( 100 - $percentage ) ) + 50, 100 );
		$price_digits           = str_pad( (string) $discounted_minor_units, $decimals + 1, '0', STR_PAD_LEFT );

		if ( 0 === $decimals ) {
			return wc_format_decimal( $price_digits, 0 );
		}

		$sale_price = substr( $price_digits, 0, -$decimals ) . '.' . substr( $price_digits, -$decimals );
		return wc_format_decimal( $sale_price, $decimals );
	}

	/**
	 * Determines whether a regular price can receive a sale price.
	 *
	 * @param mixed $regular_price Regular price.
	 * @return bool
	 */
	private function regular_price_is_usable( $regular_price ) {
		if ( '' === $regular_price || ! is_numeric( $regular_price ) ) {
			return false;
		}

		$normalized_price = wc_format_decimal( $regular_price, wc_get_price_decimals() );
		return '' !== ltrim( str_replace( '.', '', $normalized_price ), '0' );
	}

	/**
	 * Gets a valid percentage from a rule.
	 *
	 * @param WP_Post $rule Discount rule.
	 * @return int|null
	 */
	private function discount_percentage( $rule ) {
		if ( ! $rule instanceof WP_Post ) {
			return null;
		}

		$percentage = get_post_meta( $rule->ID, '_bsp_discount_percentage', true );
		if ( ! is_scalar( $percentage ) ) {
			return null;
		}

		$percentage = trim( (string) $percentage );
		return preg_match( '/^(?:[1-9]|[1-9][0-9])$/', $percentage ) ? (int) $percentage : null;
	}
}
