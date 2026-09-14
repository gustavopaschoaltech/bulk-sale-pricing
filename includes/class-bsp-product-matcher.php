<?php
/**
 * Discount-rule product matching.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Matches products against a discount rule's exact taxonomy term IDs.
 */
class BSP_Product_Matcher {

	/**
	 * Determines whether a product matches a rule.
	 *
	 * Variations are evaluated against their variable parent product.
	 *
	 * @param WC_Product $product Product or variation to evaluate.
	 * @param WP_Post    $rule Discount rule.
	 * @return bool
	 */
	public function matches( $product, $rule ) {
		$product = $this->target_product( $product );
		if ( ! $product instanceof WC_Product || ! $rule instanceof WP_Post || BSP_Discount_Rule_Post_Type::POST_TYPE !== $rule->post_type ) {
			return false;
		}

		$category_ids = $this->rule_term_ids( $rule->ID, '_bsp_category_term_ids' );
		$tag_ids      = $this->rule_term_ids( $rule->ID, '_bsp_tag_term_ids' );

		if ( empty( $category_ids ) && empty( $tag_ids ) ) {
			return false;
		}

		if ( ! empty( $category_ids ) && ! $this->has_any_term( $product->get_category_ids(), $category_ids ) ) {
			return false;
		}

		if ( ! empty( $tag_ids ) && ! $this->has_any_term( $product->get_tag_ids(), $tag_ids ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Resolves a variation to its parent product.
	 *
	 * @param WC_Product $product Product to resolve.
	 * @return WC_Product|null
	 */
	private function target_product( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		if ( ! $product->is_type( 'variation' ) ) {
			return $product;
		}

		$parent_id = $product->get_parent_id();
		return $parent_id > 0 ? wc_get_product( $parent_id ) : null;
	}

	/**
	 * Gets valid canonical term IDs stored for a rule.
	 *
	 * @param int    $rule_id Rule ID.
	 * @param string $meta_key Meta key.
	 * @return int[]
	 */
	private function rule_term_ids( $rule_id, $meta_key ) {
		$stored_ids = get_post_meta( $rule_id, $meta_key, true );
		if ( ! is_array( $stored_ids ) ) {
			return array();
		}

		$term_ids = array();
		foreach ( $stored_ids as $term_id ) {
			if ( is_int( $term_id ) && $term_id > 0 ) {
				$term_ids[] = $term_id;
			}
		}

		return array_values( array_unique( $term_ids ) );
	}

	/**
	 * Determines whether two exact term-ID lists overlap.
	 *
	 * @param int[] $product_term_ids Product term IDs.
	 * @param int[] $rule_term_ids Rule term IDs.
	 * @return bool
	 */
	private function has_any_term( $product_term_ids, $rule_term_ids ) {
		return ! empty( array_intersect( $product_term_ids, $rule_term_ids ) );
	}
}
