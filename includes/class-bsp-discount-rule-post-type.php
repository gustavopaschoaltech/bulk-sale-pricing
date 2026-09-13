<?php
/**
 * Internal discount-rule post type.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the post type used to persist discount rules.
 */
class BSP_Discount_Rule_Post_Type {

	/**
	 * Post type name.
	 */
	const POST_TYPE = 'bsp_discount_rule';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Registers the internal post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$capabilities = array_fill_keys(
			array(
				'edit_post',
				'read_post',
				'delete_post',
				'edit_posts',
				'edit_others_posts',
				'publish_posts',
				'read_private_posts',
				'delete_posts',
				'delete_private_posts',
				'delete_published_posts',
				'delete_others_posts',
				'edit_private_posts',
				'edit_published_posts',
				'create_posts',
			),
			'manage_woocommerce'
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name' => __( 'Discount Rules', 'bulk-sale-pricing' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'query_var'           => false,
				'rewrite'             => false,
				'has_archive'         => false,
				'supports'            => array( 'title' ),
				'capabilities'        => $capabilities,
				'map_meta_cap'        => false,
			)
		);
	}
}
