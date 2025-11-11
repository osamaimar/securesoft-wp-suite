<?php
/**
 * REST API controller for audit logs.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\REST\Controllers;

use SS_Core_Licenses\Audit\Logger;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Audit REST controller.
 */
class Audit extends WP_REST_Controller {

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
	protected $rest_base = 'audit';

	/**
	 * Audit logger instance.
	 *
	 * @var Logger
	 */
	private $audit_logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $audit_logger Audit logger.
	 */
	public function __construct( Logger $audit_logger ) {
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
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_logs' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args' => array(
					'actor_user_id' => array(
						'type' => 'integer',
						'description' => __( 'Filter by user ID', 'ss-core-licenses' ),
					),
					'action' => array(
						'type' => 'string',
						'description' => __( 'Filter by action', 'ss-core-licenses' ),
					),
					'entity_type' => array(
						'type' => 'string',
						'description' => __( 'Filter by entity type', 'ss-core-licenses' ),
					),
					'date_from' => array(
						'type' => 'string',
						'description' => __( 'Filter from date', 'ss-core-licenses' ),
					),
					'date_to' => array(
						'type' => 'string',
						'description' => __( 'Filter to date', 'ss-core-licenses' ),
					),
					'limit' => array(
						'type' => 'integer',
						'default' => 100,
						'description' => __( 'Limit results', 'ss-core-licenses' ),
					),
					'offset' => array(
						'type' => 'integer',
						'default' => 0,
						'description' => __( 'Offset results', 'ss-core-licenses' ),
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
		return current_user_can( 'ss_view_audit_log' );
	}

	/**
	 * Get audit logs.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_logs( $request ) {
		$args = array(
			'actor_user_id' => $request->get_param( 'actor_user_id' ),
			'action' => $request->get_param( 'action' ),
			'entity_type' => $request->get_param( 'entity_type' ),
			'date_from' => $request->get_param( 'date_from' ),
			'date_to' => $request->get_param( 'date_to' ),
			'limit' => $request->get_param( 'limit' ),
			'offset' => $request->get_param( 'offset' ),
		);

		// Remove empty values.
		$args = array_filter( $args, function( $value ) {
			return $value !== null && $value !== '';
		} );

		$logs = $this->audit_logger->get_logs( $args );

		// Format logs for response.
		$formatted_logs = array();
		foreach ( $logs as $log ) {
			$user = get_user_by( 'id', $log['actor_user_id'] );
			$formatted_logs[] = array(
				'id' => $log['id'],
				'date' => $log['created_at'],
				'user' => $user ? array(
					'id' => $user->ID,
					'name' => $user->display_name,
					'email' => $user->user_email,
				) : null,
				'action' => $log['action'],
				'entity_type' => $log['entity_type'],
				'entity_id' => $log['entity_id'],
				'ip' => $log['ip'],
				'meta' => $log['meta'],
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data' => $formatted_logs,
				'count' => count( $formatted_logs ),
			),
			200
		);
	}
}

