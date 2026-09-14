<?php
/**
 * Discount-rule ownership cleanup.
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clears sale state from products and variations owned by a deactivated rule.
 */
class BSP_Rule_Deactivation {

	/** @var int[] */
	private static $cleaning_product_ids = array();

	/** @var BSP_Product_History */
	private $history;

	/**
	 * @param BSP_Product_History|null $history Product history.
	 */
	public function __construct( $history = null ) {
		$this->history = $history instanceof BSP_Product_History ? $history : new BSP_Product_History();
	}

	/**
	 * Determines whether this request is already cleaning an item.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return bool
	 */
	public static function is_cleaning_product( $product_id ) {
		return isset( self::$cleaning_product_ids[ $product_id ] );
	}

	/**
	 * Removes BSP-controlled sale state for one rule.
	 *
	 * @param int $rule_id Discount rule ID.
	 * @return array{cleared:int[],failed:int[]}
	 */
	public function cleanup( $rule_id ) {
		$product_ids = get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_bsp_rule_id',
						'value' => (string) $rule_id,
					),
				),
			)
		);

		return $this->cleanup_products( $product_ids, $rule_id, 'deactivation', 'sale_price_removed', __( 'Bulk Sale Pricing removed the Sale Price while the rule was deactivated.', 'bulk-sale-pricing' ) );
	}

	/**
	 * Removes sale state from explicit product or variation IDs owned by one rule.
	 *
	 * @param int[] $product_ids Product or variation IDs.
	 * @param int   $rule_id Discount rule ID.
	 * @param string $source Execution context.
	 * @param string $action History action.
	 * @param string $message History message.
	 * @return array{cleared:int[],failed:int[]}
	 */
	public function cleanup_products( $product_ids, $rule_id, $source = 'deactivation', $action = 'sale_price_removed', $message = '' ) {
		$result     = array(
			'cleared' => array(),
			'failed'  => array(),
		);
		$parent_ids = array();

		foreach ( $product_ids as $product_id ) {
			if ( (string) $rule_id !== get_post_meta( $product_id, '_bsp_rule_id', true ) ) {
				continue;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product instanceof WC_Product ) {
				$result['failed'][] = $product_id;
				$this->history->record( $product_id, $this->event( $rule_id, 'error', 'sale_price_removal_failed', $source, __( 'Bulk Sale Pricing could not load this item to remove its Sale Price.', 'bulk-sale-pricing' ) ) );
				continue;
			}

			$regular_price = $product->get_regular_price();
			$sale_price    = $product->get_sale_price();
			self::$cleaning_product_ids[ $product_id ] = $product_id;
			try {
				$product->set_sale_price( '' );
				$product->set_date_on_sale_from( null );
				$product->set_date_on_sale_to( null );
				$product->save();
			} catch ( Throwable $throwable ) {
				$result['failed'][] = $product_id;
				$this->history->record( $product, $this->event( $rule_id, 'error', 'sale_price_removal_failed', $source, __( 'Bulk Sale Pricing could not remove the Sale Price.', 'bulk-sale-pricing' ), $regular_price, $sale_price, '' ) );
				continue;
			} finally {
				unset( self::$cleaning_product_ids[ $product_id ] );
			}

			if ( $product->is_type( 'variation' ) && $product->get_parent_id() > 0 ) {
				$parent_ids[] = $product->get_parent_id();
			}

			if ( false === delete_post_meta( $product_id, '_bsp_rule_id', (string) $rule_id ) ) {
				$result['failed'][] = $product_id;
				$this->history->record( $product, $this->event( $rule_id, 'error', 'ownership_removal_failed', $source, __( 'Bulk Sale Pricing cleared the Sale Price but could not remove rule ownership.', 'bulk-sale-pricing' ), $regular_price, $sale_price, '' ) );
				continue;
			}

			$result['cleared'][] = $product_id;
			$this->history->record( $product, $this->event( $rule_id, 'success', $action, $source, '' === $message ? __( 'Bulk Sale Pricing removed the Sale Price.', 'bulk-sale-pricing' ) : $message, $regular_price, $sale_price, '' ) );
		}

		foreach ( array_unique( $parent_ids ) as $parent_id ) {
			WC_Product_Variable::sync( $parent_id );
			wc_delete_product_transients( $parent_id );
		}

		return $result;
	}

	/**
	 * Builds structured history data for an ownership cleanup operation.
	 *
	 * @param int    $rule_id Rule ID.
	 * @param string $status Event status.
	 * @param string $action Event action.
	 * @param string $source Execution source.
	 * @param string $message Human-readable message.
	 * @param string $regular_price Current regular price.
	 * @param string $sale_price Sale price before cleanup.
	 * @param string $sale_price_after Sale price after cleanup.
	 * @return array
	 */
	private function event( $rule_id, $status, $action, $source, $message, $regular_price = '', $sale_price = '', $sale_price_after = '' ) {
		$rule = get_post( $rule_id );
		return array(
			'status'               => $status,
			'action'               => $action,
			'source'               => $source,
			'rule_id'              => $rule_id,
			'rule_name'            => $rule instanceof WP_Post ? $rule->post_title : '',
			'message'              => $message,
			'regular_price'        => $regular_price,
			'sale_price_before'    => $sale_price,
			'sale_price_after'     => $sale_price_after,
		);
	}
}
