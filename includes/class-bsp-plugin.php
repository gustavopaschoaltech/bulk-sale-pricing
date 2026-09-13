<?php
/**
 * Plugin bootstrap.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates plugin components.
 */
final class BSP_Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var BSP_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Gets the plugin instance.
	 *
	 * @return BSP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers plugin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		( new BSP_Discount_Rule_Post_Type() )->register_hooks();

		if ( is_admin() ) {
			( new BSP_Discount_Rule_Admin() )->register_hooks();
		}
	}
}
