<?php
/**
 * Product taxonomy selection and display helpers.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Persists immutable taxonomy snapshots and resolves their display names.
 */
class BSP_Discount_Rule_Terms {

	/**
	 * Creates validated snapshots for submitted term IDs.
	 *
	 * @param mixed  $submitted Submitted IDs.
	 * @param string $taxonomy Taxonomy name.
	 * @return array|WP_Error
	 */
	public function snapshots( $submitted, $taxonomy ) {
		if ( null === $submitted || '' === $submitted ) {
			return array();
		}

		if ( ! is_array( $submitted ) || ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'bsp_invalid_terms', __( 'One or more selected terms are invalid.', 'bulk-sale-pricing' ) );
		}

		$snapshots = array();
		foreach ( $submitted as $term_id ) {
			if ( ! is_scalar( $term_id ) || ! ctype_digit( (string) $term_id ) || 0 === (int) $term_id ) {
				return new WP_Error( 'bsp_invalid_terms', __( 'One or more selected terms are invalid.', 'bulk-sale-pricing' ) );
			}

			$term = get_term( (int) $term_id, $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) {
				return new WP_Error( 'bsp_invalid_terms', __( 'One or more selected terms are invalid.', 'bulk-sale-pricing' ) );
			}

			$snapshots[ $term->term_id ] = array(
				'term_id' => (int) $term->term_id,
				'name'    => $term->name,
			);
		}

		return array_values( $snapshots );
	}

	/**
	 * Resolves stored snapshots to current or deleted display names.
	 *
	 * @param mixed  $snapshots Stored snapshots.
	 * @param string $taxonomy Taxonomy name.
	 * @return array<int, array{name:string,deleted:bool}>
	 */
	public function display_terms( $snapshots, $taxonomy ) {
		if ( ! is_array( $snapshots ) ) {
			return array();
		}

		$terms = array();
		foreach ( $snapshots as $snapshot ) {
			if ( ! is_array( $snapshot ) || empty( $snapshot['term_id'] ) || ! isset( $snapshot['name'] ) ) {
				continue;
			}

			$term = get_term( (int) $snapshot['term_id'], $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$terms[] = array(
					'name'    => $term->name,
					'deleted' => false,
				);
				continue;
			}

			$terms[] = array(
				'name'    => (string) $snapshot['name'],
				'deleted' => true,
			);
		}

		return $terms;
	}
}
