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
		// Get filters.
		$actor_user_id = isset( $_GET['actor_user_id'] ) ? intval( $_GET['actor_user_id'] ) : 0;
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
		$entity_type = isset( $_GET['entity_type'] ) ? sanitize_text_field( $_GET['entity_type'] ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
		$paged = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
		$per_page = 50;

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

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Audit Log', 'ss-core-licenses' ); ?></h1>

			<form method="get" action="">
				<input type="hidden" name="page" value="securesoft-audit">
				<div class="tablenav top">
					<div class="alignleft actions">
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
							<option value="license_reserved" <?php selected( $action, 'license_reserved' ); ?>><?php esc_html_e( 'License Reserved', 'ss-core-licenses' ); ?></option>
							<option value="license_assigned" <?php selected( $action, 'license_assigned' ); ?>><?php esc_html_e( 'License Assigned', 'ss-core-licenses' ); ?></option>
							<option value="license_revoked" <?php selected( $action, 'license_revoked' ); ?>><?php esc_html_e( 'License Revoked', 'ss-core-licenses' ); ?></option>
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
					</div>

					<div class="alignright">
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ss_export_audit' ), 'ss_export_audit' ) ); ?>" class="button">
							<?php esc_html_e( 'Export CSV', 'ss-core-licenses' ); ?>
						</a>
					</div>
				</div>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'User', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Action', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Entity', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'IP Address', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Details', 'ss-core-licenses' ); ?></th>
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
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_logs > $per_page ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						$page_links = paginate_links(
							array(
								'base' => add_query_arg( 'paged', '%#%' ),
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

		// Get all logs.
		$logs = $this->audit_logger->get_logs( array( 'limit' => -1 ) );

		// Generate CSV.
		$filename = 'audit-log-' . date( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

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
}

