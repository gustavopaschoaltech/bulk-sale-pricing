<?php
/**
 * Product activity history persistence and display.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores a compact diagnostic history against WooCommerce product parents.
 */
class BSP_Product_History {

	/** History post-meta key. */
	const META_KEY = '_bsp_product_history';

	/** Maximum number of events retained for one product. */
	const MAX_EVENTS = 100;

	/** @var bool[] */
	private static $recorded_event_keys = array();

	/**
	 * Registers the read-only product editor meta box.
	 *
	 * @return void
	 */
	public function register_admin_hooks() {
		add_action( 'add_meta_boxes_product', array( $this, 'register_meta_box' ) );
	}

	/**
	 * Adds the history meta box to product editors.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box(
			'bsp-product-history',
			__( 'Bulk Sale Pricing History', 'bulk-sale-pricing' ),
			array( $this, 'render_meta_box' ),
			'product',
			'normal',
			'low'
		);
	}

	/**
	 * Appends one diagnostic event without saving a WooCommerce product.
	 *
	 * Variations are stored on their variable parent so their activity has one
	 * visible home in the product editor.
	 *
	 * @param WC_Product|int $product Product, variation, or product ID.
	 * @param array          $event Event data.
	 * @return void
	 */
	public function record( $product, $event ) {
		$product_id = $this->storage_product_id( $product );
		if ( $product_id <= 0 || ! is_array( $event ) ) {
			return;
		}

		$event_key = $this->event_key( $product, $event );
		if ( isset( self::$recorded_event_keys[ $event_key ] ) ) {
			return;
		}

		self::$recorded_event_keys[ $event_key ] = true;

		$history   = get_post_meta( $product_id, self::META_KEY, true );
		$history   = is_array( $history ) ? $history : array();
		$history[] = $this->normalize_event( $product, $event );
		$history   = array_slice( $history, -self::MAX_EVENTS );

		update_post_meta( $product_id, self::META_KEY, $history );
	}

	/**
	 * Builds a request-local key for one affected-object diagnostic event.
	 *
	 * @param WC_Product|int $product Product, variation, or product ID.
	 * @param array          $event Event data.
	 * @return string
	 */
	private function event_key( $product, $event ) {
		$object_id = $product instanceof WC_Product ? $product->get_id() : absint( $product );
		$action    = isset( $event['action'] ) && is_scalar( $event['action'] ) ? sanitize_key( (string) $event['action'] ) : '';
		$source    = isset( $event['source'] ) && is_scalar( $event['source'] ) ? sanitize_key( (string) $event['source'] ) : '';
		$rule_id   = isset( $event['rule_id'] ) ? absint( $event['rule_id'] ) : 0;

		return implode( ':', array( $object_id, $action, $source, $rule_id ) );
	}

	/**
	 * Gets product history in newest-first order.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get( $product_id ) {
		$product_id = $this->storage_product_id( $product_id );
		if ( $product_id <= 0 ) {
			return array();
		}

		$history = get_post_meta( $product_id, self::META_KEY, true );
		return is_array( $history ) ? array_reverse( $history ) : array();
	}

	/**
	 * Renders read-only history in the WooCommerce product editor.
	 *
	 * @param WP_Post $post Product post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$events = $this->get( $post->ID );
		if ( empty( $events ) ) {
			echo '<p>' . esc_html__( 'No Bulk Sale Pricing activity has been recorded for this product.', 'bulk-sale-pricing' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'When', 'bulk-sale-pricing' ); ?></th><th><?php esc_html_e( 'Activity', 'bulk-sale-pricing' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $events as $event ) : ?>
					<tr>
						<td><?php echo esc_html( $this->display_timestamp( $event ) ); ?></td>
						<td>
							<strong><?php echo esc_html( ucfirst( isset( $event['status'] ) ? $event['status'] : '' ) ); ?></strong>
							<?php if ( ! empty( $event['message'] ) ) : ?>
								&mdash; <?php echo esc_html( $event['message'] ); ?>
							<?php endif; ?>
							<?php $this->render_event_details( $event ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Resolves a variation to its variable parent history location.
	 *
	 * @param WC_Product|int $product Product, variation, or product ID.
	 * @return int
	 */
	private function storage_product_id( $product ) {
		if ( $product instanceof WC_Product ) {
			return $product->is_type( 'variation' ) && $product->get_parent_id() > 0 ? $product->get_parent_id() : $product->get_id();
		}

		$product_id = absint( $product );
		if ( 'product_variation' === get_post_type( $product_id ) ) {
			$parent_id = (int) wp_get_post_parent_id( $product_id );
			return $parent_id > 0 ? $parent_id : $product_id;
		}

		return $product_id;
	}

	/**
	 * Normalizes a small, deterministic event entry.
	 *
	 * @param WC_Product|int $product Product or product ID.
	 * @param array          $event Event data.
	 * @return array<string,mixed>
	 */
	private function normalize_event( $product, $event ) {
		$status = isset( $event['status'] ) ? sanitize_key( $event['status'] ) : 'error';
		if ( ! in_array( $status, array( 'success', 'skipped', 'conflict', 'error' ), true ) ) {
			$status = 'error';
		}

		$object_id   = $product instanceof WC_Product ? $product->get_id() : absint( $product );
		$object_name = $product instanceof WC_Product ? wp_strip_all_tags( $product->get_formatted_name() ) : '';
		$normalized  = array(
			'timestamp'   => current_time( 'mysql', true ),
			'status'      => $status,
			'action'      => isset( $event['action'] ) ? sanitize_key( $event['action'] ) : '',
			'source'      => isset( $event['source'] ) ? sanitize_key( $event['source'] ) : '',
			'rule_id'     => isset( $event['rule_id'] ) ? absint( $event['rule_id'] ) : 0,
			'rule_name'   => isset( $event['rule_name'] ) ? sanitize_text_field( $event['rule_name'] ) : '',
			'object_id'   => $object_id,
			'object_name' => sanitize_text_field( $object_name ),
			'message'     => isset( $event['message'] ) ? sanitize_text_field( $event['message'] ) : '',
		);

		foreach ( array( 'regular_price', 'regular_price_before', 'regular_price_after', 'sale_price_before', 'sale_price_after' ) as $price_key ) {
			if ( isset( $event[ $price_key ] ) && is_scalar( $event[ $price_key ] ) ) {
				$normalized[ $price_key ] = (string) $event[ $price_key ];
			}
		}

		return $normalized;
	}

	/**
	 * Renders optional event details.
	 *
	 * @param array<string,mixed> $event Event data.
	 * @return void
	 */
	private function render_event_details( $event ) {
		$details = array();
		if ( ! empty( $event['action'] ) ) {
			$details[] = sprintf( __( 'Action: %s', 'bulk-sale-pricing' ), $event['action'] );
		}
		if ( ! empty( $event['source'] ) ) {
			$details[] = sprintf( __( 'Source: %s', 'bulk-sale-pricing' ), $event['source'] );
		}
		if ( ! empty( $event['rule_name'] ) ) {
			$details[] = sprintf( __( 'Rule: %1$s (#%2$d)', 'bulk-sale-pricing' ), $event['rule_name'], absint( $event['rule_id'] ) );
		}
		if ( ! empty( $event['object_name'] ) && absint( $event['object_id'] ) ) {
			$details[] = sprintf( __( 'Affected item: %1$s (#%2$d)', 'bulk-sale-pricing' ), $event['object_name'], absint( $event['object_id'] ) );
		}

		$prices = $this->price_details( $event );
		if ( ! empty( $prices ) ) {
			$details[] = $prices;
		}

		if ( ! empty( $details ) ) {
			echo '<p class="description">' . esc_html( implode( ' | ', $details ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Builds an optional concise before/after price summary.
	 *
	 * @param array<string,mixed> $event Event data.
	 * @return string
	 */
	private function price_details( $event ) {
		$details = array();
		if ( isset( $event['regular_price'] ) ) {
			$details[] = sprintf( __( 'Regular: %s', 'bulk-sale-pricing' ), '' === $event['regular_price'] ? '—' : $event['regular_price'] );
		}

		foreach ( array( 'regular_price' => __( 'Regular', 'bulk-sale-pricing' ), 'sale_price' => __( 'Sale', 'bulk-sale-pricing' ) ) as $prefix => $label ) {
			$before = isset( $event[ $prefix . '_before' ] ) ? $event[ $prefix . '_before' ] : null;
			$after  = isset( $event[ $prefix . '_after' ] ) ? $event[ $prefix . '_after' ] : null;
			if ( null !== $before || null !== $after ) {
				$details[] = sprintf( '%1$s: %2$s → %3$s', $label, '' === $before ? '—' : $before, '' === $after ? '—' : $after );
			}
		}

		return implode( '; ', $details );
	}

	/**
	 * Formats a stored UTC timestamp for the current site timezone.
	 *
	 * @param array<string,mixed> $event Event data.
	 * @return string
	 */
	private function display_timestamp( $event ) {
		$timestamp = isset( $event['timestamp'] ) ? (string) $event['timestamp'] : '';
		return '' === $timestamp ? '' : get_date_from_gmt( $timestamp, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	}
}
