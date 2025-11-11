<?php
/**
 * REST API controller for licenses.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\REST\Controllers;

use SS_Core_Licenses\Licenses\Service;
use SS_Core_Licenses\Audit\Logger;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Licenses REST controller.
 */
class Licenses extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ss/v1';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'licenses';

	/**
	 * License service instance.
	 *
	 * @var Service
	 */
	private $license_service;

	/**
	 * Audit logger instance.
	 *
	 * @var Logger
	 */
	private $audit_logger;

	/**
	 * Constructor.
	 *
	 * @param Service $license_service License service.
	 * @param Logger  $audit_logger    Audit logger.
	 */
	public function __construct( Service $license_service, Logger $audit_logger ) {
		$this->license_service = $license_service;
		$this->audit_logger = $audit_logger;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'import_licenses' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args' => array(
					'product_id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => __( 'Product ID', 'ss-core-licenses' ),
					),
					'codes' => array(
						'required' => true,
						'type' => 'array',
						'items' => array( 'type' => 'string' ),
						'description' => __( 'Array of license codes', 'ss-core-licenses' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/pool/(?P<product_id>\d+)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_pool' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args' => array(
					'product_id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => __( 'Product ID', 'ss-core-licenses' ),
					),
				),
			)
		);
	}

	/**
	 * Check permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function check_permissions( $request ) {
		return current_user_can( 'ss_manage_licenses' );
	}

	/**
	 * Import licenses.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function import_licenses( $request ) {
		$product_id = $request->get_param( 'product_id' );
		$codes = $request->get_param( 'codes' );

		if ( empty( $codes ) || ! is_array( $codes ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid codes array.', 'ss-core-licenses' ),
				),
				400
			);
		}

		$imported = 0;
		$failed = 0;

		foreach ( $codes as $code ) {
			$license_id = $this->license_service->create_license( $code, $product_id );

			if ( $license_id ) {
				$imported++;
			} else {
				$failed++;
			}
		}

		// Log import event.
		$this->audit_logger->log(
			get_current_user_id(),
			'licenses_imported',
			'license',
			null,
			array(
				'product_id' => $product_id,
				'imported' => $imported,
				'failed' => $failed,
				'source' => 'rest_api',
			)
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'imported' => $imported,
				'failed' => $failed,
				'message' => sprintf(
					// translators: %d: number of imported licenses.
					_n( '%d license imported.', '%d licenses imported.', $imported, 'ss-core-licenses' ),
					$imported
				),
			),
			200
		);
	}

	/**
	 * Get pool data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_pool( $request ) {
		$product_id = $request->get_param( 'product_id' );

		$pool_repo = new \SS_Core_Licenses\Pools\Repository();
		$pool = $pool_repo->get_by_product( $product_id );

		if ( ! $pool ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Pool not found.', 'ss-core-licenses' ),
				),
				404
			);
		}

		$license_repo = new \SS_Core_Licenses\Licenses\Repository();
		$available = $license_repo->count_by_status( $product_id, 'available' );
		$reserved = $license_repo->count_by_status( $product_id, 'reserved' );
		$sold = $license_repo->count_by_status( $product_id, 'sold' );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data' => array(
					'pool_id' => $pool['id'],
					'product_id' => $product_id,
					'available' => $available,
					'reserved' => $reserved,
					'sold' => $sold,
					'policy' => $pool['policy'],
				),
			),
			200
		);
	}
}

