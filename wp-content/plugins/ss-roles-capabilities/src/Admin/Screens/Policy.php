<?php
/**
 * SecureSoft → Policy & Defaults admin screen.
 *
 * @package SS_Roles_Capabilities
 */

namespace SS_Roles_Capabilities\Admin\Screens;

use SS_Roles_Capabilities\Traits\AuditLogger;

/**
 * Policy and defaults admin screen.
 */
class Policy {

	use AuditLogger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register Policy & Defaults submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'ss-securesoft',
			__( 'Policy & Defaults', 'ss-roles-capabilities' ),
			__( 'Policy & Defaults', 'ss-roles-capabilities' ),
			'ss_manage_policies',
			'ss-securesoft-policy',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Option name for role transitions.
	 */
	const OPTION_TRANSITIONS = 'ss_roles_transitions';

	/**
	 * Render Policy & Defaults page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'ss_manage_policies' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ss-roles-capabilities' ) );
		}

		$default_role      = get_option( 'ss_roles_default_role', get_option( 'default_role', 'subscriber' ) );
		$approval_policy   = get_option( 'ss_roles_approval_policy', 'manual' );
		$transitions       = get_option( self::OPTION_TRANSITIONS, array() );
		$settings_action   = admin_url( 'admin.php?page=ss-securesoft-policy' );
		$all_roles         = wp_roles()->role_names;
		$allowed_policies  = array( 'manual', 'automatic' );

		if ( ! is_array( $transitions ) ) {
			$transitions = array();
		}

		// Initialize transitions if empty.
		if ( empty( $transitions ) ) {
			$transitions = $this->get_default_transitions();
		}

		// Handle form submission before rendering, so changes are reflected immediately.
		$this->maybe_handle_save( $allowed_policies );

		// Get filter for "From Role" if set.
		$filter_from_role = isset( $_GET['filter_from'] ) ? sanitize_key( wp_unslash( $_GET['filter_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Build all transitions array for pagination.
		$all_transitions = array();
		foreach ( $all_roles as $from_role_key => $from_role_label ) {
			// Skip if filter is set and doesn't match.
			if ( $filter_from_role && $filter_from_role !== $from_role_key ) {
				continue;
			}
			foreach ( $all_roles as $to_role_key => $to_role_label ) {
				if ( $from_role_key === $to_role_key ) {
					continue;
				}
				$transition_key = $from_role_key . '|' . $to_role_key;
				$transition      = isset( $transitions[ $transition_key ] ) ? $transitions[ $transition_key ] : array(
					'allowed'          => true,
					'requires_approval' => false,
				);
				$all_transitions[] = array(
					'from_key'         => $from_role_key,
					'from_label'       => $from_role_label,
					'to_key'           => $to_role_key,
					'to_label'         => $to_role_label,
					'transition_key'   => $transition_key,
					'transition'       => $transition,
				);
			}
		}

		// Pagination setup.
		$per_page     = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$total_items  = count( $all_transitions );
		$total_pages  = ceil( $total_items / $per_page );
		$offset       = ( $current_page - 1 ) * $per_page;
		$paged_items  = array_slice( $all_transitions, $offset, $per_page );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SecureSoft → Policy & Defaults', 'ss-roles-capabilities' ); ?></h1>
			<p><?php esc_html_e( 'Configure default roles, role-change approval policies, and allowed role transitions.', 'ss-roles-capabilities' ); ?></p>

			<form method="post" action="<?php echo esc_url( $settings_action ); ?>">
				<?php wp_nonce_field( 'ss_roles_save_policy', 'ss_roles_policy_nonce' ); ?>

				<h2><?php esc_html_e( 'Default Settings', 'ss-roles-capabilities' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="ss_roles_default_role"><?php esc_html_e( 'Default role for new registrations', 'ss-roles-capabilities' ); ?></label>
						</th>
						<td>
							<select name="ss_roles_default_role" id="ss_roles_default_role">
								<?php foreach ( $all_roles as $role_key => $role_label ) : ?>
									<option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $default_role, $role_key ); ?>>
										<?php echo esc_html( $role_label . ' (' . $role_key . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Role-change approval policy', 'ss-roles-capabilities' ); ?>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="ss_roles_approval_policy" value="manual" <?php checked( 'manual', $approval_policy ); ?> />
									<?php esc_html_e( 'Manual (requires admin approval)', 'ss-roles-capabilities' ); ?>
								</label>
								<br />
								<label>
									<input type="radio" name="ss_roles_approval_policy" value="automatic" <?php checked( 'automatic', $approval_policy ); ?> />
									<?php esc_html_e( 'Automatic (instant if user has permission)', 'ss-roles-capabilities' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Allowed Role Transitions', 'ss-roles-capabilities' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Configure which role transitions are allowed and whether they require approval.', 'ss-roles-capabilities' ); ?>
				</p>

				<div class="tablenav top">
					<div class="alignleft actions">
						<label for="filter-from-role" class="screen-reader-text">
							<?php esc_html_e( 'Filter by From Role', 'ss-roles-capabilities' ); ?>
						</label>
						<select name="filter_from" id="filter-from-role" onchange="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=ss-securesoft-policy' ) ); ?>&filter_from='+this.value+'&paged=1';">
							<option value=""><?php esc_html_e( 'All Roles', 'ss-roles-capabilities' ); ?></option>
							<?php foreach ( $all_roles as $role_key => $role_label ) : ?>
								<option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $filter_from_role, $role_key ); ?>>
									<?php echo esc_html( $role_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="button" onclick="ssSelectAllTransitions();">
							<?php esc_html_e( 'Select All', 'ss-roles-capabilities' ); ?>
						</button>
						<button type="button" class="button" onclick="ssSelectNoneTransitions();">
							<?php esc_html_e( 'Select None', 'ss-roles-capabilities' ); ?>
						</button>
					</div>
					<?php if ( $total_pages > 1 ) : ?>
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php
								printf(
									/* translators: %s: number of items */
									esc_html( _n( '%s item', '%s items', $total_items, 'ss-roles-capabilities' ) ),
									number_format_i18n( $total_items )
								);
								?>
							</span>
							<?php
							$pagination_base = admin_url( 'admin.php?page=ss-securesoft-policy' );
							if ( $filter_from_role ) {
								$pagination_base = add_query_arg( 'filter_from', $filter_from_role, $pagination_base );
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
					<table class="wp-list-table widefat fixed striped table-view-list">
						<thead>
							<tr>
								<th scope="col" class="manage-column column-from-role" style="width: 20%;">
									<?php esc_html_e( 'From Role', 'ss-roles-capabilities' ); ?>
								</th>
								<th scope="col" class="manage-column column-to-role" style="width: 20%;">
									<?php esc_html_e( 'To Role', 'ss-roles-capabilities' ); ?>
								</th>
								<th scope="col" class="manage-column column-allowed" style="width: 15%; text-align: center;">
									<?php esc_html_e( 'Allowed', 'ss-roles-capabilities' ); ?>
								</th>
								<th scope="col" class="manage-column column-approval" style="width: 15%; text-align: center;">
									<?php esc_html_e( 'Requires Approval', 'ss-roles-capabilities' ); ?>
								</th>
							</tr>
						</thead>
						<tbody id="the-list">
							<?php if ( ! empty( $paged_items ) ) : ?>
								<?php foreach ( $paged_items as $item ) : ?>
									<tr>
										<td class="column-from-role">
											<strong><?php echo esc_html( $item['from_label'] ); ?></strong>
											<div class="row-title">
												<code><?php echo esc_html( $item['from_key'] ); ?></code>
											</div>
										</td>
										<td class="column-to-role">
											<strong><?php echo esc_html( $item['to_label'] ); ?></strong>
											<div class="row-title">
												<code><?php echo esc_html( $item['to_key'] ); ?></code>
											</div>
										</td>
										<td class="column-allowed" style="text-align: center;">
											<input type="checkbox" 
												name="transitions[<?php echo esc_attr( $item['transition_key'] ); ?>][allowed]" 
												value="1" 
												class="transition-allowed"
												<?php checked( ! empty( $item['transition']['allowed'] ) ); ?> />
										</td>
										<td class="column-approval" style="text-align: center;">
											<input type="checkbox" 
												name="transitions[<?php echo esc_attr( $item['transition_key'] ); ?>][requires_approval]" 
												value="1" 
												class="transition-approval"
												<?php checked( ! empty( $item['transition']['requires_approval'] ) ); ?> />
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr class="no-items">
									<td colspan="4" class="colspanchange">
										<?php esc_html_e( 'No role transitions found.', 'ss-roles-capabilities' ); ?>
									</td>
								</tr>
							<?php endif; ?>
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
									esc_html( _n( '%s item', '%s items', $total_items, 'ss-roles-capabilities' ) ),
									number_format_i18n( $total_items )
								);
								?>
							</span>
							<?php
							$pagination_base = admin_url( 'admin.php?page=ss-securesoft-policy' );
							if ( $filter_from_role ) {
								$pagination_base = add_query_arg( 'filter_from', $filter_from_role, $pagination_base );
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
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Changes', 'ss-roles-capabilities' ); ?>
					</button>
				</p>
			</form>

			<script type="text/javascript">
			function ssSelectAllTransitions() {
				var checkboxes = document.querySelectorAll('#the-list .transition-allowed');
				checkboxes.forEach(function(cb) { cb.checked = true; });
			}
			function ssSelectNoneTransitions() {
				var checkboxes = document.querySelectorAll('#the-list .transition-allowed');
				checkboxes.forEach(function(cb) { cb.checked = false; });
			}
			</script>
		</div>
		<?php
	}

	/**
	 * Get default role transitions.
	 *
	 * @return array
	 */
	protected function get_default_transitions() {
		// Example: Distributor → Customer = not allowed, Customer → Distributor = requires approval.
		return array(
			'ss_distributor|ss_customer' => array(
				'allowed'          => false,
				'requires_approval' => false,
			),
			'ss_customer|ss_distributor' => array(
				'allowed'          => true,
				'requires_approval' => true,
			),
		);
	}

	/**
	 * Handle saving policy settings.
	 *
	 * @param string[] $allowed_policies Allowed policy values.
	 * @return void
	 */
	protected function maybe_handle_save( $allowed_policies ) {
		if ( ! isset( $_POST['ss_roles_policy_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ss_roles_policy_nonce'] ) ), 'ss_roles_save_policy' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( 'ss_manage_policies' ) ) {
			return;
		}

		$default_role = isset( $_POST['ss_roles_default_role'] ) ? sanitize_key( wp_unslash( $_POST['ss_roles_default_role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$policy       = isset( $_POST['ss_roles_approval_policy'] ) ? sanitize_key( wp_unslash( $_POST['ss_roles_approval_policy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $default_role ) {
			update_option( 'ss_roles_default_role', $default_role );
		}

		if ( in_array( $policy, $allowed_policies, true ) ) {
			update_option( 'ss_roles_approval_policy', $policy );
		}

		// Save role transitions.
		if ( isset( $_POST['transitions'] ) && is_array( $_POST['transitions'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw_transitions = wp_unslash( $_POST['transitions'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sanitized      = array();

			foreach ( $raw_transitions as $transition_key => $transition_data ) {
				$transition_key = sanitize_text_field( $transition_key );
				$sanitized[ $transition_key ] = array(
					'allowed'          => ! empty( $transition_data['allowed'] ),
					'requires_approval' => ! empty( $transition_data['requires_approval'] ),
				);
			}

			update_option( self::OPTION_TRANSITIONS, $sanitized );
		}

		// Log to audit log.
		$actor_id = get_current_user_id();
		$this->log_audit_event(
			$actor_id,
			'roles_policy_updated',
			'policy',
			null,
			array(
				'default_role'    => $default_role,
				'approval_policy' => $policy,
				'transitions_count' => count( $sanitized ),
			)
		);
	}
}



