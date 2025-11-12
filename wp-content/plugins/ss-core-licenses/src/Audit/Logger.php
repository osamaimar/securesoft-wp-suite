<?php
/**
 * Audit logger class.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Audit;

/**
 * Audit logger class.
 */
class Logger {

	/**
	 * Log an audit event.
	 *
	 * @param int    $actor_id   Actor user ID.
	 * @param string $action     Action performed.
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id  Entity ID.
	 * @param array  $meta       Additional metadata.
	 * @return int|false Log ID or false on failure.
	 */
	public function log( $actor_id, $action, $entity_type, $entity_id = null, $meta = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_audit_log';

		// Get IP address.
		$ip = $this->get_client_ip();

		// Get user agent.
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$log_id = $wpdb->insert(
			$table,
			array(
				'actor_user_id' => $actor_id,
				'action' => $action,
				'entity_type' => $entity_type,
				'entity_id' => $entity_id,
				'ip' => $ip,
				'user_agent' => $user_agent,
				'meta' => wp_json_encode( $meta ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( $log_id ) {
			// Fire action.
			do_action( 'ss/audit/log', $actor_id, $action, $entity_type, $entity_id, $meta );

			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Get audit logs.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of audit logs.
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_audit_log';

		$defaults = array(
			'actor_user_id' => null,
			'action' => null,
			'entity_type' => null,
			'entity_id' => null,
			'date_from' => null,
			'date_to' => null,
			'limit' => 100,
			'offset' => 0,
			'orderby' => 'created_at',
			'order' => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( $args['actor_user_id'] ) {
			$where[] = 'actor_user_id = %d';
			$values[] = $args['actor_user_id'];
		}

		if ( $args['action'] ) {
			$where[] = 'action = %s';
			$values[] = $args['action'];
		}

		if ( $args['entity_type'] ) {
			$where[] = 'entity_type = %s';
			$values[] = $args['entity_type'];
		}

		if ( $args['entity_id'] ) {
			$where[] = 'entity_id = %d';
			$values[] = $args['entity_id'];
		}

		if ( $args['date_from'] ) {
			$where[] = 'created_at >= %s';
			$values[] = $args['date_from'];
		}

		if ( $args['date_to'] ) {
			$where[] = 'created_at <= %s';
			$values[] = $args['date_to'];
		}

		$where_clause = implode( ' AND ', $where );

		// Validate and sanitize orderby field.
		$allowed_orderby = array( 'id', 'actor_user_id', 'action', 'entity_type', 'entity_id', 'ip', 'created_at' );
		$orderby_field = strtolower( $args['orderby'] );
		$order_direction = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		if ( ! in_array( $orderby_field, $allowed_orderby, true ) ) {
			$orderby_field = 'created_at';
		}

		// Build query - ORDER BY cannot be parameterized, so we validate the field.
		$orderby_clause = "{$orderby_field} {$order_direction}";

		// Build query - handle limit -1 (no limit) case.
		if ( $args['limit'] > 0 ) {
			if ( ! empty( $values ) ) {
				$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby_clause} LIMIT %d OFFSET %d";
			$values[] = $args['limit'];
			$values[] = $args['offset'];
			$logs = $wpdb->get_results(
				$wpdb->prepare( $query, $values ),
				ARRAY_A
			);
			} else {
				// No WHERE conditions, but we have LIMIT.
				$query = $wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY {$orderby_clause} LIMIT %d OFFSET %d",
					$args['limit'],
					$args['offset']
				);
				$logs = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		} else {
			// No limit - get all results, but still use prepare for WHERE clause.
			if ( ! empty( $values ) ) {
				$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby_clause}";
				$logs = $wpdb->get_results(
					$wpdb->prepare( $query, $values ),
					ARRAY_A
				);
			} else {
				// No WHERE conditions, safe to query directly.
				$query = "SELECT * FROM {$table} ORDER BY {$orderby_clause}";
				$logs = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		foreach ( $logs as &$log ) {
			$log['meta'] = $log['meta'] ? json_decode( $log['meta'], true ) : array();
		}

		return $logs;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_REAL_IP',        // Nginx proxy.
			'HTTP_X_FORWARDED_FOR',  // Proxy.
			'REMOTE_ADDR',           // Standard.
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (from X-Forwarded-For).
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = explode( ',', $ip );
					$ip = trim( $ip[0] );
				}
				return $ip;
			}
		}

		return '0.0.0.0';
	}
}

