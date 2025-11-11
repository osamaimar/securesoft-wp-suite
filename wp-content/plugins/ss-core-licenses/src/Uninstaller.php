<?php
/**
 * Plugin uninstall class.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses;

/**
 * Uninstaller class.
 */
class Uninstaller {

	/**
	 * Uninstall plugin.
	 */
	public static function uninstall() {
		// Check if user has permission.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Check if we should remove data.
		$remove_data = get_option( 'ss_core_remove_data_on_uninstall', false );
		if ( ! $remove_data ) {
			return;
		}

		// Drop database tables.
		$database = new Database();
		$database->drop_tables();

		// Remove options.
		delete_option( 'ss_core_db_version' );
		delete_option( 'ss_core_remove_data_on_uninstall' );
		delete_option( 'ss_core_encryption_key_version' );
		delete_option( 'ss_core_encryption_keys' );
	}
}

