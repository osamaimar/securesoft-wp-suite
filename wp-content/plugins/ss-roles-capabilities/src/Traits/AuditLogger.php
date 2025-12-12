<?php
/**
 * Trait for audit logging functionality.
 *
 * @package SS_Roles_Capabilities
 */

namespace SS_Roles_Capabilities\Traits;

/**
 * Audit logger trait.
 */
trait AuditLogger {

	/**
	 * Get Core plugin audit logger instance.
	 *
	 * @return \SS_Core_Licenses\Audit\Logger|null Logger instance or null if Core is not available.
	 */
	protected function get_audit_logger() {
		if ( ! class_exists( '\SS_Core_Licenses\Plugin' ) ) {
			return null;
		}

		$core_plugin = \SS_Core_Licenses\Plugin::instance();
		if ( ! $core_plugin || ! isset( $core_plugin->audit_logger ) ) {
			return null;
		}

		return $core_plugin->audit_logger;
	}

	/**
	 * Log an audit event via Core plugin.
	 *
	 * @param int    $actor_id   Actor user ID.
	 * @param string $action     Action performed.
	 * @param string $entity_type Entity type (e.g., 'role', 'user', 'capability', 'policy', 'webhook').
	 * @param int|string|null $entity_id  Entity ID (can be role key for roles).
	 * @param array  $meta       Additional metadata.
	 * @return void
	 */
	protected function log_audit_event( $actor_id, $action, $entity_type, $entity_id = null, $meta = array() ) {
		$logger = $this->get_audit_logger();
		if ( ! $logger ) {
			// Core plugin not available, skip logging.
			return;
		}

		// For roles, entity_id might be a string (role key), so we need to handle it.
		// The audit log table expects entity_id as int, but we can store role keys in meta.
		if ( 'role' === $entity_type && is_string( $entity_id ) ) {
			$meta['role_key'] = $entity_id;
			$entity_id = null;
		}

		$logger->log(
			$actor_id,
			$action,
			$entity_type,
			$entity_id,
			$meta
		);
	}
}

