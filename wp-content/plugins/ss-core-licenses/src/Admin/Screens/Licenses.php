<?php
/**
 * Licenses admin screen.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Admin\Screens;

use SS_Core_Licenses\Licenses\Service;
use SS_Core_Licenses\Audit\Logger;

/**
 * Licenses screen class.
 */
class Licenses {

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

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_ss_revoke_license', array( $this, 'handle_revoke_license' ) );
		add_action( 'admin_post_ss_add_license', array( $this, 'handle_add_license' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_thickbox' ) );
	}

	/**
	 * Add menu page.
	 */
	public function add_menu_page() {
		// Create the main menu page. WordPress will automatically create a submenu item
		// with the same slug, which will appear as the first item in the submenu list.
		// This is the standard WordPress behavior and we don't need to create an explicit submenu.
		add_menu_page(
			__( 'Licenses', 'ss-core-licenses' ),
			__( 'Licenses', 'ss-core-licenses' ),
			'ss_manage_licenses',
			'securesoft-licenses',
			array( $this, 'render_page' ),
			'dashicons-lock',
			30
		);
	}

	/**
	 * Render page.
	 */
	public function render_page() {
		// Handle actions.
		$this->handle_actions();

		// Get filters.
		$product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;
		$status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$paged = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
		$per_page = 20;

		// Get licenses.
		$license_repo = new \SS_Core_Licenses\Licenses\Repository();
		$licenses = $license_repo->search(
			array(
				'product_id' => $product_id,
				'status' => $status,
				'search' => $search,
				'limit' => $per_page,
				'offset' => ( $paged - 1 ) * $per_page,
			)
		);

		// Get total count.
		$total = count( $license_repo->search(
			array(
				'product_id' => $product_id,
				'status' => $status,
				'search' => $search,
				'limit' => -1,
			)
		) );

		// Get products for filter.
		if ( ! function_exists( 'wc_get_products' ) ) {
			$products = array();
		} else {
			$products = wc_get_products( array( 'limit' => -1 ) );
		}

		// Show success/error messages.
		if ( isset( $_GET['added'] ) && '1' === $_GET['added'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'License added successfully.', 'ss-core-licenses' );
			echo '</p></div>';
		}
		if ( isset( $_GET['error'] ) ) {
			$error_msg = sanitize_text_field( $_GET['error'] );
			echo '<div class="notice notice-error is-dismissible"><p>';
			echo esc_html__( 'Error: ', 'ss-core-licenses' ) . esc_html( $error_msg );
			echo '</p></div>';
		}

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Licenses', 'ss-core-licenses' ); ?></h1>
			<hr class="wp-header-end">

			<!-- Add New License Modal (Thickbox) -->
			<div id="ss-add-license-modal" style="display: none;">
				<div class="wrap">
					<h1><?php esc_html_e( 'Add New License', 'ss-core-licenses' ); ?></h1>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ss-add-license-form">
						<?php wp_nonce_field( 'ss_add_license' ); ?>
						<input type="hidden" name="action" value="ss_add_license">

						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="license_code"><?php esc_html_e( 'License Code', 'ss-core-licenses' ); ?> <span class="description">(required)</span></label>
								</th>
								<td>
									<input type="text" name="license_code" id="license_code" class="regular-text" required>
									<p class="description"><?php esc_html_e( 'Enter the license code (will be encrypted automatically).', 'ss-core-licenses' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="product_id_add"><?php esc_html_e( 'Product', 'ss-core-licenses' ); ?> <span class="description">(required)</span></label>
								</th>
								<td>
									<select name="product_id" id="product_id_add" class="regular-text" required>
										<option value=""><?php esc_html_e( 'Select a product', 'ss-core-licenses' ); ?></option>
										<?php foreach ( $products as $product ) : ?>
											<option value="<?php echo esc_attr( $product->get_id() ); ?>">
												<?php echo esc_html( $product->get_name() ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Select the WooCommerce product for this license.', 'ss-core-licenses' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="provider_ref"><?php esc_html_e( 'Provider Reference', 'ss-core-licenses' ); ?></label>
								</th>
								<td>
									<input type="text" name="provider_ref" id="provider_ref" class="regular-text">
									<p class="description"><?php esc_html_e( 'Optional: External supplier reference.', 'ss-core-licenses' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="status_add"><?php esc_html_e( 'Status', 'ss-core-licenses' ); ?></label>
								</th>
								<td>
									<select name="status" id="status_add" class="regular-text">
										<option value="available" selected><?php esc_html_e( 'Available', 'ss-core-licenses' ); ?></option>
										<option value="reserved"><?php esc_html_e( 'Reserved', 'ss-core-licenses' ); ?></option>
										<option value="sold"><?php esc_html_e( 'Sold', 'ss-core-licenses' ); ?></option>
										<option value="revoked"><?php esc_html_e( 'Revoked', 'ss-core-licenses' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Initial status for the license (default: Available).', 'ss-core-licenses' ); ?></p>
								</td>
							</tr>
						</table>

						<p class="submit">
							<?php submit_button( __( 'Add License', 'ss-core-licenses' ), 'primary', 'submit', false ); ?>
							<button type="button" class="button" onclick="tb_remove();"><?php esc_html_e( 'Cancel', 'ss-core-licenses' ); ?></button>
						</p>
					</form>
				</div>
			</div>

			<!-- Filters and List -->
			<div class="tablenav top">
				<div class="alignleft actions">
					<form method="get" action="" style="display: inline-block;">
						<input type="hidden" name="page" value="securesoft-licenses">
						<select name="product_id">
							<option value=""><?php esc_html_e( 'All Products', 'ss-core-licenses' ); ?></option>
							<?php foreach ( $products as $product ) : ?>
								<option value="<?php echo esc_attr( $product->get_id() ); ?>" <?php selected( $product_id, $product->get_id() ); ?>>
									<?php echo esc_html( $product->get_name() ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<select name="status">
							<option value=""><?php esc_html_e( 'All Statuses', 'ss-core-licenses' ); ?></option>
							<option value="available" <?php selected( $status, 'available' ); ?>><?php esc_html_e( 'Available', 'ss-core-licenses' ); ?></option>
							<option value="reserved" <?php selected( $status, 'reserved' ); ?>><?php esc_html_e( 'Reserved', 'ss-core-licenses' ); ?></option>
							<option value="sold" <?php selected( $status, 'sold' ); ?>><?php esc_html_e( 'Sold', 'ss-core-licenses' ); ?></option>
							<option value="revoked" <?php selected( $status, 'revoked' ); ?>><?php esc_html_e( 'Revoked', 'ss-core-licenses' ); ?></option>
						</select>

						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search...', 'ss-core-licenses' ); ?>">

						<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'ss-core-licenses' ); ?>">
					</form>
				</div>
				<div class="alignright actions">
					<a href="#TB_inline?width=600&height=500&inlineId=ss-add-license-modal" class="thickbox button button-primary">
						<?php esc_html_e( 'Add New License', 'ss-core-licenses' ); ?>
					</a>
				</div>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'License Code', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Product', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Status', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Provider Ref', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Created', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'ss-core-licenses' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $licenses ) ) : ?>
						<tr>
							<td colspan="7"><?php esc_html_e( 'No licenses found.', 'ss-core-licenses' ); ?></td>
						</tr>
					<?php else : ?>
						<?php
						// Initialize encryption for decrypting license codes.
						$encryption = new \SS_Core_Licenses\Crypto\Encryption();
						$encryption->initialize_keys();
						?>
						<?php foreach ( $licenses as $license ) : ?>
							<?php
							$product = wc_get_product( $license['product_id'] );
							$status = $license['status'];
							
							// Decrypt license code.
							$license_code = '';
							if ( ! empty( $license['code_enc'] ) ) {
								$decrypted = $encryption->decrypt( $license['code_enc'] );
								$license_code = $decrypted ? $decrypted : __( 'Decryption failed', 'ss-core-licenses' );
							}
							?>
							<tr>
								<td><?php echo esc_html( $license['id'] ); ?></td>
								<td>
									<code><?php echo esc_html( $license_code ?: '—' ); ?></code>
								</td>
								<td><?php echo $product ? esc_html( $product->get_name() ) : '—'; ?></td>
								<td>
									<strong><?php echo esc_html( ucfirst( $status ) ); ?></strong>
								</td>
								<td><?php echo esc_html( $license['provider_ref'] ?: '—' ); ?></td>
								<td><?php echo esc_html( $license['created_at'] ); ?></td>
								<td>
									<?php if ( 'revoked' !== $license['status'] ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ss_revoke_license&license_id=' . $license['id'] ), 'ss_revoke_license_' . $license['id'] ) ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to revoke this license?', 'ss-core-licenses' ) ); ?>');">
											<?php esc_html_e( 'Revoke', 'ss-core-licenses' ); ?>
										</a>
									<?php else : ?>
										<span class="description">—</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total > $per_page ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						$page_links = paginate_links(
							array(
								'base' => add_query_arg( 'paged', '%#%' ),
								'format' => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total' => ceil( $total / $per_page ),
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
	 * Enqueue Thickbox for modal.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_thickbox( $hook ) {
		// Only load on licenses page.
		// Check both possible hook formats for top-level menu pages.
		$is_licenses_page = (
			'toplevel_page_securesoft-licenses' === $hook ||
			'securesoft_page_securesoft-licenses' === $hook ||
			( isset( $_GET['page'] ) && 'securesoft-licenses' === $_GET['page'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		
		if ( ! $is_licenses_page ) {
			return;
		}

		// Enqueue Thickbox (WordPress built-in modal).
		add_thickbox();
	}

	/**
	 * Handle actions.
	 */
	private function handle_actions() {
		// Actions are handled via admin_post hooks.
	}

	/**
	 * Handle add license.
	 */
	public function handle_add_license() {
		if ( ! current_user_can( 'ss_manage_licenses' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_add_license' );

		$license_code = isset( $_POST['license_code'] ) ? trim( sanitize_text_field( $_POST['license_code'] ) ) : '';
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$provider_ref = isset( $_POST['provider_ref'] ) ? sanitize_text_field( $_POST['provider_ref'] ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'available';

		// Validate inputs.
		if ( empty( $license_code ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( __( 'License code is required.', 'ss-core-licenses' ) ) ) );
			exit;
		}

		if ( ! $product_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( __( 'Product is required.', 'ss-core-licenses' ) ) ) );
			exit;
		}

		// Validate status.
		$valid_statuses = array( 'available', 'reserved', 'sold', 'revoked' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			$status = 'available';
		}

		// Prepare metadata.
		$meta = array();
		$meta['created_manually'] = true;
		$meta['created_by'] = get_current_user_id();

		// Ensure encryption keys are initialized and available.
		$encryption = new \SS_Core_Licenses\Crypto\Encryption();
		$keys_initialized = $encryption->initialize_keys();
		
		// Verify that we have a valid key.
		$test_key = $encryption->get_key();
		if ( ! $test_key ) {
			// Keys don't exist or are invalid. Try to create one automatically.
			$new_key = $encryption->generate_key();
			if ( $new_key ) {
				$keys = get_option( 'ss_core_encryption_keys', array() );
				$keys[1] = base64_encode( $new_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				update_option( 'ss_core_encryption_keys', $keys );
				update_option( 'ss_core_encryption_key_version', 1 );
				
				// Verify the key is now available.
				$test_key = $encryption->get_key();
				if ( ! $test_key ) {
					wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( __( 'Failed to initialize encryption key. Please go to Key Management and generate a key manually.', 'ss-core-licenses' ) ) ) );
					exit;
				}
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( __( 'Failed to generate encryption key. Please check Key Management.', 'ss-core-licenses' ) ) ) );
				exit;
			}
		}

		// Create license using service (it will create with 'available' status initially).
		$license_id = $this->license_service->create_license( $license_code, $product_id, $meta );

		if ( $license_id ) {
			// Update license with provider_ref, status if needed.
			$license_repo = new \SS_Core_Licenses\Licenses\Repository();
			$update_data = array();
			
			if ( ! empty( $provider_ref ) ) {
				$update_data['provider_ref'] = $provider_ref;
			}
			
			if ( 'available' !== $status ) {
				$update_data['status'] = $status;
			}

			if ( ! empty( $update_data ) ) {
				$license_repo->update( $license_id, $update_data );
				
				// Update pool count if status was changed.
				if ( isset( $update_data['status'] ) ) {
					$pool_repo = new \SS_Core_Licenses\Pools\Repository();
					$pool_repo->update_count( $product_id );
				}
			}

			// Log event if status was changed.
			if ( 'available' !== $status ) {
				global $wpdb;
				$table = $wpdb->prefix . 'ss_license_events';
				$wpdb->insert(
					$table,
					array(
						'license_id' => $license_id,
						'type' => 'import',
						'actor_user_id' => get_current_user_id(),
						'meta' => wp_json_encode( array( 'status' => $status, 'manual' => true ) ),
						'created_at' => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%d', '%s', '%s' )
				);
			}

			// Log audit event.
			$this->audit_logger->log(
				get_current_user_id(),
				'license_added_manually',
				'license',
				$license_id,
				array(
					'product_id' => $product_id,
					'status' => $status,
					'provider_ref' => $provider_ref,
				)
			);

			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&added=1' ) );
			exit;
		} else {
			// Get more specific error message.
			global $wpdb;
			$error_message = __( 'Failed to create license.', 'ss-core-licenses' );
			
			// Check for database errors.
			if ( ! empty( $wpdb->last_error ) ) {
				$error_message .= ' ' . __( 'Database error:', 'ss-core-licenses' ) . ' ' . esc_html( $wpdb->last_error );
			} else {
				// Check if encryption might have failed.
				$encryption = new \SS_Core_Licenses\Crypto\Encryption();
				$encryption->initialize_keys();
				$test_key = $encryption->get_key();
				if ( ! $test_key ) {
					$error_message .= ' ' . __( 'Encryption key is not available. Please check Key Management.', 'ss-core-licenses' );
				} else {
					$test_encryption = $encryption->encrypt( 'test' );
					if ( ! $test_encryption ) {
						$error_message .= ' ' . __( 'Encryption failed. Please check your server OpenSSL configuration.', 'ss-core-licenses' );
					}
				}
			}
			
			// Log detailed error for debugging.
			error_log( 'SS Core: License creation failed. Code: ' . substr( $license_code, 0, 10 ) . '..., Product ID: ' . $product_id . ', DB Error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			
			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( $error_message ) ) );
			exit;
		}
	}

	/**
	 * Handle revoke license.
	 */
	public function handle_revoke_license() {
		if ( ! current_user_can( 'ss_manage_licenses' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		$license_id = isset( $_GET['license_id'] ) ? intval( $_GET['license_id'] ) : 0;

		if ( ! $license_id ) {
			wp_die( esc_html__( 'Invalid license ID.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_revoke_license_' . $license_id );

		$result = $this->license_service->revoke_license( $license_id );

		if ( $result ) {
			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&revoked=1' ) );
			exit;
		} else {
			wp_die( esc_html__( 'Failed to revoke license.', 'ss-core-licenses' ) );
		}
	}

}

