<?php
/**
 * SecureSoft → Webhooks admin screen.
 *
 * @package SS_Roles_Capabilities
 */

namespace SS_Roles_Capabilities\Admin\Screens;

/**
 * Webhooks configuration admin screen.
 */
class Webhooks {

	/**
	 * Option name used to store webhooks configuration.
	 */
	const OPTION_NAME = 'ss_roles_webhooks';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register Webhooks submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'ss-securesoft',
			__( 'Webhooks', 'ss-roles-capabilities' ),
			__( 'Webhooks', 'ss-roles-capabilities' ),
			'ss_manage_webhooks',
			'ss-securesoft-webhooks',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render Webhooks configuration page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'ss_manage_webhooks' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ss-roles-capabilities' ) );
		}

		$this->handle_actions();

		// Handle success messages.
		$messages = array();
		if ( isset( $_GET['added'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages[] = array(
				'type'    => 'success',
				'message' => __( 'Webhook added successfully.', 'ss-roles-capabilities' ),
			);
		}
		if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages[] = array(
				'type'    => 'success',
				'message' => __( 'Webhook deleted successfully.', 'ss-roles-capabilities' ),
			);
		}
		if ( isset( $_GET['tested'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'success' === $status ) {
				$messages[] = array(
					'type'    => 'success',
					'message' => __( 'Test webhook sent successfully.', 'ss-roles-capabilities' ),
				);
			} elseif ( 'error' === $status ) {
				$messages[] = array(
					'type'    => 'error',
					'message' => __( 'Test webhook failed to send.', 'ss-roles-capabilities' ),
				);
			}
		}

		$webhooks = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $webhooks ) ) {
			$webhooks = array();
		}

		$action_url = admin_url( 'admin.php?page=ss-securesoft-webhooks' );

		$events = array(
			'user_registered'  => __( 'User Registered', 'ss-roles-capabilities' ),
			'user_role_changed'=> __( 'User Role Changed', 'ss-roles-capabilities' ),
			'user_suspended'   => __( 'User Suspended', 'ss-roles-capabilities' ),
			'user_reactivated' => __( 'User Reactivated', 'ss-roles-capabilities' ),
		);

		// Group webhooks by event.
		$webhooks_by_event = array();
		foreach ( $webhooks as $webhook_id => $webhook ) {
			$event = isset( $webhook['event'] ) ? $webhook['event'] : '';
			if ( ! isset( $webhooks_by_event[ $event ] ) ) {
				$webhooks_by_event[ $event ] = array();
			}
			$webhooks_by_event[ $event ][ $webhook_id ] = $webhook;
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SecureSoft → Webhooks', 'ss-roles-capabilities' ); ?></h1>
			<p><?php esc_html_e( 'Configure outgoing webhooks for user and role events. Multiple webhooks can be configured per event.', 'ss-roles-capabilities' ); ?></p>

			<?php if ( ! empty( $messages ) ) : ?>
				<?php foreach ( $messages as $msg ) : ?>
					<div class="notice notice-<?php echo esc_attr( $msg['type'] ); ?> is-dismissible"><p><?php echo esc_html( $msg['message'] ); ?></p></div>
				<?php endforeach; ?>
			<?php endif; ?>

			<div class="tablenav top">
				<div class="alignleft actions">
					<button type="button" class="button" onclick="document.getElementById('add-webhook-form').style.display='block';">
						<?php esc_html_e( 'Add Webhook', 'ss-roles-capabilities' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ss-securesoft-webhooks&action=logs' ) ); ?>" class="button">
						<?php esc_html_e( 'View Logs', 'ss-roles-capabilities' ); ?>
					</a>
				</div>
			</div>

			<div id="add-webhook-form" style="display:none; margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
				<h3><?php esc_html_e( 'Add New Webhook', 'ss-roles-capabilities' ); ?></h3>
				<form method="post" action="<?php echo esc_url( $action_url ); ?>">
					<?php wp_nonce_field( 'ss_roles_add_webhook', 'ss_roles_add_webhook_nonce' ); ?>
					<input type="hidden" name="action" value="add" />
					<table class="form-table" role="presentation">
						<tr>
							<th><label for="new_webhook_event"><?php esc_html_e( 'Event', 'ss-roles-capabilities' ); ?></label></th>
							<td>
								<select name="event" id="new_webhook_event" required>
									<option value=""><?php esc_html_e( 'Select Event', 'ss-roles-capabilities' ); ?></option>
									<?php foreach ( $events as $event_key => $event_label ) : ?>
										<option value="<?php echo esc_attr( $event_key ); ?>">
											<?php echo esc_html( $event_label . ' (' . $event_key . ')' ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="new_webhook_url"><?php esc_html_e( 'URL', 'ss-roles-capabilities' ); ?></label></th>
							<td>
								<input type="url" name="url" id="new_webhook_url" class="regular-text" placeholder="https://example.com/webhook" required />
							</td>
						</tr>
						<tr>
							<th><label for="new_webhook_secret"><?php esc_html_e( 'Secret Key (HMAC)', 'ss-roles-capabilities' ); ?></label></th>
							<td>
								<input type="text" name="secret" id="new_webhook_secret" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Optional: Secret key for HMAC signature verification.', 'ss-roles-capabilities' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Retry on Failure', 'ss-roles-capabilities' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="retry" value="1" checked />
									<?php esc_html_e( 'Automatically retry failed webhook deliveries', 'ss-roles-capabilities' ); ?>
								</label>
							</td>
						</tr>
					</table>
					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Webhook', 'ss-roles-capabilities' ); ?></button>
						<button type="button" class="button" onclick="document.getElementById('add-webhook-form').style.display='none';">
							<?php esc_html_e( 'Cancel', 'ss-roles-capabilities' ); ?>
						</button>
					</p>
				</form>
			</div>

			<?php foreach ( $events as $event_key => $event_label ) : ?>
				<h2><?php echo esc_html( $event_label ); ?> <code><?php echo esc_html( $event_key ); ?></code></h2>
				<?php if ( empty( $webhooks_by_event[ $event_key ] ) ) : ?>
					<p class="description"><?php esc_html_e( 'No webhooks configured for this event.', 'ss-roles-capabilities' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'URL', 'ss-roles-capabilities' ); ?></th>
								<th><?php esc_html_e( 'Secret Key', 'ss-roles-capabilities' ); ?></th>
								<th><?php esc_html_e( 'Retry on Failure', 'ss-roles-capabilities' ); ?></th>
								<th><?php esc_html_e( 'Status', 'ss-roles-capabilities' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'ss-roles-capabilities' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $webhooks_by_event[ $event_key ] as $webhook_id => $webhook ) : ?>
								<tr>
									<td>
										<code><?php echo esc_html( $webhook['url'] ); ?></code>
									</td>
									<td>
										<?php echo ! empty( $webhook['secret'] ) ? '<code>••••••••</code>' : '<span class="description">—</span>'; ?>
									</td>
									<td style="text-align:center;">
										<?php echo ! empty( $webhook['retry'] ) ? '✓' : '—'; ?>
									</td>
									<td>
										<?php echo ! empty( $webhook['enabled'] ) ? '<span style="color:green;">' . esc_html__( 'Enabled', 'ss-roles-capabilities' ) . '</span>' : '<span style="color:red;">' . esc_html__( 'Disabled', 'ss-roles-capabilities' ) . '</span>'; ?>
									</td>
									<td>
										<a href="<?php echo esc_url( wp_nonce_url( $action_url . '&action=test&webhook_id=' . $webhook_id, 'test_webhook_' . $webhook_id ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Send Test', 'ss-roles-capabilities' ); ?>
										</a>
										<a href="<?php echo esc_url( wp_nonce_url( $action_url . '&action=delete&webhook_id=' . $webhook_id, 'delete_webhook_' . $webhook_id ) ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this webhook?', 'ss-roles-capabilities' ) ); ?>');">
											<?php esc_html_e( 'Delete', 'ss-roles-capabilities' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Handle webhook actions (add, delete, test).
	 *
	 * @return void
	 */
	protected function handle_actions() {
		if ( ! isset( $_GET['page'] ) || 'ss-securesoft-webhooks' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
				$this->handle_add_webhook();
				break;
			case 'delete':
				$this->handle_delete_webhook();
				break;
			case 'test':
				$this->handle_test_webhook();
				break;
			case 'logs':
				$this->render_logs_page();
				return;
		}
	}

	/**
	 * Handle add webhook action.
	 *
	 * @return void
	 */
	protected function handle_add_webhook() {
		if ( ! isset( $_POST['ss_roles_add_webhook_nonce'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Missing
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ss_roles_add_webhook_nonce'] ) ), 'ss_roles_add_webhook' ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		) {
			return;
		}

		if ( ! current_user_can( 'ss_manage_webhooks' ) ) {
			return;
		}

		$event  = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( $_POST['event'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$url    = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$secret = isset( $_POST['secret'] ) ? sanitize_text_field( wp_unslash( $_POST['secret'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$retry  = ! empty( $_POST['retry'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $event ) || empty( $url ) ) {
			return;
		}

		$webhooks = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $webhooks ) ) {
			$webhooks = array();
		}

		$webhook_id = wp_generate_uuid4();
		$webhooks[ $webhook_id ] = array(
			'event'           => $event,
			'url'              => $url,
			'secret'           => $secret,
			'retry'            => $retry ? 1 : 0,
			'enabled'          => true,
			'created_at'       => current_time( 'mysql' ),
		);

		update_option( self::OPTION_NAME, $webhooks );

		wp_safe_redirect( admin_url( 'admin.php?page=ss-securesoft-webhooks&added=1' ) );
		exit;
	}

	/**
	 * Handle delete webhook action.
	 *
	 * @return void
	 */
	protected function handle_delete_webhook() {
		if ( ! current_user_can( 'ss_manage_webhooks' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete webhooks.', 'ss-roles-capabilities' ) );
		}

		$webhook_id = isset( $_GET['webhook_id'] ) ? sanitize_text_field( wp_unslash( $_GET['webhook_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $webhook_id ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_webhook_' . $webhook_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$webhooks = get_option( self::OPTION_NAME, array() );
		if ( isset( $webhooks[ $webhook_id ] ) ) {
			unset( $webhooks[ $webhook_id ] );
			update_option( self::OPTION_NAME, $webhooks );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ss-securesoft-webhooks&deleted=1' ) );
		exit;
	}

	/**
	 * Handle test webhook action.
	 *
	 * @return void
	 */
	protected function handle_test_webhook() {
		if ( ! current_user_can( 'ss_manage_webhooks' ) ) {
			wp_die( esc_html__( 'You do not have permission to test webhooks.', 'ss-roles-capabilities' ) );
		}

		$webhook_id = isset( $_GET['webhook_id'] ) ? sanitize_text_field( wp_unslash( $_GET['webhook_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $webhook_id ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'test_webhook_' . $webhook_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$webhooks = get_option( self::OPTION_NAME, array() );
		if ( ! isset( $webhooks[ $webhook_id ] ) ) {
			return;
		}

		$webhook = $webhooks[ $webhook_id ];
		$payload = array(
			'event'     => 'test',
			'timestamp' => current_time( 'mysql' ),
			'data'      => array(
				'message' => __( 'This is a test webhook from SecureSoft Roles & Capabilities.', 'ss-roles-capabilities' ),
			),
		);

		$body      = wp_json_encode( $payload );
		$signature = '';
		if ( ! empty( $webhook['secret'] ) ) {
			$signature = hash_hmac( 'sha256', $body, $webhook['secret'] );
		}

		$response = wp_remote_post(
			$webhook['url'],
			array(
				'method'      => 'POST',
				'body'        => $body,
				'headers'     => array(
					'Content-Type'      => 'application/json',
					'X-SS-Event'        => 'test',
					'X-SS-Signature'    => $signature,
				),
				'timeout'     => 10,
			)
		);

		$status = is_wp_error( $response ) ? 'error' : 'success';
		wp_safe_redirect( admin_url( 'admin.php?page=ss-securesoft-webhooks&tested=1&status=' . $status ) );
		exit;
	}

	/**
	 * Render webhook logs page.
	 *
	 * @return void
	 */
	protected function render_logs_page() {
		// Simple logs view - can be enhanced with database table later.
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Webhook Logs', 'ss-roles-capabilities' ); ?></h1>
			<p><?php esc_html_e( 'Webhook delivery logs. This is a simplified view. Full logging can be implemented with a database table.', 'ss-roles-capabilities' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ss-securesoft-webhooks' ) ); ?>" class="button"><?php esc_html_e( 'Back to Webhooks', 'ss-roles-capabilities' ); ?></a></p>
		</div>
		<?php
	}

}



