<?php
/**
 * Discount-rule product target discovery.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finds matching simple and variable parent products for a rule.
 */
class BSP_Product_Targets {

	/**
	 * Product matcher.
	 *
	 * @var BSP_Product_Matcher
	 */
	private $matcher;

	/**
	 * @param BSP_Product_Matcher|null $matcher Product matcher.
	 */
	public function __construct( $matcher = null ) {
		$this->matcher = $matcher instanceof BSP_Product_Matcher ? $matcher : new BSP_Product_Matcher();
	}

	/**
	 * Finds published product parents matched by a rule.
	 *
	 * @param WP_Post $rule Discount rule.
	 * @return WC_Product[]
	 */
	public function find( $rule ) {
		$candidates = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
				'parent' => 0,
				'return' => 'objects',
			)
		);
		$products = array();

		foreach ( $candidates as $product ) {
			if ( $product instanceof WC_Product && $this->matcher->matches( $product, $rule ) ) {
				$products[] = $product;
			}
		}

		return $products;
	}
}
