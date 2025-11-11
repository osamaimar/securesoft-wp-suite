<?php
/**
 * License service class.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Licenses;

use SS_Core_Licenses\Crypto\Encryption;
use SS_Core_Licenses\Audit\Logger;

/**
 * License service class.
 */
class Service {

	/**
	 * License repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Encryption instance.
	 *
	 * @var Encryption
	 */
	private $encryption;

	/**
	 * Audit logger instance.
	 *
	 * @var Logger
	 */
	private $audit_logger;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository   License repository.
	 * @param Encryption $encryption   Encryption instance.
	 * @param Logger     $audit_logger Audit logger instance.
	 */
	public function __construct( Repository $repository, Encryption $encryption, Logger $audit_logger ) {
		$this->repository = $repository;
		$this->encryption = $encryption;
		$this->audit_logger = $audit_logger;
	}

	/**
	 * Create license.
	 *
	 * @param string $code       License code (plaintext).
	 * @param int    $product_id Product ID.
	 * @param array  $meta       Additional metadata.
	 * @return int|false License ID or false on failure.
	 */
	public function create_license( $code, $product_id, $meta = array() ) {
		// Validate inputs.
		if ( empty( $code ) ) {
			return false;
		}

		if ( empty( $product_id ) ) {
			return false;
		}

		// Ensure encryption keys are initialized.
		$this->encryption->initialize_keys();

		// Encrypt license code.
		$code_enc = $this->encryption->encrypt( $code );

		if ( ! $code_enc ) {
			// Log error for debugging.
			error_log( 'SS Core: Encryption failed for license code. Key available: ' . ( $this->encryption->get_key() ? 'yes' : 'no' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}

		// Extract provider_ref from meta if present.
		$provider_ref = null;
		if ( isset( $meta['provider_ref'] ) ) {
			$provider_ref = $meta['provider_ref'];
			unset( $meta['provider_ref'] ); // Remove from meta to avoid duplication.
		}

		// Create license.
		$license_id = $this->repository->create(
			array(
				'product_id' => $product_id,
				'code_enc' => $code_enc,
				'status' => 'available',
				'provider_ref' => $provider_ref,
				'meta' => $meta,
			)
		);

		if ( ! $license_id ) {
			// Log database error.
			global $wpdb;
			error_log( 'SS Core: License creation failed. DB Error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}

		// Log event.
		$this->log_event( $license_id, 'import', get_current_user_id(), $meta );

		// Fire action.
		do_action( 'ss/license/imported', $license_id );

		// Update pool count.
		$this->update_pool_count( $product_id );

		return $license_id;
	}

	/**
	 * Get license with decrypted code.
	 *
	 * @param int  $license_id License ID.
	 * @param bool $decrypt    Whether to decrypt the code.
	 * @return array|null License data or null if not found.
	 */
	public function get_license( $license_id, $decrypt = false ) {
		$license = $this->repository->get_by_id( $license_id );

		if ( ! $license ) {
			return null;
		}

		if ( $decrypt ) {
			$license['code'] = $this->encryption->decrypt( $license['code_enc'] );
		}

		return $license;
	}

	/**
	 * Reserve license for order.
	 *
	 * @param int $product_id Product ID.
	 * @param int $order_id   Order ID.
	 * @return int|false License ID or false on failure.
	 */
	public function reserve_license( $product_id, $order_id ) {
		// Get available license.
		$license = $this->repository->get_available( $product_id );

		if ( ! $license ) {
			return false;
		}

		// Get pool for strategy.
		$pool_repo = new \SS_Core_Licenses\Pools\Repository();
		$pool = $pool_repo->get_by_product( $product_id );

		// Allow filtering license assignment strategy.
		$filtered_license = apply_filters( 'ss/licenses/assign/strategy', $license, $pool );

		// Use filtered license if valid, otherwise use original.
		if ( $filtered_license && is_array( $filtered_license ) && isset( $filtered_license['id'] ) ) {
			$license = $filtered_license;
		}

		// Update status to reserved.
		$updated = $this->repository->update(
			$license['id'],
			array(
				'status' => 'reserved',
				'meta' => array_merge(
					$license['meta'],
					array(
						'order_id' => $order_id,
						'reserved_at' => current_time( 'mysql' ),
					)
				),
			)
		);

		if ( $updated ) {
			// Log event.
			$this->log_event( $license['id'], 'reserve', get_current_user_id(), array( 'order_id' => $order_id ) );

			// Fire action.
			do_action( 'ss/license/reserved', $license['id'], $order_id );

			// Update pool count.
			$this->update_pool_count( $product_id );

			return $license['id'];
		}

		return false;
	}

	/**
	 * Assign license to order.
	 *
	 * @param int $license_id License ID.
	 * @param int $order_id   Order ID.
	 * @return bool Success status.
	 */
	public function assign_license( $license_id, $order_id ) {
		$license = $this->repository->get_by_id( $license_id );

		if ( ! $license ) {
			return false;
		}

		// Update status to sold.
		$updated = $this->repository->update(
			$license_id,
			array(
				'status' => 'sold',
				'meta' => array_merge(
					$license['meta'],
					array(
						'order_id' => $order_id,
						'assigned_at' => current_time( 'mysql' ),
					)
				),
			)
		);

		if ( $updated ) {
			// Log event.
			$this->log_event( $license_id, 'assign', get_current_user_id(), array( 'order_id' => $order_id ) );

			// Fire action.
			do_action( 'ss/license/assigned', $license_id, $order_id );

			// Update pool count.
			$this->update_pool_count( $license['product_id'] );

			return true;
		}

		return false;
	}

	/**
	 * Revoke license.
	 *
	 * @param int $license_id License ID.
	 * @return bool Success status.
	 */
	public function revoke_license( $license_id ) {
		$license = $this->repository->get_by_id( $license_id );

		if ( ! $license ) {
			return false;
		}

		// Update status to revoked.
		$updated = $this->repository->update(
			$license_id,
			array(
				'status' => 'revoked',
				'meta' => array_merge(
					$license['meta'],
					array(
						'revoked_at' => current_time( 'mysql' ),
						'revoked_by' => get_current_user_id(),
					)
				),
			)
		);

		if ( $updated ) {
			// Log event.
			$this->log_event( $license_id, 'revoke', get_current_user_id() );

			// Fire action.
			do_action( 'ss/license/revoked', $license_id );

			// Update pool count.
			$this->update_pool_count( $license['product_id'] );

			return true;
		}

		return false;
	}

	/**
	 * Release reserved license.
	 *
	 * @param int $license_id License ID.
	 * @return bool Success status.
	 */
	public function release_license( $license_id ) {
		$license = $this->repository->get_by_id( $license_id );

		if ( ! $license || 'reserved' !== $license['status'] ) {
			return false;
		}

		// Update status to available.
		$meta = $license['meta'];
		unset( $meta['order_id'] );
		unset( $meta['reserved_at'] );

		$updated = $this->repository->update(
			$license_id,
			array(
				'status' => 'available',
				'meta' => $meta,
			)
		);

		if ( $updated ) {
			// Log event.
			$this->log_event( $license_id, 'release', get_current_user_id() );

			// Update pool count.
			$this->update_pool_count( $license['product_id'] );

			return true;
		}

		return false;
	}

	/**
	 * Get licenses by order.
	 *
	 * @param int $order_id Order ID.
	 * @return array Array of licenses.
	 */
	public function get_licenses_by_order( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_licenses';

		$licenses = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE JSON_EXTRACT(meta, '$.order_id') = %d",
				$order_id
			),
			ARRAY_A
		);

		foreach ( $licenses as &$license ) {
			$license['meta'] = $license['meta'] ? json_decode( $license['meta'], true ) : array();
		}

		return $licenses;
	}

	/**
	 * Log license event.
	 *
	 * @param int    $license_id License ID.
	 * @param string $type       Event type.
	 * @param int    $user_id    User ID.
	 * @param array  $meta       Additional metadata.
	 */
	private function log_event( $license_id, $type, $user_id, $meta = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ss_license_events';

		$wpdb->insert(
			$table,
			array(
				'license_id' => $license_id,
				'type' => $type,
				'actor_user_id' => $user_id,
				'meta' => wp_json_encode( $meta ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		// Also log to audit log for main events tracking.
		$audit_action_map = array(
			'import' => 'license_imported',
			'reserve' => 'license_reserved',
			'assign' => 'license_assigned',
			'revoke' => 'license_revoked',
			'release' => 'license_released',
		);

		$audit_action = isset( $audit_action_map[ $type ] ) ? $audit_action_map[ $type ] : 'license_' . $type;

		$this->audit_logger->log(
			$user_id,
			$audit_action,
			'license',
			$license_id,
			$meta
		);
	}

	/**
	 * Update pool count.
	 *
	 * @param int $product_id Product ID.
	 */
	private function update_pool_count( $product_id ) {
		$pool_repo = new \SS_Core_Licenses\Pools\Repository();
		$pool_repo->update_count( $product_id );
	}
}

