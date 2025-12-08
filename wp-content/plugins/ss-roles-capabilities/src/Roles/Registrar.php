<?php
/**
 * Role and capability registrar.
 *
 * @package SS_Roles_Capabilities
 */

namespace SS_Roles_Capabilities\Roles;

/**
 * Handles registration of SecureSoft roles and capabilities.
 */
class Registrar {

	/**
	 * SecureSoft roles.
	 */
	const ROLE_DISTRIBUTOR = 'ss_distributor';
	const ROLE_CUSTOMER    = 'ss_customer';
	const ROLE_MANAGER     = 'ss_manager';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_roles' ), 5 );
	}

	/**
	 * Register roles and capabilities.
	 *
	 * @return void
	 */
	public function register_roles() {
		// Ensure roles object exists.
		if ( ! function_exists( 'get_role' ) ) {
			return;
		}

		// Base capabilities controlled by this plugin.
		$core_caps = array(
			'ss_manage_roles'        => true,
			'ss_manage_capabilities' => true,
			'ss_view_roles_matrix'   => true,
			'ss_manage_policies'     => true,
			'ss_manage_webhooks'     => true,
			'ss_suspend_users'       => true,
		);

		/**
		 * Allow other plugins to extend the capabilities map.
		 *
		 * @param array $caps Core SecureSoft capabilities.
		 */
		$caps_map = apply_filters( 'ss/roles/capabilities_map', $core_caps );

		// Administrator gets everything.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( $caps_map as $cap => $grant ) {
				if ( $grant ) {
					$admin_role->add_cap( $cap );
				}
			}
		}

		// Distributor role.
		if ( ! get_role( self::ROLE_DISTRIBUTOR ) ) {
			add_role(
				self::ROLE_DISTRIBUTOR,
				__( 'SecureSoft Distributor', 'ss-roles-capabilities' ),
				array(
					'read'                => true,
					'ss_view_roles_matrix'=> true,
				)
			);
		}

		// Customer role.
		if ( ! get_role( self::ROLE_CUSTOMER ) ) {
			add_role(
				self::ROLE_CUSTOMER,
				__( 'SecureSoft Customer', 'ss-roles-capabilities' ),
				array(
					'read' => true,
				)
			);
		}

		// Manager role.
		if ( ! get_role( self::ROLE_MANAGER ) ) {
			add_role(
				self::ROLE_MANAGER,
				__( 'SecureSoft Manager', 'ss-roles-capabilities' ),
				array(
					'read'                   => true,
					'ss_manage_roles'        => true,
					'ss_manage_capabilities' => true,
					'ss_view_roles_matrix'   => true,
					'ss_manage_policies'     => true,
					'ss_manage_webhooks'     => true,
					'ss_suspend_users'       => true,
				)
			);
		}
	}

	/**
	 * Get capabilities map.
	 *
	 * @return array<string,bool>
	 */
	public function get_capabilities_map() {
		$core_caps = array(
			'ss_manage_roles'        => true,
			'ss_manage_capabilities' => true,
			'ss_view_roles_matrix'   => true,
			'ss_manage_policies'     => true,
			'ss_manage_webhooks'     => true,
			'ss_suspend_users'       => true,
		);

		/**
		 * Filter the capabilities map used across SecureSoft plugins.
		 *
		 * @param array $caps Core SecureSoft capabilities.
		 */
		return apply_filters( 'ss/roles/capabilities_map', $core_caps );
	}

	/**
	 * Get capabilities organized by category.
	 *
	 * @return array<string,array<string,bool>>
	 */
	public function get_capabilities_by_category() {
		$caps_map = $this->get_capabilities_map();

		// Default categorization for core capabilities.
		$categories = array(
			'core' => array(
				'ss_manage_roles'        => true,
				'ss_manage_capabilities' => true,
				'ss_view_roles_matrix'   => true,
				'ss_manage_policies'     => true,
				'ss_manage_webhooks'     => true,
				'ss_suspend_users'       => true,
			),
			'licenses' => array(),
			'pricing'  => array(),
			'integrations' => array(),
			'billing'  => array(),
		);

		// Categorize capabilities based on prefix or name.
		foreach ( $caps_map as $cap => $grant ) {
			$assigned = false;
			if ( strpos( $cap, 'ss_manage_licenses' ) !== false || strpos( $cap, 'ss_view_plain_codes' ) !== false || strpos( $cap, 'license' ) !== false ) {
				$categories['licenses'][ $cap ] = $grant;
				$assigned = true;
			} elseif ( strpos( $cap, 'pricing' ) !== false || strpos( $cap, 'price' ) !== false || strpos( $cap, 'wallet' ) !== false ) {
				$categories['pricing'][ $cap ] = $grant;
				$assigned = true;
			} elseif ( strpos( $cap, 'integration' ) !== false || strpos( $cap, 'api' ) !== false || strpos( $cap, 'webhook' ) !== false ) {
				$categories['integrations'][ $cap ] = $grant;
				$assigned = true;
			} elseif ( strpos( $cap, 'billing' ) !== false || strpos( $cap, 'invoice' ) !== false || strpos( $cap, 'payment' ) !== false ) {
				$categories['billing'][ $cap ] = $grant;
				$assigned = true;
			}

			// If not assigned to a specific category, put in core.
			if ( ! $assigned && ! isset( $categories['core'][ $cap ] ) ) {
				$categories['core'][ $cap ] = $grant;
			}
		}

		/**
		 * Filter capabilities by category.
		 *
		 * @param array $categories Capabilities organized by category.
		 */
		return apply_filters( 'ss/roles/capabilities_by_category', $categories );
	}

	/**
	 * Get default capabilities for roles (used for revert).
	 *
	 * @return array<string,array<string,bool>>
	 */
	public function get_default_capabilities() {
		$defaults = array(
			self::ROLE_MANAGER => array(
				'read'                   => true,
				'ss_manage_roles'        => true,
				'ss_manage_capabilities' => true,
				'ss_view_roles_matrix'   => true,
				'ss_manage_policies'     => true,
				'ss_manage_webhooks'     => true,
				'ss_suspend_users'       => true,
			),
			self::ROLE_DISTRIBUTOR => array(
				'read'                => true,
				'ss_view_roles_matrix'=> true,
			),
			self::ROLE_CUSTOMER => array(
				'read' => true,
			),
		);

		/**
		 * Filter default capabilities per role.
		 *
		 * @param array $defaults Default capabilities by role.
		 */
		return apply_filters( 'ss/roles/default_capabilities', $defaults );
	}
}