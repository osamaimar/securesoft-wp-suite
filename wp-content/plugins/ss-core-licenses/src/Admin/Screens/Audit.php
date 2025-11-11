<?php
/**
 * Audit log admin screen.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Admin\Screens;

use SS_Core_Licenses\Audit\Logger;

/**
 * Audit screen class.
 */
class Audit {

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

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_ss_export_audit', array( $this, 'handle_export' ) );
		add_action( 'admin_post_ss_save_audit_filter', array( $this, 'handle_save_filter' ) );
		add_action( 'admin_post_ss_delete_audit_filter', array( $this, 'handle_delete_filter' ) );
	}

	/**
	 * Add menu page.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'securesoft-licenses',
			__( 'Audit Log', 'ss-core-licenses' ),
			__( 'Audit Log', 'ss-core-licenses' ),
			'ss_view_audit_log',
			'securesoft-audit',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render page.
	 */
	public function render_page() {
		// Handle saved filter.
		$actor_user_id = isset( $_GET['actor_user_id'] ) ? intval( $_GET['actor_user_id'] ) : 0;
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
		$entity_type = isset( $_GET['entity_type'] ) ? sanitize_text_field( $_GET['entity_type'] ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
		
		if ( isset( $_GET['filter_id'] ) ) {
			$filter_id = intval( $_GET['filter_id'] );
			$saved_filters = get_user_meta( get_current_user_id(), 'ss_saved_audit_filters', true );
			if ( is_array( $saved_filters ) && isset( $saved_filters[ $filter_id ] ) ) {
				$filter_data = $saved_filters[ $filter_id ];
				$actor_user_id = isset( $filter_data['actor_user_id'] ) ? intval( $filter_data['actor_user_id'] ) : 0;
				$action = isset( $filter_data['action'] ) ? sanitize_text_field( $filter_data['action'] ) : '';
				$entity_type = isset( $filter_data['entity_type'] ) ? sanitize_text_field( $filter_data['entity_type'] ) : '';
				$date_from = isset( $filter_data['date_from'] ) ? sanitize_text_field( $filter_data['date_from'] ) : '';
				$date_to = isset( $filter_data['date_to'] ) ? sanitize_text_field( $filter_data['date_to'] ) : '';
			}
		}

		$paged = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
		$per_page = 50;
		
		// Get sorting parameters.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at';
		$order = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';

		// Get saved filters.
		$saved_filters = get_user_meta( get_current_user_id(), 'ss_saved_audit_filters', true );
		if ( ! is_array( $saved_filters ) ) {
			$saved_filters = array();
		}

		// Get logs.
		$logs = $this->audit_logger->get_logs(
			array(
				'actor_user_id' => $actor_user_id,
				'action' => $action,
				'entity_type' => $entity_type,
				'date_from' => $date_from,
				'date_to' => $date_to,
				'limit' => $per_page,
				'offset' => ( $paged - 1 ) * $per_page,
				'orderby' => $orderby,
				'order' => $order,
			)
		);

		// Get total count (simplified - in production, you'd want a count method).
		$total_logs = count( $this->audit_logger->get_logs(
			array(
				'actor_user_id' => $actor_user_id,
				'action' => $action,
				'entity_type' => $entity_type,
				'date_from' => $date_from,
				'date_to' => $date_to,
				'limit' => -1,
			)
		) );

		// Get users for filter.
		$users = get_users( array( 'role__in' => array( 'administrator', 'shop_manager' ) ) );

		// Show success messages.
		if ( isset( $_GET['filter_saved'] ) && $_GET['filter_saved'] == 1 ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Filter saved successfully.', 'ss-core-licenses' ) . '</p></div>';
		}
		if ( isset( $_GET['filter_deleted'] ) && $_GET['filter_deleted'] == 1 ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Filter deleted successfully.', 'ss-core-licenses' ) . '</p></div>';
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Audit Log', 'ss-core-licenses' ); ?></h1>

			<form method="get" action="">
				<input type="hidden" name="page" value="securesoft-audit">
				<?php if ( $orderby && 'created_at' !== $orderby ) : ?>
					<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
				<?php endif; ?>
				<?php if ( $order && 'DESC' !== $order ) : ?>
					<input type="hidden" name="order" value="<?php echo esc_attr( strtolower( $order ) ); ?>">
				<?php endif; ?>
				<div class="tablenav top">
					<div class="alignleft actions">
						<?php if ( ! empty( $saved_filters ) ) : ?>
							<select name="saved_filter" id="ss-saved-audit-filter" onchange="if(this.value) { window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=securesoft-audit' ) ); ?>&filter_id=' + this.value; }">
								<option value=""><?php esc_html_e( 'Saved Filters...', 'ss-core-licenses' ); ?></option>
								<?php foreach ( $saved_filters as $id => $filter ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>">
										<?php echo esc_html( $filter['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>

						<select name="actor_user_id">
							<option value=""><?php esc_html_e( 'All Users', 'ss-core-licenses' ); ?></option>
							<?php foreach ( $users as $user ) : ?>
								<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $actor_user_id, $user->ID ); ?>>
									<?php echo esc_html( $user->display_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<select name="action">
							<option value=""><?php esc_html_e( 'All Actions', 'ss-core-licenses' ); ?></option>
							<option value="license_imported" <?php selected( $action, 'license_imported' ); ?>><?php esc_html_e( 'License Imported', 'ss-core-licenses' ); ?></option>
							<option value="license_added_manually" <?php selected( $action, 'license_added_manually' ); ?>><?php esc_html_e( 'License Added Manually', 'ss-core-licenses' ); ?></option>
							<option value="license_reserved" <?php selected( $action, 'license_reserved' ); ?>><?php esc_html_e( 'License Reserved', 'ss-core-licenses' ); ?></option>
							<option value="license_assigned" <?php selected( $action, 'license_assigned' ); ?>><?php esc_html_e( 'License Assigned', 'ss-core-licenses' ); ?></option>
							<option value="license_revoked" <?php selected( $action, 'license_revoked' ); ?>><?php esc_html_e( 'License Revoked', 'ss-core-licenses' ); ?></option>
							<option value="license_released" <?php selected( $action, 'license_released' ); ?>><?php esc_html_e( 'License Released', 'ss-core-licenses' ); ?></option>
							<option value="pool_recounted" <?php selected( $action, 'pool_recounted' ); ?>><?php esc_html_e( 'Pool Recounted', 'ss-core-licenses' ); ?></option>
							<option value="key_generated" <?php selected( $action, 'key_generated' ); ?>><?php esc_html_e( 'Key Generated', 'ss-core-licenses' ); ?></option>
							<option value="keys_rotated" <?php selected( $action, 'keys_rotated' ); ?>><?php esc_html_e( 'Keys Rotated', 'ss-core-licenses' ); ?></option>
						</select>

						<select name="entity_type">
							<option value=""><?php esc_html_e( 'All Entities', 'ss-core-licenses' ); ?></option>
							<option value="license" <?php selected( $entity_type, 'license' ); ?>><?php esc_html_e( 'License', 'ss-core-licenses' ); ?></option>
							<option value="key" <?php selected( $entity_type, 'key' ); ?>><?php esc_html_e( 'Key', 'ss-core-licenses' ); ?></option>
							<option value="pool" <?php selected( $entity_type, 'pool' ); ?>><?php esc_html_e( 'Pool', 'ss-core-licenses' ); ?></option>
						</select>

						<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'From Date', 'ss-core-licenses' ); ?>">
						<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'To Date', 'ss-core-licenses' ); ?>">

						<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'ss-core-licenses' ); ?>">
						
						<?php if ( $actor_user_id > 0 || $action || $entity_type || $date_from || $date_to ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=securesoft-audit' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'ss-core-licenses' ); ?></a>
							<button type="button" class="button" onclick="document.getElementById('ss-save-audit-filter-modal').style.display='block';"><?php esc_html_e( 'Save Filter', 'ss-core-licenses' ); ?></button>
						<?php endif; ?>
					</div>

					<div class="alignright actions">
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ss_export_audit' . ( $actor_user_id ? '&actor_user_id=' . $actor_user_id : '' ) . ( $action ? '&action=' . urlencode( $action ) : '' ) . ( $entity_type ? '&entity_type=' . urlencode( $entity_type ) : '' ) . ( $date_from ? '&date_from=' . urlencode( $date_from ) : '' ) . ( $date_to ? '&date_to=' . urlencode( $date_to ) : '' ) ), 'ss_export_audit' ) ); ?>" class="button">
							<?php esc_html_e( 'Export', 'ss-core-licenses' ); ?>
						</a>
					</div>
				</div>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<?php echo $this->get_column_header( 'created_at', __( 'Date', 'ss-core-licenses' ), $orderby, $order ); ?>
						<th class="manage-column"><?php esc_html_e( 'Details', 'ss-core-licenses' ); ?></th>
						<?php echo $this->get_column_header( 'actor_user_id', __( 'User', 'ss-core-licenses' ), $orderby, $order ); ?>
						<?php echo $this->get_column_header( 'action', __( 'Action', 'ss-core-licenses' ), $orderby, $order ); ?>
						<?php echo $this->get_column_header( 'entity_type', __( 'Entity', 'ss-core-licenses' ), $orderby, $order ); ?>
						<?php echo $this->get_column_header( 'ip', __( 'IP Address', 'ss-core-licenses' ), $orderby, $order ); ?>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No audit logs found.', 'ss-core-licenses' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<?php
							$user = get_user_by( 'id', $log['actor_user_id'] );
							?>
							<tr>
								<td><?php echo esc_html( $log['created_at'] ); ?></td>
								<td>
									<?php if ( ! empty( $log['meta'] ) ) : ?>
										<details>
											<summary><?php esc_html_e( 'View Details', 'ss-core-licenses' ); ?></summary>
											<pre class="code"><?php echo esc_html( wp_json_encode( $log['meta'], JSON_PRETTY_PRINT ) ); ?></pre>
										</details>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td><?php echo $user ? esc_html( $user->display_name ) : '—'; ?></td>
								<td><?php echo esc_html( $log['action'] ); ?></td>
								<td>
									<?php
									echo esc_html( $log['entity_type'] );
									if ( $log['entity_id'] ) {
										echo ' #' . esc_html( $log['entity_id'] );
									}
									?>
								</td>
								<td><?php echo esc_html( $log['ip'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_logs > $per_page ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						$pagination_base = add_query_arg( array( 'paged' => '%#%', 'orderby' => $orderby, 'order' => strtolower( $order ) ), remove_query_arg( 'paged' ) );
						$page_links = paginate_links(
							array(
								'base' => $pagination_base,
								'format' => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total' => ceil( $total_logs / $per_page ),
								'current' => $paged,
							)
						);
						echo $page_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Save Filter Modal -->
			<div id="ss-save-audit-filter-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; border:1px solid #ccc; z-index:100000;">
				<h2><?php esc_html_e( 'Save Filter', 'ss-core-licenses' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ss_save_audit_filter' ); ?>
					<input type="hidden" name="action" value="ss_save_audit_filter">
					<input type="hidden" name="actor_user_id" value="<?php echo esc_attr( $actor_user_id ); ?>">
					<input type="hidden" name="action_filter" value="<?php echo esc_attr( $action ); ?>">
					<input type="hidden" name="entity_type" value="<?php echo esc_attr( $entity_type ); ?>">
					<input type="hidden" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
					<input type="hidden" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
					<p>
						<label><?php esc_html_e( 'Filter Name:', 'ss-core-licenses' ); ?>
							<input type="text" name="filter_name" required class="regular-text">
						</label>
					</p>
					<p>
						<?php submit_button( __( 'Save', 'ss-core-licenses' ), 'primary', 'submit', false ); ?>
						<button type="button" class="button" onclick="document.getElementById('ss-save-audit-filter-modal').style.display='none';"><?php esc_html_e( 'Cancel', 'ss-core-licenses' ); ?></button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle export.
	 */
	public function handle_export() {
		if ( ! current_user_can( 'ss_view_audit_log' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_export_audit' );

		// Get filters from URL.
		$actor_user_id = isset( $_GET['actor_user_id'] ) ? intval( $_GET['actor_user_id'] ) : 0;
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
		$entity_type = isset( $_GET['entity_type'] ) ? sanitize_text_field( $_GET['entity_type'] ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';

		// Get filtered logs.
		$logs = $this->audit_logger->get_logs(
			array(
				'actor_user_id' => $actor_user_id,
				'action' => $action,
				'entity_type' => $entity_type,
				'date_from' => $date_from,
				'date_to' => $date_to,
				'limit' => -1,
			)
		);

		// Generate CSV.
		$filename = 'audit-log-' . date( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		// Add UTF-8 BOM for Excel compatibility.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Header.
		fputcsv( $output, array( 'Date', 'User', 'Action', 'Entity Type', 'Entity ID', 'IP Address', 'Meta' ) );

		// Rows.
		foreach ( $logs as $log ) {
			$user = get_user_by( 'id', $log['actor_user_id'] );
			fputcsv(
				$output,
				array(
					$log['created_at'],
					$user ? $user->display_name : '',
					$log['action'],
					$log['entity_type'],
					$log['entity_id'],
					$log['ip'],
					wp_json_encode( $log['meta'] ),
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		exit;
	}

	/**
	 * Handle save filter.
	 */
	public function handle_save_filter() {
		if ( ! current_user_can( 'ss_view_audit_log' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_save_audit_filter' );

		$filter_name = isset( $_POST['filter_name'] ) ? sanitize_text_field( $_POST['filter_name'] ) : '';
		$actor_user_id = isset( $_POST['actor_user_id'] ) ? intval( $_POST['actor_user_id'] ) : 0;
		$action = isset( $_POST['action_filter'] ) ? sanitize_text_field( $_POST['action_filter'] ) : '';
		$entity_type = isset( $_POST['entity_type'] ) ? sanitize_text_field( $_POST['entity_type'] ) : '';
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : '';
		$date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : '';

		if ( empty( $filter_name ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-audit&error=1' ) );
			exit;
		}

		$saved_filters = get_user_meta( get_current_user_id(), 'ss_saved_audit_filters', true );
		if ( ! is_array( $saved_filters ) ) {
			$saved_filters = array();
		}

		$filter_id = time();
		$saved_filters[ $filter_id ] = array(
			'name' => $filter_name,
			'actor_user_id' => $actor_user_id,
			'action' => $action,
			'entity_type' => $entity_type,
			'date_from' => $date_from,
			'date_to' => $date_to,
		);

		update_user_meta( get_current_user_id(), 'ss_saved_audit_filters', $saved_filters );

		wp_safe_redirect( admin_url( 'admin.php?page=securesoft-audit&filter_saved=1' ) );
		exit;
	}

	/**
	 * Handle delete filter.
	 */
	public function handle_delete_filter() {
		if ( ! current_user_can( 'ss_view_audit_log' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_delete_audit_filter' );

		$filter_id = isset( $_GET['filter_id'] ) ? intval( $_GET['filter_id'] ) : 0;

		if ( $filter_id > 0 ) {
			$saved_filters = get_user_meta( get_current_user_id(), 'ss_saved_audit_filters', true );
			if ( is_array( $saved_filters ) && isset( $saved_filters[ $filter_id ] ) ) {
				unset( $saved_filters[ $filter_id ] );
				update_user_meta( get_current_user_id(), 'ss_saved_audit_filters', $saved_filters );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=securesoft-audit&filter_deleted=1' ) );
		exit;
	}

	/**
	 * Get sortable column header.
	 *
	 * @param string $column_key Column key.
	 * @param string $column_name Column display name.
	 * @param string $current_orderby Current orderby parameter.
	 * @param string $current_order Current order parameter.
	 * @return string Column header HTML.
	 */
	private function get_column_header( $column_key, $column_name, $current_orderby, $current_order ) {
		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$current_url = remove_query_arg( array( 'paged', 'orderby', 'order' ), $current_url );

		// Determine sort order.
		if ( $current_orderby === $column_key ) {
			$order = 'asc' === strtolower( $current_order ) ? 'desc' : 'asc';
			$class = 'sorted ' . strtolower( $current_order );
			$aria_sort = 'asc' === strtolower( $current_order ) ? ' aria-sort="ascending"' : ' aria-sort="descending"';
		} else {
			$order = 'asc';
			$class = 'sortable asc';
			$aria_sort = '';
		}

		$orderby = $column_key;
		$url = add_query_arg( compact( 'orderby', 'order' ), $current_url );

		// Build column header with WordPress default classes.
		$header = sprintf(
			'<th scope="col" class="manage-column column-%s %s"%s>',
			esc_attr( $column_key ),
			esc_attr( $class ),
			$aria_sort
		);

		$header .= sprintf(
			'<a href="%s">' .
			'<span>%s</span>' .
			'<span class="sorting-indicators">' .
			'<span class="sorting-indicator asc" aria-hidden="true"></span>' .
			'<span class="sorting-indicator desc" aria-hidden="true"></span>' .
			'</span>' .
			'<span class="screen-reader-text">%s</span>' .
			'</a>',
			esc_url( $url ),
			esc_html( $column_name ),
			esc_html__( 'Sort ascending.', 'ss-core-licenses' )
		);

		$header .= '</th>';

		return $header;
	}
}

