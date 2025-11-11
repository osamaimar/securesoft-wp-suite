<?php
/**
 * License repository class.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Licenses;

/**
 * License repository class.
 */
class Repository {

	/**
	 * Get license by ID.
	 *
	 * @param int $license_id License ID.
	 * @return array|null License data or null if not found.
	 */
	public function get_by_id( $license_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_licenses';

		$license = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$license_id
			),
			ARRAY_A
		);

		if ( $license ) {
			$license['meta'] = $license['meta'] ? json_decode( $license['meta'], true ) : array();
		}

		return $license;
	}

	/**
	 * Get licenses by product ID.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $status     Status filter.
	 * @param int    $limit      Limit.
	 * @param int    $offset     Offset.
	 * @return array Array of licenses.
	 */
	public function get_by_product( $product_id, $status = null, $limit = 100, $offset = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_licenses';

		$where = array( 'product_id = %d' );
		$values = array( $product_id );

		if ( $status ) {
			$where[] = 'status = %s';
			$values[] = $status;
		}

		$where_clause = implode( ' AND ', $where );

		$licenses = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				array_merge( $values, array( $limit, $offset ) )
			),
			ARRAY_A
		);

		foreach ( $licenses as &$license ) {
			$license['meta'] = $license['meta'] ? json_decode( $license['meta'], true ) : array();
		}

		return $licenses;
	}

	/**
	 * Get available license for product.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null License data or null if not found.
	 */
	public function get_available( $product_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_licenses';

		$license = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_id = %d AND status = 'available' ORDER BY id ASC LIMIT 1",
				$product_id
			),
			ARRAY_A
		);

		if ( $license ) {
			$license['meta'] = $license['meta'] ? json_decode( $license['meta'], true ) : array();
		}

		return $license;
	}

	/**
	 * Create license.
	 *
	 * @param array $data License data.
	 * @return int|false License ID or false on failure.
	 */
	public function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_licenses';

		$defaults = array(
			'product_id' => 0,
			'code_enc' => '',
			'status' => 'available',
			'provider_ref' => null,
			'meta' => array(),
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		if ( is_array( $data['meta'] ) ) {
			$data['meta'] = wp_json_encode( $data['meta'] );
		}

		// Build format array dynamically based on data types.
		$format = array();
		foreach ( $data as $key => $value ) {
			if ( 'id' === $key ) {
				continue; // Skip ID field.
			}
			if ( 'product_id' === $key ) {
				$format[] = '%d';
			} elseif ( in_array( $key, array( 'code_enc', 'status', 'provider_ref', 'meta', 'created_at', 'updated_at' ), true ) ) {
				$format[] = '%s';
			} else {
				$format[] = '%s'; // Default to string.
			}
		}

		// Remove 'id' from data if present.
		unset( $data['id'] );

		$result = $wpdb->insert(
			$table,
			$data,
			$format
		);

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update license.
	 *
	 * @param int   $license_id License ID.
	 * @param array $data       License data.
	 * @return bool Success status.
	 */
	public function update( $license_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_licenses';

		$data['updated_at'] = current_time( 'mysql' );

		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$data['meta'] = wp_json_encode( $data['meta'] );
		}

		$format = array();
		foreach ( $data as $key => $value ) {
			if ( 'id' === $key ) {
				continue;
			}
			if ( is_int( $value ) ) {
				$format[] = '%d';
			} elseif ( is_float( $value ) ) {
				$format[] = '%f';
			} else {
				$format[] = '%s';
			}
		}

		$result = $wpdb->update(
			$table,
			$data,
			array( 'id' => $license_id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete license.
	 *
	 * @param int $license_id License ID.
	 * @return bool Success status.
	 */
	public function delete( $license_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_licenses';

		$result = $wpdb->delete(
			$table,
			array( 'id' => $license_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get all licenses.
	 *
	 * @param int|null $product_id Optional product ID to filter by.
	 * @return array Array of licenses.
	 */
	public function get_all( $product_id = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_licenses';

		if ( $product_id ) {
			$licenses = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE product_id = %d",
					$product_id
				),
				ARRAY_A
			);
		} else {
			$licenses = $wpdb->get_results(
				"SELECT * FROM {$table}",
				ARRAY_A
			);
		}

		foreach ( $licenses as &$license ) {
			$license['meta'] = $license['meta'] ? json_decode( $license['meta'], true ) : array();
		}

		return $licenses;
	}

	/**
	 * Count licenses by status.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $status     Status.
	 * @return int Count.
	 */
	public function count_by_status( $product_id, $status ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_licenses';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND status = %s",
				$product_id,
				$status
			)
		);

		return (int) $count;
	}

	/**
	 * Search licenses.
	 *
	 * @param array $args Search arguments.
	 * @return array Array of licenses.
	 */
	public function search( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_licenses';

		$defaults = array(
			'product_id' => null,
			'status' => null,
			'search' => null,
			'provider_ref' => null,
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

		if ( $args['product_id'] ) {
			$where[] = 'product_id = %d';
			$values[] = $args['product_id'];
		}

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$values[] = $args['status'];
		}

		if ( $args['search'] ) {
			$where[] = '(provider_ref LIKE %s OR id = %d)';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = (int) $args['search'];
		}

		if ( $args['provider_ref'] ) {
			$where[] = 'provider_ref LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['provider_ref'] ) . '%';
		}

		if ( $args['date_from'] ) {
			$where[] = 'created_at >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}

		if ( $args['date_to'] ) {
			$where[] = 'created_at <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		if ( ! $orderby ) {
			$orderby = 'created_at DESC';
		}

		// Build query - handle limit -1 (no limit) case.
		if ( $args['limit'] > 0 ) {
			$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
			$values[] = $args['limit'];
			$values[] = $args['offset'];
			$licenses = $wpdb->get_results(
				$wpdb->prepare( $query, $values ),
				ARRAY_A
			);
		} else {
			// No limit - get all results, but still use prepare for WHERE clause.
			if ( ! empty( $values ) ) {
				$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby}";
				$licenses = $wpdb->get_results(
					$wpdb->prepare( $query, $values ),
					ARRAY_A
				);
			} else {
				// No WHERE conditions, safe to query directly.
				$query = "SELECT * FROM {$table} ORDER BY {$orderby}";
				$licenses = $wpdb->get_results( $query, ARRAY_A );
			}
		}

		foreach ( $licenses as &$license ) {
			$license['meta'] = $license['meta'] ? json_decode( $license['meta'], true ) : array();
		}

		return $licenses;
	}
}

