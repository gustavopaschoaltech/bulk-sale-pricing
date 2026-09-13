<?php
/**
 * Removes data owned exclusively by Bulk Sale Pricing.
 *
 * @package BulkSalePricing
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

do {
	$bsp_rules = new WP_Query(
		array(
			'post_type'      => 'bsp_discount_rule',
			'post_status'    => array( 'publish', 'trash' ),
			'posts_per_page' => 100,
			'paged'          => 1,
			'fields'         => 'ids',
		)
	);

	foreach ( $bsp_rules->posts as $bsp_rule_id ) {
		if ( false === wp_delete_post( $bsp_rule_id, true ) ) {
			break 2;
		}
	}

} while ( ! empty( $bsp_rules->posts ) );
