<?php
/**
 * SecureSoft → Roles admin screen.
 *
 * @package SS_Roles_Capabilities
 */

namespace SS_Roles_Capabilities\Admin\Screens;

use SS_Roles_Capabilities\Roles\Registrar;

/**
 * Roles list admin screen.
 */
class Roles {

	/**
	 * Roles registrar.
	 *
	 * @var Registrar
	 */
	protected $registrar;

	/**
	 * Option name for storing role last updated timestamps.
	 */
	const OPTION_LAST_UPDATED = 'ss_roles_last_updated';

	/**
	 * Constructor.
	 *
	 * @param Registrar $registrar Roles registrar.
	 */
	public function __construct( Registrar $registrar ) {
		$this->registrar = $registrar;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Register main SecureSoft menu and Roles page.
	 *
	 * @return void
	 */
	public function register_menu() {
		// Main SecureSoft menu (if not already created by Core, this will create it).
		add_menu_page(
			__( 'SecureSoft', 'ss-roles-capabilities' ),
			__( 'SecureSoft', 'ss-roles-capabilities' ),
			'ss_view_roles_matrix',
			'ss-securesoft',
			array( $this, 'render_page' ),
			'dashicons-shield-alt',
			56
		);

		// Roles submenu.
		add_submenu_page(
			'ss-securesoft',
			__( 'Roles', 'ss-roles-capabilities' ),
			__( 'Roles', 'ss-roles-capabilities' ),
			'ss_view_roles_matrix',
			'ss-securesoft',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_ss-securesoft' !== $screen->id ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Handle form actions (delete, duplicate, export, import, sync).
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || 'ss-securesoft' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Check both GET and POST for action parameter.
		$action = '';
		if ( isset( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_POST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$action = sanitize_text_field( wp_unslash( $_POST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		switch ( $action ) {
			case 'add':
				$this->handle_add_role();
				break;
			case 'delete':
				$this->handle_delete_role();
				break;
			case 'duplicate':
				$this->handle_duplicate_role();
				break;
			case 'export':
				$this->handle_export_roles();
				break;
			case 'export_defaults':
				$this->handle_export_default_roles();
				break;
			case 'import':
				$this->handle_import_roles();
				break;
		}
	}

	/**
	 * Handle add new role action.
	 *
	 * @return void
	 */
	protected function handle_add_role() {
		if ( ! current_user_can( 'ss_manage_roles' ) ) {
			wp_die( esc_html__( 'You do not have permission to add roles.', 'ss-roles-capabilities' ) );
		}

		if ( ! isset( $_POST['ss_roles_add_nonce'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Missing
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ss_roles_add_nonce'] ) ), 'add_role' ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		) {
			return;
		}

		$role_key = isset( $_POST['role_key'] ) ? sanitize_key( wp_unslash( $_POST['role_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$role_name = isset( $_POST['role_name'] ) ? sanitize_text_field( wp_unslash( $_POST['role_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $role_key ) || empty( $role_name ) ) {
			wp_die( esc_html__( 'Role slug and name are required.', 'ss-roles-capabilities' ) );
		}

		// Check if role already exists.
		if ( get_role( $role_key ) ) {
			wp_die( esc_html__( 'Role already exists.', 'ss-roles-capabilities' ) );
		}

		// Prevent creating core roles.
		$core_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		if ( in_array( $role_key, $core_roles, true ) ) {
			wp_die( esc_html__( 'Cannot create core WordPress roles.', 'ss-roles-capabilities' ) );
		}

		// Create role with basic 'read' capability.
		$caps = array( 'read' => true );
		add_role( $role_key, $role_name, $caps );

		// Update last updated timestamp.
		$last_updated = get_option( self::OPTION_LAST_UPDATED, array() );
		if ( ! is_array( $last_updated ) ) {
			$last_updated = array();
		}
		$last_updated[ $role_key ] = current_time( 'timestamp' );
		update_option( self::OPTION_LAST_UPDATED, $last_updated );

		// Log action.
		do_action(
			'ss/audit/log',
			'role_created',
			array(
				'role' => $role_key,
				'name' => $role_name,
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=ss-securesoft&added=1' ) );
		exit;
	}

	/**
	 * Handle delete role action.
	 *
	 * @return void
	 */
	protected function handle_delete_role() {
		if ( ! current_user_can( 'ss_manage_roles' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete roles.', 'ss-roles-capabilities' ) );
		}

		$role = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $role ) {
			return;
		}

		// Check nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_role_' . $role ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Prevent deletion of core roles.
		$core_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		if ( in_array( $role, $core_roles, true ) ) {
			wp_die( esc_html__( 'Cannot delete core WordPress roles.', 'ss-roles-capabilities' ) );
		}

		// Check if role has users.
		$users = get_users( array( 'role' => $role ) );
		if ( ! empty( $users ) ) {
			wp_die(
				sprintf(
					/* translators: 1: role name, 2: user count */
					esc_html__( 'Cannot delete role "%1$s" because it has %2$d user(s). Please reassign users first.', 'ss-roles-capabilities' ),
					esc_html( $role ),
					count( $users )
				)
			);
		}

		remove_role( $role );

		// Log action.
		do_action(
			'ss/audit/log',
			'role_deleted',
			array(
				'role' => $role,
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=ss-securesoft&deleted=1' ) );
		exit;
	}

	/**
	 * Handle duplicate role action.
	 *
	 * @return void
	 */
	protected function handle_duplicate_role() {
		if ( ! current_user_can( 'ss_manage_roles' ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate roles.', 'ss-roles-capabilities' ) );
		}

		$role = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $role ) {
			return;
		}

		// Check nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'duplicate_role_' . $role ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$source_role = get_role( $role );
		if ( ! $source_role ) {
			return;
		}

		// Generate new role key.
		$new_role_key = $role . '_copy';
		$counter      = 1;
		while ( get_role( $new_role_key ) ) {
			$new_role_key = $role . '_copy_' . $counter;
			++$counter;
		}

		// Get role data.
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = wp_roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$role_data = $wp_roles->roles[ $role ];
		$new_name  = $role_data['name'] . ' (Copy)';

		// Create new role with same capabilities.
		add_role( $new_role_key, $new_name, $role_data['capabilities'] );

		// Log action.
		do_action(
			'ss/audit/log',
			'role_duplicated',
			array(
				'source_role' => $role,
				'new_role'    => $new_role_key,
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=ss-securesoft&duplicated=1' ) );
		exit;
	}

	/**
	 * Handle export roles action.
	 *
	 * @return void
	 */
	protected function handle_export_roles() {
		if ( ! current_user_can( 'ss_manage_roles' ) ) {
			wp_die( esc_html__( 'You do not have permission to export roles.', 'ss-roles-capabilities' ) );
		}

		// Check nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'export_roles' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = wp_roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$export_data = array();
		foreach ( $wp_roles->roles as $role_key => $role_data ) {
			$export_data[ $role_key ] = array(
				'name'         => $role_data['name'],
				'capabilities' => array_keys( array_filter( $role_data['capabilities'] ) ),
			);
		}

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="ss-roles-export-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Handle import roles action.
	 *
	 * @return void
	 */
	protected function handle_import_roles() {
		if ( ! current_user_can( 'ss_manage_roles' ) ) {
			wp_die( esc_html__( 'You do not have permission to import roles.', 'ss-roles-capabilities' ) );
		}

		if ( ! isset( $_POST['ss_roles_import_nonce'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Missing
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ss_roles_import_nonce'] ) ), 'import_roles' ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		) {
			return;
		}

		if ( ! isset( $_FILES['roles_file'] ) || UPLOAD_ERR_OK !== $_FILES['roles_file']['error'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$file_content = file_get_contents( $_FILES['roles_file']['tmp_name'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.WP.AlternativeFunctions.file_get_contents
		$import_data  = json_decode( $file_content, true );

		if ( ! is_array( $import_data ) ) {
			wp_die( esc_html__( 'Invalid JSON file.', 'ss-roles-capabilities' ) );
		}

		$imported = 0;
		foreach ( $import_data as $role_key => $role_data ) {
			$role_key = sanitize_key( $role_key );
			if ( empty( $role_key ) || empty( $role_data['name'] ) ) {
				continue;
			}

			$caps = array();
			if ( isset( $role_data['capabilities'] ) && is_array( $role_data['capabilities'] ) ) {
				foreach ( $role_data['capabilities'] as $cap ) {
					$caps[ sanitize_key( $cap ) ] = true;
				}
			}

			// Skip core roles.
			$core_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
			if ( in_array( $role_key, $core_roles, true ) ) {
				continue;
			}

			// Remove existing role if it exists (non-core).
			if ( get_role( $role_key ) ) {
				remove_role( $role_key );
			}

			add_role( $role_key, sanitize_text_field( $role_data['name'] ), $caps );
			++$imported;
		}

		// Log action.
		do_action(
			'ss/audit/log',
			'roles_imported',
			array(
				'count' => $imported,
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=ss-securesoft&imported=' . $imported ) );
		exit;
	}

	/**
	 * Handle export default roles action.
	 *
	 * @return void
	 */
	protected function handle_export_default_roles() {
		if ( ! current_user_can( 'ss_manage_roles' ) ) {
			wp_die( esc_html__( 'You do not have permission to export default roles.', 'ss-roles-capabilities' ) );
		}

		// Check nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'export_default_roles' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Get default capabilities for SecureSoft roles.
		$defaults = $this->registrar->get_default_capabilities();

		// Format for export (convert capabilities array to list format like export_roles).
		$export_data = array();
		foreach ( $defaults as $role_key => $role_caps ) {
			// Get role display name.
			$role_names = array(
				Registrar::ROLE_DISTRIBUTOR => __( 'SecureSoft Distributor', 'ss-roles-capabilities' ),
				Registrar::ROLE_CUSTOMER    => __( 'SecureSoft Customer', 'ss-roles-capabilities' ),
				Registrar::ROLE_MANAGER     => __( 'SecureSoft Manager', 'ss-roles-capabilities' ),
			);
			$role_name = isset( $role_names[ $role_key ] ) ? $role_names[ $role_key ] : $role_key;

			$export_data[ $role_key ] = array(
				'name'         => $role_name,
				'capabilities' => array_keys( array_filter( $role_caps ) ),
			);
		}

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="ss-default-roles-export-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Get user count for a role.
	 *
	 * @param string $role Role key.
	 * @return int
	 */
	protected function get_user_count( $role ) {
		$users = count_users();
		return isset( $users['avail_roles'][ $role ] ) ? (int) $users['avail_roles'][ $role ] : 0;
	}

	/**
	 * Get last updated timestamp for a role.
	 *
	 * @param string $role Role key.
	 * @return string
	 */
	protected function get_last_updated( $role ) {
		$last_updated = get_option( self::OPTION_LAST_UPDATED, array() );
		if ( ! is_array( $last_updated ) ) {
			$last_updated = array();
		}

		if ( isset( $last_updated[ $role ] ) ) {
			return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_updated[ $role ] );
		}

		return __( 'Never', 'ss-roles-capabilities' );
	}

	/**
	 * Get key capabilities for a role (limited to 3).
	 *
	 * @param array $capabilities Role capabilities.
	 * @return array
	 */
	protected function get_key_capabilities( $capabilities ) {
		$enabled_caps = array_keys( array_filter( $capabilities ) );
		$ss_caps      = array_intersect( $enabled_caps, array_keys( $this->registrar->get_capabilities_map() ) );
		return array_slice( $ss_caps, 0, 3 );
	}

	/**
	 * Render Roles admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'ss_view_roles_matrix' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ss-roles-capabilities' ) );
		}

		// Handle success messages.
		$messages = array();
		if ( isset( $_GET['added'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages[] = __( 'Role created successfully.', 'ss-roles-capabilities' );
		}
		if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages[] = __( 'Role deleted successfully.', 'ss-roles-capabilities' );
		}
		if ( isset( $_GET['duplicated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages[] = __( 'Role duplicated successfully.', 'ss-roles-capabilities' );
		}
		if ( isset( $_GET['imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$imported = (int) $_GET['imported']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages[] = sprintf(
				/* translators: %d: number of roles imported */
				_n( '%d role imported successfully.', '%d roles imported successfully.', $imported, 'ss-roles-capabilities' ),
				$imported
			);
		}

		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = wp_roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$all_roles = $wp_roles->roles;
		$base_url  = admin_url( 'admin.php?page=ss-securesoft' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SecureSoft → Roles', 'ss-roles-capabilities' ); ?></h1>

			<?php if ( ! empty( $messages ) ) : ?>
				<?php foreach ( $messages as $message ) : ?>
					<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
				<?php endforeach; ?>
			<?php endif; ?>

			<div class="tablenav top">
				<div class="alignleft actions">
					<?php if ( current_user_can( 'ss_manage_roles' ) ) : ?>
						<button type="button" class="button" onclick="document.getElementById('add-role-form').style.display='block';">
							<?php esc_html_e( 'Add New Role', 'ss-roles-capabilities' ); ?>
						</button>
						<a href="<?php echo esc_url( wp_nonce_url( $base_url . '&action=export', 'export_roles' ) ); ?>" class="button">
							<?php esc_html_e( 'Export Roles (JSON)', 'ss-roles-capabilities' ); ?>
						</a>
						<a href="<?php echo esc_url( wp_nonce_url( $base_url . '&action=export_defaults', 'export_default_roles' ) ); ?>" class="button">
							<?php esc_html_e( 'Export Default Roles (JSON)', 'ss-roles-capabilities' ); ?>
						</a>
						<button type="button" class="button" onclick="document.getElementById('import-form').style.display='block';">
							<?php esc_html_e( 'Import Roles (JSON)', 'ss-roles-capabilities' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( current_user_can( 'ss_manage_roles' ) ) : ?>
				<div id="add-role-form" style="display:none; margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
					<h3><?php esc_html_e( 'Add New Role', 'ss-roles-capabilities' ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ss-securesoft&action=add' ) ); ?>">
						<?php wp_nonce_field( 'add_role', 'ss_roles_add_nonce' ); ?>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="role_key">
										<?php esc_html_e( 'Role Slug', 'ss-roles-capabilities' ); ?>
									</label>
								</th>
								<td>
									<input type="text" name="role_key" id="role_key" class="regular-text" required pattern="[a-z0-9_]+" title="<?php esc_attr_e( 'Only lowercase letters, numbers, and underscores allowed.', 'ss-roles-capabilities' ); ?>" />
									<p class="description">
										<?php esc_html_e( 'Lowercase letters, numbers, and underscores only. Example: custom_role', 'ss-roles-capabilities' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="role_name">
										<?php esc_html_e( 'Role Name', 'ss-roles-capabilities' ); ?>
									</label>
								</th>
								<td>
									<input type="text" name="role_name" id="role_name" class="regular-text" required />
									<p class="description">
										<?php esc_html_e( 'Display name for the role. Example: Custom Role', 'ss-roles-capabilities' ); ?>
									</p>
								</td>
							</tr>
						</table>
						<p class="submit">
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Create Role', 'ss-roles-capabilities' ); ?>
							</button>
							<button type="button" class="button" onclick="document.getElementById('add-role-form').style.display='none';">
								<?php esc_html_e( 'Cancel', 'ss-roles-capabilities' ); ?>
							</button>
						</p>
					</form>
				</div>
				<div id="import-form" style="display:none; margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ss-securesoft&action=import' ) ); ?>" enctype="multipart/form-data">
						<?php wp_nonce_field( 'import_roles', 'ss_roles_import_nonce' ); ?>
						<p>
							<label for="roles_file">
								<strong><?php esc_html_e( 'Select JSON file:', 'ss-roles-capabilities' ); ?></strong>
							</label>
							<input type="file" name="roles_file" id="roles_file" accept=".json" required />
						</p>
						<p>
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Import', 'ss-roles-capabilities' ); ?>
							</button>
							<button type="button" class="button" onclick="document.getElementById('import-form').style.display='none';">
								<?php esc_html_e( 'Cancel', 'ss-roles-capabilities' ); ?>
							</button>
						</p>
					</form>
				</div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th class="column-role-name"><?php esc_html_e( 'Role Name', 'ss-roles-capabilities' ); ?></th>
						<th class="column-user-count"><?php esc_html_e( 'User Count', 'ss-roles-capabilities' ); ?></th>
						<th class="column-key-capabilities"><?php esc_html_e( 'Key Capabilities', 'ss-roles-capabilities' ); ?></th>
						<th class="column-last-updated"><?php esc_html_e( 'Last Updated', 'ss-roles-capabilities' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all_roles as $role_key => $role_data ) : ?>
						<?php
						$user_count      = $this->get_user_count( $role_key );
						$key_caps        = $this->get_key_capabilities( $role_data['capabilities'] );
						$last_updated    = $this->get_last_updated( $role_key );
						$edit_url        = admin_url( 'admin.php?page=ss-securesoft-matrix&role=' . urlencode( $role_key ) );
						$duplicate_url   = wp_nonce_url( $base_url . '&action=duplicate&role=' . urlencode( $role_key ), 'duplicate_role_' . $role_key );
						$delete_url      = wp_nonce_url( $base_url . '&action=delete&role=' . urlencode( $role_key ), 'delete_role_' . $role_key );
						$core_roles      = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
						$is_core_role    = in_array( $role_key, $core_roles, true );
						?>
						<tr>
							<td class="column-role-name">
								<strong><?php echo esc_html( $role_data['name'] ); ?></strong>
								<div class="row-actions">
									<?php if ( current_user_can( 'ss_manage_capabilities' ) ) : ?>
										<span class="edit">
											<a href="<?php echo esc_url( $edit_url ); ?>">
												<?php esc_html_e( 'Edit Capabilities', 'ss-roles-capabilities' ); ?>
											</a> |
										</span>
									<?php endif; ?>
									<?php if ( current_user_can( 'ss_manage_roles' ) && ! $is_core_role ) : ?>
										<span class="duplicate">
											<a href="<?php echo esc_url( $duplicate_url ); ?>">
												<?php esc_html_e( 'Duplicate Role', 'ss-roles-capabilities' ); ?>
											</a> |
										</span>
										<span class="delete">
											<a href="<?php echo esc_url( $delete_url ); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this role?', 'ss-roles-capabilities' ) ); ?>');">
												<?php esc_html_e( 'Delete Role', 'ss-roles-capabilities' ); ?>
											</a>
										</span>
									<?php endif; ?>
								</div>
							</td>
							<td class="column-user-count">
								<?php echo esc_html( number_format_i18n( $user_count ) ); ?>
							</td>
							<td class="column-key-capabilities">
								<?php
								if ( ! empty( $key_caps ) ) {
									echo esc_html( implode( ', ', $key_caps ) );
									$total_ss_caps = count( array_intersect( array_keys( array_filter( $role_data['capabilities'] ) ), array_keys( $this->registrar->get_capabilities_map() ) ) );
									if ( $total_ss_caps > 3 ) {
										echo ' <span class="description">(+' . esc_html( $total_ss_caps - 3 ) . ')</span>';
									}
								} else {
									echo '<span class="description">—</span>';
								}
								?>
							</td>
							<td class="column-last-updated">
								<?php echo esc_html( $last_updated ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}



