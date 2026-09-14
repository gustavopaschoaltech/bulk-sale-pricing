<?php
/**
 * Plugin Name:       Bulk Sale Pricing
 * Description:       Quickly change sale prices for groups of WooCommerce products.
 * Version:           0.2.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Text Domain:       bulk-sale-pricing
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BulkSalePricing
 */

defined( 'ABSPATH' ) || exit;

define( 'BSP_VERSION', '0.2.0' );
define( 'BSP_FILE', __FILE__ );
define( 'BSP_PATH', plugin_dir_path( __FILE__ ) );
define( 'BSP_URL', plugin_dir_url( __FILE__ ) );

require_once BSP_PATH . 'includes/class-bsp-discount-rule-post-type.php';
require_once BSP_PATH . 'includes/class-bsp-discount-rule-validator.php';
require_once BSP_PATH . 'includes/class-bsp-discount-rule-terms.php';
require_once BSP_PATH . 'includes/class-bsp-discount-rule-migration.php';
require_once BSP_PATH . 'includes/class-bsp-active-discount-rules.php';
require_once BSP_PATH . 'includes/class-bsp-product-matcher.php';
require_once BSP_PATH . 'includes/class-bsp-product-history.php';
require_once BSP_PATH . 'includes/class-bsp-discount-applicator.php';
require_once BSP_PATH . 'includes/class-bsp-product-targets.php';
require_once BSP_PATH . 'includes/class-bsp-rule-activation.php';
require_once BSP_PATH . 'includes/class-bsp-rule-deactivation.php';
require_once BSP_PATH . 'includes/class-bsp-product-synchronizer.php';
require_once BSP_PATH . 'includes/class-bsp-discount-rule-admin.php';
require_once BSP_PATH . 'includes/class-bsp-plugin.php';

BSP_Plugin::instance()->register_hooks();
