<?php
/**
 * Autoloader for SecureSoft Roles & Capabilities plugin.
 *
 * @package SS_Roles_Capabilities
 */

namespace SS_Roles_Capabilities;

/**
 * Autoloader class.
 */
class Autoloader {

	/**
	 * Initialize the autoloader.
	 *
	 * @return void
	 */
	public static function init() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class_name Class name.
	 * @return void
	 */
	public static function autoload( $class_name ) {
		// Only autoload our classes.
		if ( strpos( $class_name, 'SS_Roles_Capabilities\\' ) !== 0 ) {
			return;
		}

		// Remove namespace prefix.
		$class_name = str_replace( 'SS_Roles_Capabilities\\', '', $class_name );

		// Convert namespace separators to directory separators.
		$class_name = str_replace( '\\', '/', $class_name );

		// Build file path.
		$file_path = SS_ROLES_PLUGIN_DIR . 'src/' . $class_name . '.php';

		// Load file if it exists.
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
}



