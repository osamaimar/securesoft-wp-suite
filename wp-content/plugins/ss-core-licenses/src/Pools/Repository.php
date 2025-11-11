<?php
/**
 * License pool repository class.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Pools;

/**
 * License pool repository class.
 */
class Repository {

	/**
	 * Get pool by product ID.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null Pool data or null if not found.
	 */
	public function get_by_product( $product_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_license_pools';

		$pool = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_id = %d",
				$product_id
			),
			ARRAY_A
		);

		if ( $pool ) {
			$pool['policy'] = $pool['policy'] ? json_decode( $pool['policy'], true ) : array();
		}

		return $pool;
	}

	/**
	 * Create or update pool.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $policy     Policy data.
	 * @return int|false Pool ID or false on failure.
	 */
	public function create_or_update( $product_id, $policy = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_license_pools';

		$existing = $this->get_by_product( $product_id );

		if ( $existing ) {
			// Update.
			$wpdb->update(
				$table,
				array(
					'policy' => wp_json_encode( $policy ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'product_id' => $product_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			return $existing['id'];
		} else {
			// Create.
			$wpdb->insert(
				$table,
				array(
					'product_id' => $product_id,
					'qty_cached' => 0,
					'policy' => wp_json_encode( $policy ),
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%s' )
			);

			return $wpdb->insert_id;
		}
	}

	/**
	 * Update cached count.
	 *
	 * @param int $product_id Product ID.
	 * @return bool Success status.
	 */
	public function update_count( $product_id ) {
		global $wpdb;

		$licenses_table = $wpdb->prefix . 'ss_licenses';
		$pools_table = $wpdb->prefix . 'ss_license_pools';

		// Count available licenses.
		$available = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$licenses_table} WHERE product_id = %d AND status = 'available'",
				$product_id
			)
		);

		// Count reserved licenses.
		$reserved = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$licenses_table} WHERE product_id = %d AND status = 'reserved'",
				$product_id
			)
		);

		// Count sold licenses.
		$sold = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$licenses_table} WHERE product_id = %d AND status = 'sold'",
				$product_id
			)
		);

		// Update or create pool.
		$pool = $this->get_by_product( $product_id );

		if ( $pool ) {
			$wpdb->update(
				$pools_table,
				array(
					'qty_cached' => $available,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'product_id' => $product_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$this->create_or_update( $product_id );
			$this->update_count( $product_id ); // Recursive call to update count.
		}

		return true;
	}

	/**
	 * Get all pools.
	 *
	 * @return array Array of pools.
	 */
	public function get_all() {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_license_pools';

		$pools = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY updated_at DESC",
			ARRAY_A
		);

		foreach ( $pools as &$pool ) {
			$pool['policy'] = $pool['policy'] ? json_decode( $pool['policy'], true ) : array();
		}

		return $pools;
	}
}

