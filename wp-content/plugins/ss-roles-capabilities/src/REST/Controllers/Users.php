<?php
/**
 * REST controller for user-related role operations.
 *
 * @package SS_Roles_Capabilities
 */

namespace SS_Roles_Capabilities\REST\Controllers;

use SS_Roles_Capabilities\Roles\Registrar;
use SS_Roles_Capabilities\Traits\AuditLogger;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

/**
 * Users REST controller.
 */
class Users extends WP_REST_Controller {

	use AuditLogger;

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ss/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'users';

	/**
	 * Roles registrar.
	 *
	 * @var Registrar
	 */
	protected $registrar;

	/**
	 * Constructor.
	 *
	 * @param Registrar $registrar Roles registrar.
	 */
	public function __construct( Registrar $registrar ) {
		$this->registrar = $registrar;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/role',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_user_role' ),
					'permission_callback' => array( $this, 'can_manage_roles' ),
				),
			)
		);
	}

	/**
	 * Permission: manage roles.
	 *
	 * @return bool
	 */
	public function can_manage_roles() {
		return current_user_can( 'ss_manage_roles' );
	}

	/**
	 * PUT /users/{id}/role
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_user_role( WP_REST_Request $request ) {
		$user_id = (int) $request['id'];
		$new_role = sanitize_key( $request->get_param( 'role' ) );

		if ( empty( $new_role ) ) {
			return new WP_Error( 'ss_users_invalid_role', __( 'Role is required.', 'ss-roles-capabilities' ), array( 'status' => 400 ) );
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'ss_users_not_found', __( 'User not found.', 'ss-roles-capabilities' ), array( 'status' => 404 ) );
		}

		$role = get_role( $new_role );
		if ( ! $role ) {
			return new WP_Error( 'ss_users_role_not_found', __( 'Role not found.', 'ss-roles-capabilities' ), array( 'status' => 404 ) );
		}

		$old_roles = $user->roles;
		$user->set_role( $new_role );

		// Log action.
		$actor_id = get_current_user_id();
		$this->log_audit_event(
			$actor_id,
			'user_role_changed',
			'user',
			$user_id,
			array(
				'old_roles' => $old_roles,
				'new_role'  => $new_role,
				'source'    => 'rest_api',
			)
		);

		return new WP_REST_Response(
			array(
				'user_id' => $user_id,
				'role'    => $new_role,
			)
		);
	}
}



