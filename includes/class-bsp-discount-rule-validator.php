<?php
/**
 * Validation for discount-rule input.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates fields that do not require taxonomy lookups.
 */
class BSP_Discount_Rule_Validator {

	/**
	 * Validates a rule name.
	 *
	 * @param mixed $value Submitted value.
	 * @return string|WP_Error
	 */
	public function rule_name( $value ) {
		if ( ! is_scalar( $value ) ) {
			return new WP_Error( 'bsp_invalid_name', __( 'Enter a rule name.', 'bulk-sale-pricing' ) );
		}

		$name = trim( sanitize_text_field( (string) $value ) );

		if ( '' === $name ) {
			return new WP_Error( 'bsp_invalid_name', __( 'Enter a rule name.', 'bulk-sale-pricing' ) );
		}

		return $name;
	}

	/**
	 * Validates a whole-number percentage.
	 *
	 * @param mixed $value Submitted value.
	 * @return int|WP_Error
	 */
	public function percentage( $value ) {
		if ( ! is_scalar( $value ) ) {
			return new WP_Error( 'bsp_invalid_percentage', __( 'Enter a whole-number discount from 1 to 99.', 'bulk-sale-pricing' ) );
		}

		$value = trim( (string) $value );

		if ( ! preg_match( '/^(?:[1-9]|[1-9][0-9])$/', $value ) ) {
			return new WP_Error( 'bsp_invalid_percentage', __( 'Enter a whole-number discount from 1 to 99.', 'bulk-sale-pricing' ) );
		}

		return (int) $value;
	}
}
