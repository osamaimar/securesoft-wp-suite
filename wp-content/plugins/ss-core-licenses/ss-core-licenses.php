<?php
/**
 * Plugin Name: SecureSoft Core & Licenses
 * Plugin URI: https://securesoft.com
 * Description: Core plugin for SecureSoft license management, encryption, and WooCommerce integration.
 * Version: 1.0.1
 * Author: SecureSoft
 * Author URI: https://securesoft.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ss-core-licenses
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package SS_Core_Licenses
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 */
define( 'SS_CORE_VERSION', '1.0.1' );

/**
 * Plugin directory path.
 */
define( 'SS_CORE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'SS_CORE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'SS_CORE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check PHP version.
 */
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', 'ss_core_php_version_notice' );
	return;
}

/**
 * Display PHP version notice.
 */
function ss_core_php_version_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'SecureSoft Core & Licenses requires PHP 7.4 or higher. Please upgrade your PHP version.', 'ss-core-licenses' );
	echo '</p></div>';
}

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function ss_core_is_woocommerce_active() {
	// Check for WooCommerce class.
	if ( class_exists( 'WooCommerce' ) ) {
		return true;
	}

	// Check for WooCommerce constant.
	if ( defined( 'WC_VERSION' ) ) {
		return true;
	}

	// Check for WooCommerce function.
	if ( function_exists( 'WC' ) ) {
		return true;
	}

	// Check if plugin is active.
	if ( ! function_exists( 'is_plugin_active' ) ) {
		if ( file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	if ( function_exists( 'is_plugin_active' ) ) {
		return is_plugin_active( 'woocommerce/woocommerce.php' );
	}

	return false;
}

/**
 * Initialize the plugin.
 */
function ss_core_licenses() {
	return \SS_Core_Licenses\Plugin::instance();
}

// Register activation hook.
register_activation_hook( __FILE__, array( 'SS_Core_Licenses\Activator', 'activate' ) );

// Register deactivation hook.
register_deactivation_hook( __FILE__, array( 'SS_Core_Licenses\Deactivator', 'deactivate' ) );

// Register uninstall hook.
register_uninstall_hook( __FILE__, array( 'SS_Core_Licenses\Uninstaller', 'uninstall' ) );

// Load autoloader.
require_once SS_CORE_PLUGIN_DIR . 'src/Autoloader.php';
\SS_Core_Licenses\Autoloader::init();

/**
 * Declare WooCommerce compatibility.
 * This must run before WooCommerce initializes.
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		// Declare compatibility with custom order tables (HPOS).
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		
		// Declare compatibility with cart/checkout blocks.
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

// Initialize plugin on plugins_loaded with low priority to ensure WooCommerce is loaded.
add_action( 'plugins_loaded', 'ss_core_licenses', 5 );

