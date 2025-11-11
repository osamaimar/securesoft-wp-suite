<?php
/**
 * Autoloader for SecureSoft Core & Licenses plugin.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses;

/**
 * Autoloader class.
 */
class Autoloader {

	/**
	 * Initialize the autoloader.
	 */
	public static function init() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class_name Class name.
	 */
	public static function autoload( $class_name ) {
		// Only autoload our classes.
		if ( strpos( $class_name, 'SS_Core_Licenses\\' ) !== 0 ) {
			return;
		}

		// Remove namespace prefix.
		$class_name = str_replace( 'SS_Core_Licenses\\', '', $class_name );

		// Convert namespace separators to directory separators.
		$class_name = str_replace( '\\', '/', $class_name );

		// Build file path.
		$file_path = SS_CORE_PLUGIN_DIR . 'src/' . $class_name . '.php';

		// Load file if it exists.
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
}

