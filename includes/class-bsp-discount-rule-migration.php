<?php
/**
 * Discount-rule data migrations.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Backfills canonical term IDs for rules created before those IDs were stored separately.
 */
class BSP_Discount_Rule_Migration {

	/**
	 * Completed migration version.
	 */
	const TERM_ID_META_VERSION = '1';

	/**
	 * Option used to record the completed migration.
	 */
	const TERM_ID_META_OPTION = 'bsp_term_id_meta_version';

	/**
	 * Registers migration hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'backfill_term_ids' ), 20 );
	}

	/**
	 * Copies legacy snapshot IDs into canonical metadata once.
	 *
	 * Snapshots are consulted only during this one-time migration. Product matching
	 * continues to use the canonical metadata exclusively.
	 *
	 * @return void
	 */
	public function backfill_term_ids() {
		if ( self::TERM_ID_META_VERSION === get_option( self::TERM_ID_META_OPTION ) ) {
			return;
		}

		$rules = get_posts(
			array(
				'post_type'      => BSP_Discount_Rule_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'trash' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $rules as $rule_id ) {
			$this->backfill_term_meta( $rule_id, '_bsp_category_terms', '_bsp_category_term_ids' );
			$this->backfill_term_meta( $rule_id, '_bsp_tag_terms', '_bsp_tag_term_ids' );
		}

		update_option( self::TERM_ID_META_OPTION, self::TERM_ID_META_VERSION, false );
	}

	/**
	 * Backfills one canonical metadata key when it does not yet exist.
	 *
	 * @param int    $rule_id Rule ID.
	 * @param string $snapshot_meta_key Legacy snapshot meta key.
	 * @param string $canonical_meta_key Canonical ID meta key.
	 * @return void
	 */
	private function backfill_term_meta( $rule_id, $snapshot_meta_key, $canonical_meta_key ) {
		if ( metadata_exists( 'post', $rule_id, $canonical_meta_key ) ) {
			return;
		}

		$snapshots = get_post_meta( $rule_id, $snapshot_meta_key, true );
		$term_ids  = array();

		if ( is_array( $snapshots ) ) {
			foreach ( $snapshots as $snapshot ) {
				if ( is_array( $snapshot ) && isset( $snapshot['term_id'] ) && ctype_digit( (string) $snapshot['term_id'] ) && (int) $snapshot['term_id'] > 0 ) {
					$term_ids[] = (int) $snapshot['term_id'];
				}
			}
		}

		update_post_meta( $rule_id, $canonical_meta_key, array_values( array_unique( $term_ids ) ) );
	}
}
