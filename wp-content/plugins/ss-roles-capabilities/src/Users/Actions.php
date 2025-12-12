<?php
/**
 * User-related actions: registration, role changes, suspension.
 *
 * @package SS_Roles_Capabilities
 */

namespace SS_Roles_Capabilities\Users;

use SS_Roles_Capabilities\Roles\Registrar;
use SS_Roles_Capabilities\Webhooks\Dispatcher;
use WP_Error;
use WP_User;

/**
 * Handles user workflows and events.
 */
class Actions {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'user_register', array( $this, 'handle_user_registered' ), 10, 1 );
		add_action( 'set_user_role', array( $this, 'handle_role_changed' ), 10, 3 );

		add_filter( 'authenticate', array( $this, 'block_suspended_user' ), 100, 3 );

		// Profile meta box.
		add_action( 'show_user_profile', array( $this, 'render_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );

		// Track last activity.
		add_action( 'wp_login', array( $this, 'update_last_activity' ), 10, 2 );
		add_action( 'wp', array( $this, 'maybe_update_last_activity' ) );
	}

	/**
	 * Handle user registration event.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function handle_user_registered( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			return;
		}

		$default_role = get_option( 'default_role', Registrar::ROLE_CUSTOMER );
		$default_role = apply_filters( 'ss/roles/default_role', $default_role );

		// Apply default role if not already set or if using subscriber.
		if ( empty( $user->roles ) || in_array( 'subscriber', $user->roles, true ) ) {
			$user->set_role( $default_role );
		}

		$context = array(
			'source' => 'wordpress_registration',
		);

		// Log to audit log.
		$this->log_audit_event(
			$user_id,
			'user_registered',
			$user_id,
			array(
				'role'    => $default_role,
				'context' => $context,
			)
		);

		/**
		 * Fire SecureSoft user registered event.
		 */
		do_action( 'ss/user/registered', $user_id, $default_role, $context );

		// Dispatch webhook.
		$dispatcher = new Dispatcher();
		$dispatcher->dispatch(
			'user_registered',
			array(
				'user_id'      => $user_id,
				'role'         => $default_role,
				'context'      => $context,
				'registered_at'=> current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Handle role change events.
	 *
	 * @param int      $user_id   User ID.
	 * @param string   $role      New role.
	 * @param string[] $old_roles Old roles.
	 * @return void
	 */
	public function handle_role_changed( $user_id, $role, $old_roles ) {
		// Get actor user ID (who made the change).
		$actor_id = get_current_user_id();
		if ( ! $actor_id ) {
			// If no current user, use system (0) or the user themselves if self-registration.
			$actor_id = $user_id;
		}

		// Log to audit log.
		$this->log_audit_event(
			$actor_id,
			'user_role_changed',
			$user_id,
			array(
				'old_roles' => $old_roles,
				'new_role'  => $role,
			)
		);

		/**
		 * Fire SecureSoft role changed event.
		 */
		do_action( 'ss/user/role_changed', $user_id, $old_roles, $role );

		// Dispatch alert through SecureSoft Alerts (if available).
		do_action(
			'ss/alert/send',
			array(
				'to'      => 'admin',
				'type'    => 'user_role_changed',
				'channel' => array( 'email', 'telegram', 'whatsapp' ),
				'title'   => __( 'User role updated', 'ss-roles-capabilities' ),
				'body'    => sprintf(
					/* translators: 1: user ID, 2: old roles, 3: new role */
					__( 'User #%1$d changed from %2$s to %3$s', 'ss-roles-capabilities' ),
					$user_id,
					implode( ',', (array) $old_roles ),
					$role
				),
				'meta'    => array(
					'user_id'   => $user_id,
					'old_roles' => $old_roles,
					'new_role'  => $role,
				),
			)
		);

		// Dispatch webhook.
		$dispatcher = new Dispatcher();
		$dispatcher->dispatch(
			'user_role_changed',
			array(
				'user_id'   => $user_id,
				'old_roles' => $old_roles,
				'new_role'  => $role,
				'changed_at'=> current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Block suspended users from authenticating.
	 *
	 * @param WP_User|WP_Error|null $user     User or error.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error|null
	 */
	public function block_suspended_user( $user, $username, $password ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( $user instanceof WP_User ) {
			$is_suspended = get_user_meta( $user->ID, 'ss_suspended', true );
			if ( (int) $is_suspended === 1 ) {
				return new WP_Error(
					'ss_user_suspended',
					__( 'Your account is suspended. Please contact support.', 'ss-roles-capabilities' )
				);
			}
		}

		return $user;
	}

	/**
	 * Render profile fields for suspension status.
	 *
	 * @param WP_User $user User object.
	 * @return void
	 */
	public function render_profile_fields( $user ) {
		if ( ! current_user_can( 'ss_suspend_users' ) ) {
			return;
		}

		$is_suspended = (int) get_user_meta( $user->ID, 'ss_suspended', true );
		$last_activity = get_user_meta( $user->ID, 'ss_last_activity', true );
		$user_roles    = $user->roles;
		$status_label  = $is_suspended ? __( 'Suspended', 'ss-roles-capabilities' ) : __( 'Active', 'ss-roles-capabilities' );
		$status_class  = $is_suspended ? 'error' : 'success';

		// Get role display names.
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = wp_roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$role_names = array();
		foreach ( $user_roles as $role_key ) {
			if ( isset( $wp_roles->role_names[ $role_key ] ) ) {
				$role_names[] = $wp_roles->role_names[ $role_key ];
			} else {
				$role_names[] = $role_key;
			}
		}

		$base_url = admin_url( 'user-edit.php?user_id=' . $user->ID );
		?>
		<h2><?php esc_html_e( 'SecureSoft User Status', 'ss-roles-capabilities' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Role(s)', 'ss-roles-capabilities' ); ?></th>
				<td>
					<?php if ( ! empty( $role_names ) ) : ?>
						<?php foreach ( $role_names as $role_name ) : ?>
							<span class="ss-role-badge" style="display:inline-block; padding: 4px 8px; margin: 2px; background: #2271b1; color: #fff; border-radius: 3px;">
								<?php echo esc_html( $role_name ); ?>
							</span>
						<?php endforeach; ?>
					<?php else : ?>
						<span class="description"><?php esc_html_e( 'No roles assigned', 'ss-roles-capabilities' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Status', 'ss-roles-capabilities' ); ?></th>
				<td>
					<span class="ss-status-badge" style="padding: 4px 8px; background: <?php echo $is_suspended ? '#d63638' : '#00a32a'; ?>; color: #fff; border-radius: 3px;">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Last Activity', 'ss-roles-capabilities' ); ?></th>
				<td>
					<?php if ( $last_activity ) : ?>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_activity ) ) ); ?>
					<?php else : ?>
						<span class="description"><?php esc_html_e( 'Never', 'ss-roles-capabilities' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<div style="margin: 20px 0; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Quick Actions', 'ss-roles-capabilities' ); ?></h3>
			<p>
				<?php if ( $is_suspended ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( $base_url . '&ss_action=reactivate', 'ss_reactivate_user_' . $user->ID ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Reactivate User', 'ss-roles-capabilities' ); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( wp_nonce_url( $base_url . '&ss_action=suspend', 'ss_suspend_user_' . $user->ID ) ); ?>" class="button" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to suspend this user?', 'ss-roles-capabilities' ) ); ?>');">
						<?php esc_html_e( 'Suspend User', 'ss-roles-capabilities' ); ?>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( wp_nonce_url( $base_url . '&ss_action=force_logout', 'ss_force_logout_' . $user->ID ) ); ?>" class="button" onclick="return confirm('<?php echo esc_js( __( 'This will invalidate all sessions for this user. Continue?', 'ss-roles-capabilities' ) ); ?>');">
					<?php esc_html_e( 'Force Logout', 'ss-roles-capabilities' ); ?>
				</a>
			</p>
		</div>

		<?php wp_nonce_field( 'ss_roles_save_user_status_' . $user->ID, 'ss_roles_user_status_nonce' ); ?>

		<?php
		// Handle quick actions.
		$this->handle_quick_actions( $user->ID );
	}

	/**
	 * Handle quick actions (suspend, reactivate, force logout).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	protected function handle_quick_actions( $user_id ) {
		if ( ! isset( $_GET['ss_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['ss_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		switch ( $action ) {
			case 'suspend':
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ss_suspend_user_' . $user_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$actor_id = get_current_user_id();
					update_user_meta( $user_id, 'ss_suspended', 1 );
					$this->force_logout_user( $user_id );

					// Log to audit log.
					$this->log_audit_event(
						$actor_id,
						'user_suspended',
						$user_id,
						array(
							'reason' => 'manual_admin_action',
						)
					);

					$dispatcher = new Dispatcher();
					do_action( 'ss/user/suspended', $user_id, 'manual_admin_action' );
					$dispatcher->dispatch(
						'user_suspended',
						array(
							'user_id'      => $user_id,
							'reason'       => 'manual_admin_action',
							'suspended_at' => current_time( 'mysql' ),
						)
					);

					wp_safe_redirect( admin_url( 'user-edit.php?user_id=' . $user_id . '&ss_suspended=1' ) );
					exit;
				}
				break;

			case 'reactivate':
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ss_reactivate_user_' . $user_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$actor_id = get_current_user_id();
					update_user_meta( $user_id, 'ss_suspended', 0 );

					// Log to audit log.
					$this->log_audit_event(
						$actor_id,
						'user_reactivated',
						$user_id,
						array()
					);

					$dispatcher = new Dispatcher();
					do_action( 'ss/user/reactivated', $user_id );
					$dispatcher->dispatch(
						'user_reactivated',
						array(
							'user_id'        => $user_id,
							'reactivated_at' => current_time( 'mysql' ),
						)
					);

					wp_safe_redirect( admin_url( 'user-edit.php?user_id=' . $user_id . '&ss_reactivated=1' ) );
					exit;
				}
				break;

			case 'force_logout':
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ss_force_logout_' . $user_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$actor_id = get_current_user_id();
					$this->force_logout_user( $user_id, $actor_id );
					wp_safe_redirect( admin_url( 'user-edit.php?user_id=' . $user_id . '&ss_logged_out=1' ) );
					exit;
				}
				break;
		}
	}

	/**
	 * Force logout user by invalidating all sessions.
	 *
	 * @param int      $user_id User ID.
	 * @param int|null $actor_id Actor user ID (who performed the action). Defaults to current user.
	 * @return void
	 */
	protected function force_logout_user( $user_id, $actor_id = null ) {
		// Invalidate all sessions by updating user meta.
		update_user_meta( $user_id, 'session_tokens', array() );

		// If using WP Session Manager or similar, destroy sessions.
		if ( function_exists( 'wp_destroy_all_sessions' ) ) {
			wp_destroy_all_sessions();
		}

		// Get actor ID if not provided.
		if ( null === $actor_id ) {
			$actor_id = get_current_user_id();
		}

		// Log to audit log.
		$this->log_audit_event(
			$actor_id,
			'user_force_logged_out',
			$user_id,
			array()
		);
	}

	/**
	 * Update last activity timestamp.
	 *
	 * @param string  $user_login User login.
	 * @param WP_User $user       User object.
	 * @return void
	 */
	public function update_last_activity( $user_login, $user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( $user instanceof WP_User ) {
			update_user_meta( $user->ID, 'ss_last_activity', current_time( 'mysql' ) );
		}
	}

	/**
	 * Maybe update last activity on page load (throttled).
	 *
	 * @return void
	 */
	public function maybe_update_last_activity() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$last_update = get_user_meta( $user_id, 'ss_last_activity', true );

		// Only update if last update was more than 5 minutes ago.
		if ( ! $last_update || ( time() - strtotime( $last_update ) ) > 300 ) {
			update_user_meta( $user_id, 'ss_last_activity', current_time( 'mysql' ) );
		}
	}

	/**
	 * Save profile fields (suspension status).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function save_profile_fields( $user_id ) {
		if ( ! current_user_can( 'ss_suspend_users' ) ) {
			return;
		}

		if ( ! isset( $_POST['ss_roles_user_status_nonce'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Missing
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ss_roles_user_status_nonce'] ) ), 'ss_roles_save_user_status_' . $user_id ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		) {
			return;
		}

		$is_suspended = isset( $_POST['ss_suspended'] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$actor_id = get_current_user_id();

		update_user_meta( $user_id, 'ss_suspended', $is_suspended );

		$dispatcher = new Dispatcher();

		if ( 1 === $is_suspended ) {
			// Log to audit log.
			$this->log_audit_event(
				$actor_id,
				'user_suspended',
				$user_id,
				array(
					'reason' => 'manual_admin_action',
				)
			);

			do_action( 'ss/user/suspended', $user_id, 'manual_admin_action' );

			$dispatcher->dispatch(
				'user_suspended',
				array(
					'user_id'     => $user_id,
					'reason'      => 'manual_admin_action',
					'suspended_at'=> current_time( 'mysql' ),
				)
			);
		} else {
			// Log to audit log.
			$this->log_audit_event(
				$actor_id,
				'user_reactivated',
				$user_id,
				array()
			);

			do_action( 'ss/user/reactivated', $user_id );

			$dispatcher->dispatch(
				'user_reactivated',
				array(
					'user_id'       => $user_id,
					'reactivated_at'=> current_time( 'mysql' ),
				)
			);
		}
	}

	/**
	 * Get Core plugin audit logger instance.
	 *
	 * @return \SS_Core_Licenses\Audit\Logger|null Logger instance or null if Core is not available.
	 */
	protected function get_audit_logger() {
		if ( ! class_exists( '\SS_Core_Licenses\Plugin' ) ) {
			return null;
		}

		$core_plugin = \SS_Core_Licenses\Plugin::instance();
		if ( ! $core_plugin || ! isset( $core_plugin->audit_logger ) ) {
			return null;
		}

		return $core_plugin->audit_logger;
	}

	/**
	 * Log an audit event via Core plugin.
	 *
	 * @param int    $actor_id   Actor user ID.
	 * @param string $action     Action performed.
	 * @param int    $entity_id  Entity ID (user ID in this case).
	 * @param array  $meta       Additional metadata.
	 * @return void
	 */
	protected function log_audit_event( $actor_id, $action, $entity_id, $meta = array() ) {
		$logger = $this->get_audit_logger();
		if ( ! $logger ) {
			// Core plugin not available, skip logging.
			return;
		}

		$logger->log(
			$actor_id,
			$action,
			'user',
			$entity_id,
			$meta
		);
	}
}

