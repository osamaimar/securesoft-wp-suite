<?php
/**
 * REST API controller for key rotation.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\REST\Controllers;

use SS_Core_Licenses\Crypto\Encryption;
use SS_Core_Licenses\Audit\Logger;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Rotate REST controller.
 */
class Rotate extends WP_REST_Controller {

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
	protected $rest_base = 'licenses/rotate';

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
	 * @param Encryption $encryption   Encryption instance.
	 * @param Logger     $audit_logger Audit logger.
	 */
	public function __construct( Encryption $encryption, Logger $audit_logger ) {
		$this->encryption = $encryption;
		$this->audit_logger = $audit_logger;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'rotate_keys' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args' => array(
					'dry_run' => array(
						'type' => 'boolean',
						'default' => false,
						'description' => __( 'Perform dry run', 'ss-core-licenses' ),
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
		return current_user_can( 'ss_manage_keys' );
	}

	/**
	 * Rotate keys.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rotate_keys( $request ) {
		$dry_run = $request->get_param( 'dry_run' );

		$result = $this->encryption->rotate_keys( $dry_run );

		if ( ! $dry_run ) {
			// Log event.
			$this->audit_logger->log(
				get_current_user_id(),
				'keys_rotated',
				'key',
				$result['new_version'],
				array(
					'new_version' => $result['new_version'],
					'source' => 'rest_api',
				)
			);
		}

		return new WP_REST_Response(
			array(
				'success' => $result['success'],
				'message' => $result['message'],
				'new_version' => isset( $result['new_version'] ) ? $result['new_version'] : null,
			),
			200
		);
	}
}

