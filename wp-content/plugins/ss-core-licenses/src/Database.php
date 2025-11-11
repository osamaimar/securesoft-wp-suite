<?php
/**
 * Database management class.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses;

/**
 * Database class.
 */
class Database {

	/**
	 * Database version.
	 *
	 * @var string
	 */
	private $version = '1.0.1';

	/**
	 * Create database tables.
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Table: ss_licenses
		$table_licenses = $wpdb->prefix . 'ss_licenses';
		$sql_licenses = "CREATE TABLE IF NOT EXISTS {$table_licenses} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id bigint(20) UNSIGNED NOT NULL,
			code_enc text NOT NULL,
			status enum('available', 'reserved', 'sold', 'revoked') NOT NULL DEFAULT 'available',
			provider_ref varchar(255) DEFAULT NULL,
			meta longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql_licenses );

		// Table: ss_license_pools
		$table_pools = $wpdb->prefix . 'ss_license_pools';
		$sql_pools = "CREATE TABLE IF NOT EXISTS {$table_pools} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id bigint(20) UNSIGNED NOT NULL,
			qty_cached int(11) UNSIGNED NOT NULL DEFAULT 0,
			policy longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY product_id (product_id)
		) {$charset_collate};";
		dbDelta( $sql_pools );

		// Table: ss_license_events
		$table_events = $wpdb->prefix . 'ss_license_events';
		$sql_events = "CREATE TABLE IF NOT EXISTS {$table_events} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			license_id bigint(20) UNSIGNED NOT NULL,
			type enum('reserve', 'assign', 'release', 'revoke', 'import') NOT NULL,
			actor_user_id bigint(20) UNSIGNED NOT NULL,
			meta longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY license_id (license_id),
			KEY type (type),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql_events );

		// Table: ss_audit_log
		$table_audit = $wpdb->prefix . 'ss_audit_log';
		$sql_audit = "CREATE TABLE IF NOT EXISTS {$table_audit} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			actor_user_id bigint(20) UNSIGNED NOT NULL,
			action varchar(255) NOT NULL,
			entity_type varchar(255) NOT NULL,
			entity_id bigint(20) UNSIGNED DEFAULT NULL,
			ip varchar(255) DEFAULT NULL,
			user_agent varchar(255) DEFAULT NULL,
			meta longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY actor_user_id (actor_user_id),
			KEY action (action),
			KEY entity_type (entity_type),
			KEY entity_id (entity_id),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql_audit );

		// Store database version.
		$current_version = get_option( 'ss_core_db_version', '0' );
		if ( version_compare( $current_version, $this->version, '<' ) ) {
			// Run database upgrades.
			$this->upgrade_database( $current_version );
		}
		update_option( 'ss_core_db_version', $this->version );
	}

	/**
	 * Upgrade database from previous versions.
	 *
	 * @param string $current_version Current database version.
	 */
	private function upgrade_database( $current_version ) {
		global $wpdb;

		// Version 1.0.1: Ensure audit_log table has correct structure.
		if ( version_compare( $current_version, '1.0.1', '<' ) ) {
			$table_audit = $wpdb->prefix . 'ss_audit_log';
			
			// Check if table exists.
			$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_audit ) ) === $table_audit;
			
			if ( $table_exists ) {
				// Check if actor_user_id column exists.
				$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table_audit} LIKE %s", 'actor_user_id' ) );
				
				if ( empty( $column_exists ) ) {
					// Add missing column.
					$wpdb->query( "ALTER TABLE {$table_audit} ADD COLUMN actor_user_id bigint(20) UNSIGNED NOT NULL AFTER id" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( "ALTER TABLE {$table_audit} ADD INDEX actor_user_id (actor_user_id)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
			}
		}
	}

	/**
	 * Drop database tables.
	 */
	public function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'ss_licenses',
			$wpdb->prefix . 'ss_license_pools',
			$wpdb->prefix . 'ss_license_events',
			$wpdb->prefix . 'ss_audit_log',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		delete_option( 'ss_core_db_version' );
	}
}

