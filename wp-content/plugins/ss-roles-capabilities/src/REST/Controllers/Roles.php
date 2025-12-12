<?php
/**
 * REST controller for roles.
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

/**
 * Roles REST controller.
 */
class Roles extends WP_REST_Controller {

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
	protected $rest_base = 'roles';

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
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_roles' ),
					'permission_callback' => array( $this, 'can_view_roles' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_role' ),
					'permission_callback' => array( $this, 'can_manage_roles' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<role>[a-zA-Z0-9_\-]+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_role' ),
					'permission_callback' => array( $this, 'can_manage_capabilities' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_role' ),
					'permission_callback' => array( $this, 'can_manage_roles' ),
				),
			)
		);
	}

	/**
	 * Permission: view roles matrix.
	 *
	 * @return bool
	 */
	public function can_view_roles() {
		return current_user_can( 'ss_view_roles_matrix' );
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
	 * Permission: manage capabilities.
	 *
	 * @return bool
	 */
	public function can_manage_capabilities() {
		return current_user_can( 'ss_manage_capabilities' );
	}

	/**
	 * GET /roles
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_roles( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = wp_roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$data = array();
		foreach ( $wp_roles->roles as $key => $role ) {
			$data[ $key ] = array(
				'name'         => $role['name'],
				'capabilities' => array_keys( array_filter( $role['capabilities'] ) ),
			);
		}

		return new WP_REST_Response( $data );
	}

	/**
	 * POST /roles
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_role( WP_REST_Request $request ) {
		$role       = sanitize_key( $request->get_param( 'role' ) );
		$name       = sanitize_text_field( $request->get_param( 'name' ) );
		$caps       = (array) $request->get_param( 'capabilities' );
		$caps_clean = array();

		if ( empty( $role ) || empty( $name ) ) {
			return new WP_Error( 'ss_roles_invalid_params', __( 'Role slug and name are required.', 'ss-roles-capabilities' ), array( 'status' => 400 ) );
		}

		foreach ( $caps as $cap ) {
			$cap                = sanitize_key( $cap );
			$caps_clean[ $cap ] = true;
		}

		if ( get_role( $role ) ) {
			return new WP_Error( 'ss_roles_exists', __( 'Role already exists.', 'ss-roles-capabilities' ), array( 'status' => 409 ) );
		}

		add_role( $role, $name, $caps_clean );

		// Log action.
		$actor_id = get_current_user_id();
		$this->log_audit_event(
			$actor_id,
			'role_created',
			'role',
			$role,
			array(
				'role' => $role,
				'name' => $name,
				'capabilities' => array_keys( $caps_clean ),
				'source' => 'rest_api',
			)
		);

		return $this->get_roles( $request );
	}

	/**
	 * PUT /roles/{role}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_role( WP_REST_Request $request ) {
		$role_key = sanitize_key( $request['role'] );
		$caps     = (array) $request->get_param( 'capabilities' );

		$role = get_role( $role_key );
		if ( ! $role ) {
			return new WP_Error( 'ss_roles_not_found', __( 'Role not found.', 'ss-roles-capabilities' ), array( 'status' => 404 ) );
		}

		// Normalize capabilities.
		$new_caps = array();
		foreach ( $caps as $cap ) {
			$cap               = sanitize_key( $cap );
			$new_caps[ $cap ]  = true;
		}

		// Reset capabilities for known map, then re-add.
		foreach ( $this->registrar->get_capabilities_map() as $cap_key => $default ) {
			$role->remove_cap( $cap_key );
		}

		foreach ( $new_caps as $cap_key => $grant ) {
			if ( $grant ) {
				$role->add_cap( $cap_key );
			}
		}

		// Log action.
		$actor_id = get_current_user_id();
		$this->log_audit_event(
			$actor_id,
			'role_capabilities_updated',
			'capability',
			null,
			array(
				'role' => $role_key,
				'capabilities' => array_keys( $new_caps ),
				'source' => 'rest_api',
			)
		);

		return $this->get_roles( $request );
	}

	/**
	 * DELETE /roles/{role}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_role( WP_REST_Request $request ) {
		$role_key = sanitize_key( $request['role'] );

		if ( in_array( $role_key, array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ), true ) ) {
			return new WP_Error( 'ss_roles_core_role', __( 'Cannot delete core WordPress roles.', 'ss-roles-capabilities' ), array( 'status' => 400 ) );
		}

		remove_role( $role_key );

		// Log action.
		$actor_id = get_current_user_id();
		$this->log_audit_event(
			$actor_id,
			'role_deleted',
			'role',
			$role_key,
			array(
				'role' => $role_key,
				'source' => 'rest_api',
			)
		);

		return $this->get_roles( $request );
	}
}



