<?php
/**
 * Plugin deactivation class.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses;

/**
 * Deactivator class.
 */
class Deactivator {

	/**
	 * Deactivate plugin.
	 */
	public static function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}

