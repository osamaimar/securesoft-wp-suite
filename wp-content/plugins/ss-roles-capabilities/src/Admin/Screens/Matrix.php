<?php
/**
 * SecureSoft → Capability Matrix admin screen.
 *
 * @package SS_Roles_Capabilities
 */

namespace SS_Roles_Capabilities\Admin\Screens;

use SS_Roles_Capabilities\Roles\Registrar;
use SS_Roles_Capabilities\Traits\AuditLogger;

/**
 * Capability matrix admin screen.
 */
class Matrix {

	use AuditLogger;

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

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register Capability Matrix submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'ss-securesoft',
			__( 'Capability Matrix', 'ss-roles-capabilities' ),
			__( 'Capability Matrix', 'ss-roles-capabilities' ),
			'ss_manage_capabilities',
			'ss-securesoft-matrix',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render Capability Matrix page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'ss_manage_capabilities' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ss-roles-capabilities' ) );
		}

		// Handle form submission before rendering, so changes are reflected immediately.
		$this->maybe_handle_save();
		$this->maybe_handle_revert();

		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = wp_roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$all_roles     = $wp_roles->roles;
		$selected_role = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Get all capabilities.
		$all_caps = $this->registrar->get_capabilities_map();
		$cap_keys = array_keys( $all_caps );

		// Pagination setup for capabilities.
		$per_page     = 10; // Show 10 capabilities per page.
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$total_items  = count( $cap_keys );
		$total_pages  = ceil( $total_items / $per_page );
		$offset       = ( $current_page - 1 ) * $per_page;
		$paged_caps   = array_slice( $cap_keys, $offset, $per_page );

		$action_url = admin_url( 'admin.php?page=ss-securesoft-matrix' );

		// Handle success messages.
		$messages = array();
		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages[] = __( 'Capabilities updated successfully.', 'ss-roles-capabilities' );
		}
		if ( isset( $_GET['reverted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages[] = __( 'Capabilities reverted to defaults successfully.', 'ss-roles-capabilities' );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SecureSoft → Capability Matrix', 'ss-roles-capabilities' ); ?></h1>
			<p><?php esc_html_e( 'Toggle capabilities per role.', 'ss-roles-capabilities' ); ?></p>

			<?php if ( ! empty( $messages ) ) : ?>
				<?php foreach ( $messages as $message ) : ?>
					<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
				<?php endforeach; ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $action_url ); ?>" id="capability-matrix-form">
				<?php wp_nonce_field( 'ss_roles_save_matrix', 'ss_roles_matrix_nonce' ); ?>

				<div class="tablenav top">
					<div class="alignleft actions">
						<button type="button" class="button" onclick="ssSelectAllCapabilities();">
							<?php esc_html_e( 'Select All', 'ss-roles-capabilities' ); ?>
						</button>
						<button type="button" class="button" onclick="ssSelectNoneCapabilities();">
							<?php esc_html_e( 'Select None', 'ss-roles-capabilities' ); ?>
						</button>
						<?php if ( current_user_can( 'ss_manage_roles' ) ) : ?>
							<button type="submit" name="action" value="revert" class="button" onclick="return confirm('<?php echo esc_js( __( 'This will revert all roles to their default capabilities. Continue?', 'ss-roles-capabilities' ) ); ?>');">
								<?php esc_html_e( 'Revert to Default', 'ss-roles-capabilities' ); ?>
							</button>
						<?php endif; ?>
					</div>
					<?php if ( $total_pages > 1 ) : ?>
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php
								printf(
									/* translators: %s: number of items */
									esc_html( _n( '%s capability', '%s capabilities', $total_items, 'ss-roles-capabilities' ) ),
									number_format_i18n( $total_items )
								);
								?>
							</span>
							<?php
							$pagination_base = $action_url;
							if ( $selected_role ) {
								$pagination_base = add_query_arg( 'role', $selected_role, $pagination_base );
							}
							$pagination_base = add_query_arg( 'paged', '%#%', $pagination_base );
							$page_links = paginate_links(
								array(
									'base'      => $pagination_base,
									'format'    => '',
									'prev_text' => __( '&laquo;' ),
									'next_text' => __( '&raquo;' ),
									'total'     => $total_pages,
									'current'   => $current_page,
									'type'      => 'plain',
								)
							);
							if ( $page_links ) {
								echo '<span class="pagination-links">' . $page_links . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</div>
					<?php endif; ?>
				</div>

				<div style="overflow-x: auto;">
					<table class="wp-list-table widefat fixed striped table-view-list" id="capability-matrix-table">
						<thead>
							<tr>
								<th scope="col" class="manage-column column-role" style="width: 200px;">
									<?php esc_html_e( 'Role', 'ss-roles-capabilities' ); ?>
								</th>
								<?php foreach ( $paged_caps as $cap_key ) : ?>
									<th scope="col" class="manage-column" style="text-align:center; min-width: 120px;">
										<?php echo esc_html( $cap_key ); ?>
									</th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $all_roles as $role_key => $role_data ) : ?>
								<?php if ( $selected_role && $selected_role !== $role_key ) : ?>
									<?php continue; ?>
								<?php endif; ?>
								<?php
								$is_administrator = ( 'administrator' === $role_key );
								?>
								<tr<?php echo $is_administrator ? ' class="administrator-row"' : ''; ?>>
									<td class="column-role">
										<strong><?php echo esc_html( $role_data['name'] ); ?></strong>
										<div class="row-title">
											<code><?php echo esc_html( $role_key ); ?></code>
										</div>
									</td>
									<?php foreach ( $paged_caps as $cap_key ) : ?>
										<?php
										// Administrator always has all capabilities.
										$has_cap = $is_administrator ? true : ! empty( $role_data['capabilities'][ $cap_key ] );
										$field   = 'caps[' . esc_attr( $role_key ) . '][' . esc_attr( $cap_key ) . ']';
										$field_id = 'cap_' . esc_attr( $role_key ) . '_' . esc_attr( $cap_key );
										?>
										<td style="text-align:center;">
											<?php if ( $is_administrator ) : ?>
												<input type="checkbox" 
													id="<?php echo esc_attr( $field_id ); ?>"
													checked="checked"
													disabled="disabled"
													class="capability-checkbox administrator-checkbox"
													title="<?php esc_attr_e( 'Administrator always has all capabilities', 'ss-roles-capabilities' ); ?>" />
												<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="1" />
											<?php else : ?>
												<input type="checkbox" 
													id="<?php echo esc_attr( $field_id ); ?>"
													name="<?php echo esc_attr( $field ); ?>" 
													value="1" 
													<?php checked( $has_cap ); ?>
													class="capability-checkbox" />
											<?php endif; ?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php
								printf(
									/* translators: %s: number of items */
									esc_html( _n( '%s capability', '%s capabilities', $total_items, 'ss-roles-capabilities' ) ),
									number_format_i18n( $total_items )
								);
								?>
							</span>
							<?php
							$pagination_base = $action_url;
							if ( $selected_role ) {
								$pagination_base = add_query_arg( 'role', $selected_role, $pagination_base );
							}
							$pagination_base = add_query_arg( 'paged', '%#%', $pagination_base );
							$page_links = paginate_links(
								array(
									'base'      => $pagination_base,
									'format'    => '',
									'prev_text' => __( '&laquo;' ),
									'next_text' => __( '&raquo;' ),
									'total'     => $total_pages,
									'current'   => $current_page,
									'type'      => 'plain',
								)
							);
							if ( $page_links ) {
								echo '<span class="pagination-links">' . $page_links . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</div>
					</div>
				<?php endif; ?>

				<p class="submit">
					<button type="submit" class="button button-primary" name="action" value="save">
						<?php esc_html_e( 'Save Changes', 'ss-roles-capabilities' ); ?>
					</button>
				</p>
			</form>
		</div>

		<style type="text/css">
		.administrator-row {
			background-color: #fff8e5;
		}
		.administrator-row td {
			opacity: 0.9;
		}
		.administrator-checkbox {
			cursor: not-allowed;
		}
		</style>
		<script type="text/javascript">
		function ssSelectAllCapabilities() {
			var checkboxes = document.querySelectorAll('#capability-matrix-table .capability-checkbox:not(.administrator-checkbox)');
			checkboxes.forEach(function(cb) { cb.checked = true; });
		}
		function ssSelectNoneCapabilities() {
			var checkboxes = document.querySelectorAll('#capability-matrix-table .capability-checkbox:not(.administrator-checkbox)');
			checkboxes.forEach(function(cb) { cb.checked = false; });
		}
		</script>
		<?php
	}

	/**
	 * Handle saving of capability matrix.
	 *
	 * @return void
	 */
	protected function maybe_handle_save() {
		if ( ! isset( $_POST['ss_roles_matrix_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ss_roles_matrix_nonce'] ) ), 'ss_roles_save_matrix' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( 'ss_manage_capabilities' ) ) {
			return;
		}

		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : 'save'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( 'save' !== $action ) {
			return;
		}

		// Get POST data - caps may be empty if no checkboxes are checked, which is valid.
		$caps = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? wp_unslash( $_POST['caps'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Get all capabilities that are displayed on the current page.
		// We need to replicate the pagination logic to know which capabilities to process.
		$selected_role = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$all_caps = $this->registrar->get_capabilities_map();
		$cap_keys = array_keys( $all_caps );

		// Get pagination info.
		$per_page = 10;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset = ( $current_page - 1 ) * $per_page;
		$paged_caps = array_slice( $cap_keys, $offset, $per_page );

		// Get all roles that should be displayed (same logic as render_page).
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = wp_roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$all_roles = $wp_roles->roles;
		$roles_to_update = array();

		// Process each role that is displayed on the page.
		foreach ( $all_roles as $role_key => $role_data ) {
			// Skip if filtering by specific role and this isn't it.
			if ( $selected_role && $selected_role !== $role_key ) {
				continue;
			}

			// Skip administrator - it cannot be modified.
			if ( 'administrator' === $role_key ) {
				continue;
			}

			$role = get_role( sanitize_key( $role_key ) );
			if ( ! $role ) {
				continue;
			}

			$role_updated = false;

			// For each capability on the current page, update it based on POST data.
			foreach ( $paged_caps as $cap_key ) {
				$cap_key = sanitize_key( $cap_key );
				// Only process capabilities that are in our capabilities map.
				if ( ! isset( $all_caps[ $cap_key ] ) ) {
					continue;
				}

				// Check if this capability is in the POST data for this role.
				// If checkbox is unchecked, it won't be in POST, so we need to remove it.
				$has_cap = isset( $caps[ $role_key ][ $cap_key ] ) && ! empty( $caps[ $role_key ][ $cap_key ] );

				// Get current state of the capability from the role object (not cached data).
				$current_has_cap = $role->has_cap( $cap_key );

				// Only update if the state has changed.
				if ( $has_cap !== $current_has_cap ) {
					if ( $has_cap ) {
						$role->add_cap( $cap_key );
					} else {
						$role->remove_cap( $cap_key );
					}
					$role_updated = true;
				}
			}

			if ( $role_updated ) {
				$roles_to_update[] = $role_key;
			}
		}

		// Update last updated timestamp for roles that were changed.
		if ( ! empty( $roles_to_update ) ) {
			$this->update_role_timestamps( $roles_to_update );

			// Log action.
			$actor_id = get_current_user_id();
			foreach ( $roles_to_update as $role_key ) {
				$this->log_audit_event(
					$actor_id,
					'capabilities_updated',
					'capability',
					null,
					array(
						'role' => $role_key,
						'capabilities_count' => count( $paged_caps ),
					)
				);
			}

			// Redirect to prevent duplicate submissions and show success message.
			$redirect_url = admin_url( 'admin.php?page=ss-securesoft-matrix' );
			if ( $selected_role ) {
				$redirect_url = add_query_arg( 'role', $selected_role, $redirect_url );
			}
			if ( $current_page > 1 ) {
				$redirect_url = add_query_arg( 'paged', $current_page, $redirect_url );
			}
			$redirect_url = add_query_arg( 'updated', '1', $redirect_url );

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Handle revert to defaults action.
	 *
	 * @return void
	 */
	protected function maybe_handle_revert() {
		if ( ! isset( $_POST['ss_roles_matrix_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ss_roles_matrix_nonce'] ) ), 'ss_roles_save_matrix' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( 'ss_manage_roles' ) ) {
			return;
		}

		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( 'revert' !== $action ) {
			return;
		}

		$defaults = $this->registrar->get_default_capabilities();
		$all_caps = $this->registrar->get_capabilities_map();
		$updated_roles = array();

		// Get all roles to revert.
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = wp_roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$all_roles = $wp_roles->roles;

		// Process all roles.
		foreach ( $all_roles as $role_key => $role_data ) {
			$role = get_role( $role_key );
			if ( ! $role ) {
				continue;
			}

			// Skip administrator - it cannot be modified and always has all capabilities.
			if ( 'administrator' === $role_key ) {
				// Ensure administrator has all capabilities (in case something went wrong).
				foreach ( $all_caps as $cap_key => $grant ) {
					if ( $grant && ! $role->has_cap( $cap_key ) ) {
						$role->add_cap( $cap_key );
					}
				}
				continue;
			}

			// Remove all SS capabilities first.
			foreach ( $all_caps as $cap_key => $default ) {
				$role->remove_cap( $cap_key );
			}

			// If role has defaults defined, apply them.
			if ( isset( $defaults[ $role_key ] ) ) {
				// Add default capabilities for this role.
				foreach ( $defaults[ $role_key ] as $cap_key => $grant ) {
					// Only add SecureSoft capabilities (skip 'read' and other WordPress caps).
					if ( $grant && isset( $all_caps[ $cap_key ] ) ) {
						$role->add_cap( $cap_key );
					}
				}
			}
			// For other roles (editor, author, etc.), they get no SecureSoft capabilities by default.

			$updated_roles[] = $role_key;
		}

		// Update last updated timestamp.
		$this->update_role_timestamps( $updated_roles );

		// Log action (single log entry for all roles).
		$actor_id = get_current_user_id();
		$this->log_audit_event(
			$actor_id,
			'capabilities_reverted',
			'capability',
			null,
			array(
				'roles' => $updated_roles,
				'roles_count' => count( $updated_roles ),
			)
		);

		// Redirect to prevent duplicate submissions and show success message.
		$redirect_url = admin_url( 'admin.php?page=ss-securesoft-matrix' );
		$selected_role = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $selected_role ) {
			$redirect_url = add_query_arg( 'role', $selected_role, $redirect_url );
		}
		if ( $current_page > 1 ) {
			$redirect_url = add_query_arg( 'paged', $current_page, $redirect_url );
		}
		$redirect_url = add_query_arg( 'reverted', '1', $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Update last updated timestamps for roles.
	 *
	 * @param array $role_keys Role keys.
	 * @return void
	 */
	protected function update_role_timestamps( $role_keys ) {
		$last_updated = get_option( 'ss_roles_last_updated', array() );
		if ( ! is_array( $last_updated ) ) {
			$last_updated = array();
		}

		$now = current_time( 'timestamp' );
		foreach ( $role_keys as $role_key ) {
			$last_updated[ $role_key ] = $now;
		}

		update_option( 'ss_roles_last_updated', $last_updated );
	}
}



