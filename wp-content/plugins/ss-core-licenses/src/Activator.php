<?php
/**
 * Plugin activation class.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses;

/**
 * Activator class.
 */
class Activator {

	/**
	 * Activate plugin.
	 */
	public static function activate() {
		// Create database tables.
		$database = new Database();
		$database->create_tables();

		// Initialize encryption keys if not exists.
		$encryption = new Crypto\Encryption();
		$encryption->initialize_keys();

		// Create default capabilities.
		self::create_capabilities();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create default capabilities.
	 */
	private static function create_capabilities() {
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'ss_manage_licenses' );
			$admin_role->add_cap( 'ss_view_plain_codes' );
			$admin_role->add_cap( 'ss_manage_keys' );
			$admin_role->add_cap( 'ss_view_audit_log' );
		}
	}
}

