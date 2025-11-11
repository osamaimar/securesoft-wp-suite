<?php
/**
 * WooCommerce order hooks integration.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Woo;

use SS_Core_Licenses\Licenses\Service;
use SS_Core_Licenses\Audit\Logger;

/**
 * Order hooks class.
 */
class OrderHooks {

	/**
	 * License service instance.
	 *
	 * @var Service
	 */
	private $license_service;

	/**
	 * Audit logger instance.
	 *
	 * @var Logger
	 */
	private $audit_logger;

	/**
	 * Constructor.
	 *
	 * @param Service $license_service License service.
	 * @param Logger  $audit_logger    Audit logger.
	 */
	public function __construct( Service $license_service, Logger $audit_logger ) {
		$this->license_service = $license_service;
		$this->audit_logger = $audit_logger;

		// Reserve licenses when order is created.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'reserve_licenses_for_order' ), 10, 1 );

		// Assign licenses when order is paid.
		add_action( 'woocommerce_payment_complete', array( $this, 'assign_licenses_for_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'assign_licenses_for_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'assign_licenses_for_order' ), 10, 1 );

		// Release licenses when order is cancelled.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'release_licenses_for_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'release_licenses_for_order' ), 10, 1 );

		// Add license meta box to order edit screen.
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ) );

		// Display licenses in order emails.
		add_action( 'woocommerce_email_order_details', array( $this, 'display_licenses_in_email' ), 20, 4 );

		// Handle revoke license from order meta box.
		add_action( 'admin_post_ss_revoke_license', array( $this, 'handle_revoke_license_from_order' ) );
	}

	/**
	 * Handle revoke license from order meta box.
	 */
	public function handle_revoke_license_from_order() {
		if ( ! current_user_can( 'ss_manage_licenses' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		$license_id = isset( $_GET['license_id'] ) ? intval( $_GET['license_id'] ) : 0;
		$order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;

		if ( ! $license_id ) {
			wp_die( esc_html__( 'Invalid license ID.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_revoke_license_' . $license_id );

		$result = $this->license_service->revoke_license( $license_id );

		if ( $result ) {
			if ( $order_id ) {
				wp_safe_redirect( admin_url( 'post.php?post=' . $order_id . '&action=edit&revoked=1' ) );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&revoked=1' ) );
			}
			exit;
		} else {
			wp_die( esc_html__( 'Failed to revoke license.', 'ss-core-licenses' ) );
		}
	}

	/**
	 * Reserve licenses for order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function reserve_licenses_for_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$quantity = $item->get_quantity();

			// Check if product uses internal license delivery.
			$delivery_mode = get_post_meta( $product_id, '_ss_delivery_mode', true );
			if ( 'internal' !== $delivery_mode ) {
				continue;
			}

			// Reserve licenses for each quantity.
			for ( $i = 0; $i < $quantity; $i++ ) {
				$license_id = $this->license_service->reserve_license( $product_id, $order_id );

				if ( $license_id ) {
					// Log audit event.
					$this->audit_logger->log(
						get_current_user_id(),
						'license_reserved',
						'license',
						$license_id,
						array(
							'order_id' => $order_id,
							'product_id' => $product_id,
						)
					);
				}
			}
		}
	}

	/**
	 * Assign licenses for order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function assign_licenses_for_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$licenses = $this->license_service->get_licenses_by_order( $order_id );

		foreach ( $licenses as $license ) {
			if ( 'reserved' === $license['status'] ) {
				$this->license_service->assign_license( $license['id'], $order_id );

				// Log audit event.
				$this->audit_logger->log(
					get_current_user_id(),
					'license_assigned',
					'license',
					$license['id'],
					array(
						'order_id' => $order_id,
						'product_id' => $license['product_id'],
					)
				);

				// Send license to customer (once only).
				$this->send_license_to_customer( $order, $license );
			}
		}
	}

	/**
	 * Release licenses for order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function release_licenses_for_order( $order_id ) {
		$licenses = $this->license_service->get_licenses_by_order( $order_id );

		foreach ( $licenses as $license ) {
			if ( 'reserved' === $license['status'] ) {
				$this->license_service->release_license( $license['id'] );

				// Log audit event.
				$this->audit_logger->log(
					get_current_user_id(),
					'license_released',
					'license',
					$license['id'],
					array(
						'order_id' => $order_id,
					)
				);
			}
		}
	}

	/**
	 * Send license to customer.
	 *
	 * @param \WC_Order $order   Order object.
	 * @param array     $license License data.
	 */
	private function send_license_to_customer( $order, $license ) {
		// Check if license was already sent.
		$meta_key = '_ss_license_sent_' . $license['id'];
		if ( $order->get_meta( $meta_key ) ) {
			return; // Already sent.
		}

		// Get decrypted license code.
		$license_data = $this->license_service->get_license( $license['id'], true );
		if ( ! $license_data || ! isset( $license_data['code'] ) ) {
			return;
		}

		// Store license code in order meta (encrypted or plain based on settings).
		$order->update_meta_data( '_ss_license_code_' . $license['id'], $license_data['code'] );
		$order->update_meta_data( $meta_key, current_time( 'mysql' ) );
		$order->save();

		// Fire action for email/notification system.
		do_action( 'ss/license/sent_to_customer', $license['id'], $order->get_id(), $license_data['code'] );
	}

	/**
	 * Add order meta box.
	 */
	public function add_order_meta_box() {
		add_meta_box(
			'ss_assigned_licenses',
			__( 'Assigned Licenses', 'ss-core-licenses' ),
			array( $this, 'render_order_meta_box' ),
			'shop_order',
			'normal',
			'high'
		);
	}

	/**
	 * Render order meta box.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_order_meta_box( $post ) {
		$order_id = $post->ID;
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$licenses = $this->license_service->get_licenses_by_order( $order_id );

		if ( empty( $licenses ) ) {
			echo '<p>' . esc_html__( 'No licenses assigned to this order.', 'ss-core-licenses' ) . '</p>';
			return;
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'License ID', 'ss-core-licenses' ); ?></th>
					<th><?php esc_html_e( 'Product', 'ss-core-licenses' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ss-core-licenses' ); ?></th>
					<th><?php esc_html_e( 'License Code', 'ss-core-licenses' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ss-core-licenses' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $licenses as $license ) : ?>
					<?php
					$product = wc_get_product( $license['product_id'] );
					$license_data = $this->license_service->get_license( $license['id'], current_user_can( 'ss_view_plain_codes' ) );
					$code = $license_data && isset( $license_data['code'] ) ? $license_data['code'] : '••••••••••••';
					$status = $license['status'];
					// Use WordPress status colors.
					$status_colors = array(
						'available' => '#00a32a',
						'reserved' => '#2271b1',
						'sold' => '#d63638',
						'revoked' => '#50575e',
					);
					$status_color = isset( $status_colors[ $status ] ) ? $status_colors[ $status ] : '#50575e';
					?>
					<tr>
						<td><?php echo esc_html( $license['id'] ); ?></td>
						<td><?php echo $product ? esc_html( $product->get_name() ) : '—'; ?></td>
						<td>
							<span style="padding: 2px 8px; background-color: <?php echo esc_attr( $status_color ); ?>; color: #fff; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
								<?php echo esc_html( ucfirst( $status ) ); ?>
							</span>
						</td>
						<td>
							<code style="padding: 2px 6px; background-color: #f0f0f1; border-radius: 3px;">
								<?php echo esc_html( $code ); ?>
							</code>
						</td>
						<td>
							<?php if ( 'revoked' !== $license['status'] ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ss_revoke_license&license_id=' . $license['id'] . '&order_id=' . $order_id ), 'ss_revoke_license_' . $license['id'] ) ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to revoke this license?', 'ss-core-licenses' ) ); ?>');">
									<?php esc_html_e( 'Revoke', 'ss-core-licenses' ); ?>
								</a>
							<?php else : ?>
								<span style="color: #999;">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Display licenses in order email.
	 *
	 * @param \WC_Order $order         Order object.
	 * @param bool      $sent_to_admin Whether email is sent to admin.
	 * @param bool      $plain_text    Whether email is plain text.
	 * @param \WC_Email $email         Email object.
	 */
	public function display_licenses_in_email( $order, $sent_to_admin, $plain_text, $email ) {
		// Only show in customer emails for completed/processing orders.
		if ( $sent_to_admin ) {
			return;
		}

		$order_id = $order->get_id();
		$licenses = $this->license_service->get_licenses_by_order( $order_id );

		if ( empty( $licenses ) ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n\n" . esc_html__( 'Your License Keys:', 'ss-core-licenses' ) . "\n\n";
			foreach ( $licenses as $license ) {
				$product = wc_get_product( $license['product_id'] );
				$license_data = $this->license_service->get_license( $license['id'], true );
				$code = $license_data && isset( $license_data['code'] ) ? $license_data['code'] : 'N/A';

				echo esc_html( $product ? $product->get_name() : 'Product' ) . ': ' . esc_html( $code ) . "\n";
			}
		} else {
			echo '<h2>' . esc_html__( 'Your License Keys', 'ss-core-licenses' ) . '</h2>';
			echo '<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif;">';
			echo '<thead><tr><th class="td" scope="col">' . esc_html__( 'Product', 'ss-core-licenses' ) . '</th><th class="td" scope="col">' . esc_html__( 'License Key', 'ss-core-licenses' ) . '</th></tr></thead>';
			echo '<tbody>';

			foreach ( $licenses as $license ) {
				$product = wc_get_product( $license['product_id'] );
				$license_data = $this->license_service->get_license( $license['id'], true );
				$code = $license_data && isset( $license_data['code'] ) ? $license_data['code'] : 'N/A';

				echo '<tr>';
				echo '<td class="td">' . esc_html( $product ? $product->get_name() : 'Product' ) . '</td>';
				echo '<td class="td"><code>' . esc_html( $code ) . '</code></td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}
	}
}

