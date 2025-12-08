<?php
/**
 * Plugin Name: SecureSoft Roles & Capabilities
 * Plugin URI: https://securesoft.com
 * Description: Centralized roles, capabilities, policies, and webhooks for the SecureSoft ecosystem.
 * Version: 1.0.0
 * Author: SecureSoft
 * Author URI: https://securesoft.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ss-roles-capabilities
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package SS_Roles_Capabilities
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 */
define( 'SS_ROLES_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 */
define( 'SS_ROLES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'SS_ROLES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'SS_ROLES_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check PHP version.
 */
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', 'ss_roles_php_version_notice' );
	return;
}

/**
 * Display PHP version notice.
 */
function ss_roles_php_version_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'SecureSoft Roles & Capabilities requires PHP 7.4 or higher. Please upgrade your PHP version.', 'ss-roles-capabilities' );
	echo '</p></div>';
}

/**
 * Check if SecureSoft Core plugin is active.
 *
 * @return bool
 */
function ss_roles_is_core_active() {
	if ( class_exists( '\SS_Core_Licenses\Plugin' ) ) {
		return true;
	}

	if ( ! function_exists( 'is_plugin_active' ) ) {
		if ( file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	if ( function_exists( 'is_plugin_active' ) ) {
		return is_plugin_active( 'ss-core-licenses/ss-core-licenses.php' );
	}

	return false;
}

/**
 * Show admin notice when Core plugin is missing.
 */
function ss_roles_core_missing_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'SecureSoft Roles & Capabilities requires the SecureSoft Core & Licenses plugin to be installed and activated.', 'ss-roles-capabilities' );
	echo '</p></div>';
}

/**
 * Initialize the plugin.
 *
 * @return \SS_Roles_Capabilities\Plugin|null
 */
function ss_roles_capabilities() {
	if ( ! ss_roles_is_core_active() ) {
		add_action( 'admin_notices', 'ss_roles_core_missing_notice' );
		return null;
	}

	return \SS_Roles_Capabilities\Plugin::instance();
}

// Register activation/deactivation hooks (roles/caps mostly handled on init).
register_activation_hook(
	__FILE__,
	function () {
		// Ensure autoloader is available.
		require_once SS_ROLES_PLUGIN_DIR . 'src/Autoloader.php';
		\SS_Roles_Capabilities\Autoloader::init();

		// Register roles and capabilities on activation.
		$registrar = new \SS_Roles_Capabilities\Roles\Registrar();
		$registrar->register_roles();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		// No destructive cleanup by default; roles are left in place.
	}
);

// Load autoloader.
require_once SS_ROLES_PLUGIN_DIR . 'src/Autoloader.php';
\SS_Roles_Capabilities\Autoloader::init();

// Initialize plugin on plugins_loaded.
add_action( 'plugins_loaded', 'ss_roles_capabilities', 15 );



