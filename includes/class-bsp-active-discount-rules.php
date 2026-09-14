<?php
/**
 * Active discount-rule retrieval.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retrieves active discount rules once per request.
 */
class BSP_Active_Discount_Rules {

	/**
	 * Cached active rules.
	 *
	 * @var WP_Post[]|null
	 */
	private static $rules = null;

	/**
	 * Gets published rules marked active.
	 *
	 * @return WP_Post[]
	 */
	public function get() {
		if ( null !== self::$rules ) {
			return self::$rules;
		}

		self::$rules = get_posts(
			array(
				'post_type'      => BSP_Discount_Rule_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'   => '_bsp_active',
						'value' => '1',
					),
				),
			)
		);

		return self::$rules;
	}

	/**
	 * Clears the request-local active-rule collection.
	 *
	 * Call this after changing a rule's activation state so later product
	 * lifecycle hooks in the same request retrieve the current collection.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$rules = null;
	}
}
