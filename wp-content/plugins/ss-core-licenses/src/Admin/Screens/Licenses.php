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
		add_action( 'admin_post_ss_bulk_licenses', array( $this, 'handle_bulk_licenses' ) );
		add_action( 'admin_post_ss_export_licenses', array( $this, 'handle_export_licenses' ) );
		add_action( 'admin_post_ss_save_filter', array( $this, 'handle_save_filter' ) );
		add_action( 'admin_post_ss_delete_filter', array( $this, 'handle_delete_filter' ) );
		add_action( 'admin_post_ss_transfer_licenses', array( $this, 'handle_transfer_licenses' ) );
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
		$provider_ref = isset( $_GET['provider_ref'] ) ? sanitize_text_field( $_GET['provider_ref'] ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
		$paged = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
		$per_page = 20;
		
		// Get sorting parameters.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at';
		$order = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';
		
		// Get saved filters.
		$saved_filters = get_user_meta( get_current_user_id(), 'ss_saved_license_filters', true );
		if ( ! is_array( $saved_filters ) ) {
			$saved_filters = array();
		}
		
		// Apply saved filter if requested.
		if ( isset( $_GET['filter_id'] ) && ! empty( $saved_filters[ $_GET['filter_id'] ] ) ) {
			$filter_data = $saved_filters[ sanitize_text_field( $_GET['filter_id'] ) ];
			$product_id = isset( $filter_data['product_id'] ) ? intval( $filter_data['product_id'] ) : 0;
			$status = isset( $filter_data['status'] ) ? sanitize_text_field( $filter_data['status'] ) : '';
			$search = isset( $filter_data['search'] ) ? sanitize_text_field( $filter_data['search'] ) : '';
			$provider_ref = isset( $filter_data['provider_ref'] ) ? sanitize_text_field( $filter_data['provider_ref'] ) : '';
			$date_from = isset( $filter_data['date_from'] ) ? sanitize_text_field( $filter_data['date_from'] ) : '';
			$date_to = isset( $filter_data['date_to'] ) ? sanitize_text_field( $filter_data['date_to'] ) : '';
		}

		// Get licenses.
		$license_repo = new \SS_Core_Licenses\Licenses\Repository();
		$search_args = array(
				'product_id' => $product_id,
				'status' => $status,
				'search' => $search,
			'provider_ref' => $provider_ref,
			'date_from' => $date_from,
			'date_to' => $date_to,
				'limit' => $per_page,
				'offset' => ( $paged - 1 ) * $per_page,
				'orderby' => $orderby,
				'order' => $order,
		);
		$licenses = $license_repo->search( $search_args );

		// Get total count.
		$count_args = $search_args;
		$count_args['limit'] = -1;
		$total = count( $license_repo->search( $count_args ) );

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
		if ( isset( $_GET['bulk_deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html( sprintf( __( '%d license(s) deleted successfully.', 'ss-core-licenses' ), intval( $_GET['bulk_deleted'] ) ) );
			echo '</p></div>';
		}
		if ( isset( $_GET['bulk_updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html( sprintf( __( '%d license(s) updated successfully.', 'ss-core-licenses' ), intval( $_GET['bulk_updated'] ) ) );
			echo '</p></div>';
		}
		if ( isset( $_GET['bulk_transferred'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html( sprintf( __( '%d license(s) transferred successfully.', 'ss-core-licenses' ), intval( $_GET['bulk_transferred'] ) ) );
			echo '</p></div>';
		}
		if ( isset( $_GET['filter_saved'] ) && '1' === $_GET['filter_saved'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Filter saved successfully.', 'ss-core-licenses' );
			echo '</p></div>';
		}
		if ( isset( $_GET['filter_deleted'] ) && '1' === $_GET['filter_deleted'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Filter deleted successfully.', 'ss-core-licenses' );
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
				<div class="alignleft actions bulkactions">
					<form method="get" action="" id="ss-license-filters-form" style="display: inline-block;">
				<input type="hidden" name="page" value="securesoft-licenses">
						<?php if ( $orderby && 'created_at' !== $orderby ) : ?>
							<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
						<?php endif; ?>
						<?php if ( $order && 'DESC' !== $order ) : ?>
							<input type="hidden" name="order" value="<?php echo esc_attr( strtolower( $order ) ); ?>">
						<?php endif; ?>
						
						<?php if ( ! empty( $saved_filters ) ) : ?>
							<select name="saved_filter" id="ss-saved-filter" onchange="if(this.value) { window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=securesoft-licenses' ) ); ?>&filter_id=' + this.value; }">
								<option value=""><?php esc_html_e( 'Saved Filters...', 'ss-core-licenses' ); ?></option>
								<?php foreach ( $saved_filters as $filter_id => $filter_data ) : ?>
									<option value="<?php echo esc_attr( $filter_id ); ?>">
										<?php echo esc_html( $filter_data['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
						
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

						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search ID or Provider Ref...', 'ss-core-licenses' ); ?>">
						
						<input type="text" name="provider_ref" value="<?php echo esc_attr( $provider_ref ); ?>" placeholder="<?php esc_attr_e( 'Provider Ref', 'ss-core-licenses' ); ?>">
						
						<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'From Date', 'ss-core-licenses' ); ?>">
						
						<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'To Date', 'ss-core-licenses' ); ?>">

						<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'ss-core-licenses' ); ?>">
						
						<?php if ( $product_id || $status || $search || $provider_ref || $date_from || $date_to ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=securesoft-licenses' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'ss-core-licenses' ); ?></a>
							<button type="button" class="button" onclick="document.getElementById('ss-save-filter-modal').style.display='block';"><?php esc_html_e( 'Save Filter', 'ss-core-licenses' ); ?></button>
						<?php endif; ?>
					</form>
				</div>
			</div>
			
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ss-bulk-form-main">
				<?php wp_nonce_field( 'ss_bulk_licenses' ); ?>
				<input type="hidden" name="action" value="ss_bulk_licenses">
				
				<!-- Bulk Actions -->
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="bulk_action" id="bulk-action-selector">
							<option value=""><?php esc_html_e( 'Bulk Actions', 'ss-core-licenses' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'ss-core-licenses' ); ?></option>
							<option value="change_status"><?php esc_html_e( 'Change Status', 'ss-core-licenses' ); ?></option>
							<option value="transfer"><?php esc_html_e( 'Transfer to Product', 'ss-core-licenses' ); ?></option>
						</select>
						<select name="new_status" id="bulk-status-selector" style="display:none;">
							<option value="available"><?php esc_html_e( 'Available', 'ss-core-licenses' ); ?></option>
							<option value="reserved"><?php esc_html_e( 'Reserved', 'ss-core-licenses' ); ?></option>
							<option value="sold"><?php esc_html_e( 'Sold', 'ss-core-licenses' ); ?></option>
							<option value="revoked"><?php esc_html_e( 'Revoked', 'ss-core-licenses' ); ?></option>
						</select>
						<select name="target_product_id" id="bulk-product-selector" style="display:none;">
							<option value=""><?php esc_html_e( 'Select Product', 'ss-core-licenses' ); ?></option>
							<?php foreach ( $products as $product ) : ?>
								<option value="<?php echo esc_attr( $product->get_id() ); ?>">
									<?php echo esc_html( $product->get_name() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input type="submit" class="button action" id="doaction" value="<?php esc_attr_e( 'Apply', 'ss-core-licenses' ); ?>" onclick="return ssBulkAction();">
					</div>
					<div class="alignright actions">
						<?php if ( ! empty( $licenses ) ) : ?>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ss_export_licenses' . ( $product_id ? '&product_id=' . $product_id : '' ) . ( $status ? '&status=' . $status : '' ) . ( $search ? '&s=' . urlencode( $search ) : '' ) . ( $provider_ref ? '&provider_ref=' . urlencode( $provider_ref ) : '' ) . ( $date_from ? '&date_from=' . urlencode( $date_from ) : '' ) . ( $date_to ? '&date_to=' . urlencode( $date_to ) : '' ) ), 'ss_export_licenses' ) ); ?>" class="button">
								<?php esc_html_e( 'Export', 'ss-core-licenses' ); ?>
							</a>
						<?php endif; ?>
						<a href="#TB_inline?width=600&height=500&inlineId=ss-add-license-modal" class="thickbox button button-primary">
							<?php esc_html_e( 'Add New License', 'ss-core-licenses' ); ?>
						</a>
					</div>
				</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
							<td class="manage-column column-cb check-column">
								<input type="checkbox" id="cb-select-all">
							</td>
						<?php echo $this->get_column_header( 'id', __( 'ID', 'ss-core-licenses' ), $orderby, $order ); ?>
							<th class="manage-column"><?php esc_html_e( 'License Code', 'ss-core-licenses' ); ?></th>
						<?php echo $this->get_column_header( 'product_id', __( 'Product', 'ss-core-licenses' ), $orderby, $order ); ?>
						<?php echo $this->get_column_header( 'status', __( 'Status', 'ss-core-licenses' ), $orderby, $order ); ?>
						<?php echo $this->get_column_header( 'provider_ref', __( 'Provider Ref', 'ss-core-licenses' ), $orderby, $order ); ?>
						<?php echo $this->get_column_header( 'created_at', __( 'Created', 'ss-core-licenses' ), $orderby, $order ); ?>
						<th class="manage-column"><?php esc_html_e( 'Actions', 'ss-core-licenses' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $licenses ) ) : ?>
						<tr>
								<td colspan="8"><?php esc_html_e( 'No licenses found.', 'ss-core-licenses' ); ?></td>
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
									<th scope="row" class="check-column">
										<input type="checkbox" name="license_ids[]" value="<?php echo esc_attr( $license['id'] ); ?>" class="license-checkbox">
									</th>
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
			</form>

			<!-- Save Filter Modal -->
			<div id="ss-save-filter-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; border:1px solid #ccc; z-index:100000;">
				<h2><?php esc_html_e( 'Save Filter', 'ss-core-licenses' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ss_save_filter' ); ?>
					<input type="hidden" name="action" value="ss_save_filter">
					<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
					<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
					<input type="hidden" name="search" value="<?php echo esc_attr( $search ); ?>">
					<input type="hidden" name="provider_ref" value="<?php echo esc_attr( $provider_ref ); ?>">
					<input type="hidden" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
					<input type="hidden" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
					<p>
						<label><?php esc_html_e( 'Filter Name:', 'ss-core-licenses' ); ?>
							<input type="text" name="filter_name" required class="regular-text">
						</label>
					</p>
					<p>
						<?php submit_button( __( 'Save', 'ss-core-licenses' ), 'primary', 'submit', false ); ?>
						<button type="button" class="button" onclick="document.getElementById('ss-save-filter-modal').style.display='none';"><?php esc_html_e( 'Cancel', 'ss-core-licenses' ); ?></button>
					</p>
				</form>
			</div>

			<script>
			// Select All Checkbox
			document.getElementById('cb-select-all')?.addEventListener('change', function(e) {
				var checkboxes = document.querySelectorAll('.license-checkbox');
				checkboxes.forEach(function(cb) {
					cb.checked = e.target.checked;
				});
			});

			// Bulk Action Selector
			document.getElementById('bulk-action-selector')?.addEventListener('change', function(e) {
				var statusSelector = document.getElementById('bulk-status-selector');
				var productSelector = document.getElementById('bulk-product-selector');
				
				if (e.target.value === 'change_status') {
					statusSelector.style.display = 'inline-block';
					productSelector.style.display = 'none';
				} else if (e.target.value === 'transfer') {
					statusSelector.style.display = 'none';
					productSelector.style.display = 'inline-block';
				} else {
					statusSelector.style.display = 'none';
					productSelector.style.display = 'none';
				}
			});

			// Bulk Action Handler
			function ssBulkAction() {
				var form = document.getElementById('ss-bulk-form-main');
				var action = document.getElementById('bulk-action-selector').value;
				var checkboxes = document.querySelectorAll('.license-checkbox:checked');
				
				if (!action) {
					alert('<?php echo esc_js( __( 'Please select an action.', 'ss-core-licenses' ) ); ?>');
					return false;
				}
				
				if (checkboxes.length === 0) {
					alert('<?php echo esc_js( __( 'Please select at least one license.', 'ss-core-licenses' ) ); ?>');
					return false;
				}
				
				if (action === 'delete') {
					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete the selected licenses?', 'ss-core-licenses' ) ); ?>')) {
						return false;
					}
				}
				
				if (action === 'change_status') {
					var statusValue = document.getElementById('bulk-status-selector').value;
					if (!statusValue) {
						alert('<?php echo esc_js( __( 'Please select a status.', 'ss-core-licenses' ) ); ?>');
						return false;
					}
				} else if (action === 'transfer') {
					var productValue = document.getElementById('bulk-product-selector').value;
					if (!productValue) {
						alert('<?php echo esc_js( __( 'Please select a target product.', 'ss-core-licenses' ) ); ?>');
						return false;
					}
				}
				
				return true;
			}
			</script>

			<?php if ( $total > $per_page ) : ?>
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

	/**
	 * Handle bulk licenses action.
	 */
	public function handle_bulk_licenses() {
		if ( ! current_user_can( 'ss_manage_licenses' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_bulk_licenses' );

		$license_ids = isset( $_POST['license_ids'] ) ? array_map( 'intval', $_POST['license_ids'] ) : array();
		$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : '';

		if ( empty( $license_ids ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( __( 'No licenses selected.', 'ss-core-licenses' ) ) ) );
			exit;
		}

		$license_repo = new \SS_Core_Licenses\Licenses\Repository();
		$pool_repo = new \SS_Core_Licenses\Pools\Repository();
		$updated = 0;
		$deleted = 0;
		$transferred = 0;

		switch ( $bulk_action ) {
			case 'delete':
				foreach ( $license_ids as $license_id ) {
					$license = $license_repo->get_by_id( $license_id );
					if ( $license && $license_repo->delete( $license_id ) ) {
						$deleted++;
						// Update pool count.
						$pool_repo->update_count( $license['product_id'] );
						// Log audit event.
						$this->audit_logger->log(
							get_current_user_id(),
							'license_deleted',
							'license',
							$license_id,
							array( 'bulk' => true )
						);
					}
				}
				wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&bulk_deleted=' . $deleted ) );
				exit;

			case 'change_status':
				$new_status = isset( $_POST['new_status'] ) ? sanitize_text_field( $_POST['new_status'] ) : '';
				$valid_statuses = array( 'available', 'reserved', 'sold', 'revoked' );
				if ( ! in_array( $new_status, $valid_statuses, true ) ) {
					wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( __( 'Invalid status.', 'ss-core-licenses' ) ) ) );
					exit;
				}
				foreach ( $license_ids as $license_id ) {
					$license = $license_repo->get_by_id( $license_id );
					if ( $license && $license_repo->update( $license_id, array( 'status' => $new_status ) ) ) {
						$updated++;
						// Update pool count.
						$pool_repo->update_count( $license['product_id'] );
						// Log audit event.
						$this->audit_logger->log(
							get_current_user_id(),
							'license_status_changed',
							'license',
							$license_id,
							array(
								'old_status' => $license['status'],
								'new_status' => $new_status,
								'bulk' => true,
							)
						);
					}
				}
				wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&bulk_updated=' . $updated ) );
				exit;

			case 'transfer':
				$target_product_id = isset( $_POST['target_product_id'] ) ? intval( $_POST['target_product_id'] ) : 0;
				if ( ! $target_product_id ) {
					wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( __( 'Please select a target product.', 'ss-core-licenses' ) ) ) );
					exit;
				}
				foreach ( $license_ids as $license_id ) {
					$license = $license_repo->get_by_id( $license_id );
					if ( $license && $license_repo->update( $license_id, array( 'product_id' => $target_product_id ) ) ) {
						$transferred++;
						// Update pool counts for both products.
						$pool_repo->update_count( $license['product_id'] );
						$pool_repo->update_count( $target_product_id );
						// Log audit event.
						$this->audit_logger->log(
							get_current_user_id(),
							'license_transferred',
							'license',
							$license_id,
							array(
								'old_product_id' => $license['product_id'],
								'new_product_id' => $target_product_id,
								'bulk' => true,
							)
						);
					}
				}
				wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&bulk_transferred=' . $transferred ) );
				exit;

			default:
				wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( __( 'Invalid action.', 'ss-core-licenses' ) ) ) );
				exit;
		}
	}

	/**
	 * Handle export licenses.
	 */
	public function handle_export_licenses() {
		if ( ! current_user_can( 'ss_manage_licenses' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_export_licenses' );

		// Get filters.
		$product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;
		$status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$provider_ref = isset( $_GET['provider_ref'] ) ? sanitize_text_field( $_GET['provider_ref'] ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';

		// Get licenses.
		$license_repo = new \SS_Core_Licenses\Licenses\Repository();
		$licenses = $license_repo->search(
			array(
				'product_id' => $product_id,
				'status' => $status,
				'search' => $search,
				'provider_ref' => $provider_ref,
				'date_from' => $date_from,
				'date_to' => $date_to,
				'limit' => -1,
			)
		);

		// Generate CSV.
		$filename = 'licenses-export-' . date( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		// Add BOM for UTF-8.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Header.
		fputcsv( $output, array( 'ID', 'License Code', 'Product ID', 'Product Name', 'Status', 'Provider Ref', 'Created At' ) );

		// Initialize encryption.
		$encryption = new \SS_Core_Licenses\Crypto\Encryption();
		$encryption->initialize_keys();

		// Rows.
		foreach ( $licenses as $license ) {
			$product = wc_get_product( $license['product_id'] );
			$license_code = '';
			if ( ! empty( $license['code_enc'] ) ) {
				$decrypted = $encryption->decrypt( $license['code_enc'] );
				$license_code = $decrypted ? $decrypted : '';
			}
			fputcsv(
				$output,
				array(
					$license['id'],
					$license_code,
					$license['product_id'],
					$product ? $product->get_name() : '',
					$license['status'],
					$license['provider_ref'] ?: '',
					$license['created_at'],
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
		if ( ! current_user_can( 'ss_manage_licenses' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_save_filter' );

		$filter_name = isset( $_POST['filter_name'] ) ? sanitize_text_field( $_POST['filter_name'] ) : '';
		if ( empty( $filter_name ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( __( 'Filter name is required.', 'ss-core-licenses' ) ) ) );
			exit;
		}

		$filters = get_user_meta( get_current_user_id(), 'ss_saved_license_filters', true );
		if ( ! is_array( $filters ) ) {
			$filters = array();
		}

		$filter_id = sanitize_key( $filter_name ) . '_' . time();
		$filters[ $filter_id ] = array(
			'name' => $filter_name,
			'product_id' => isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0,
			'status' => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '',
			'search' => isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '',
			'provider_ref' => isset( $_POST['provider_ref'] ) ? sanitize_text_field( $_POST['provider_ref'] ) : '',
			'date_from' => isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : '',
			'date_to' => isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : '',
		);

		update_user_meta( get_current_user_id(), 'ss_saved_license_filters', $filters );

		wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&filter_saved=1' ) );
		exit;
	}

	/**
	 * Handle delete filter.
	 */
	public function handle_delete_filter() {
		if ( ! current_user_can( 'ss_manage_licenses' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_delete_filter' );

		$filter_id = isset( $_GET['filter_id'] ) ? sanitize_text_field( $_GET['filter_id'] ) : '';
		if ( empty( $filter_id ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( __( 'Filter ID is required.', 'ss-core-licenses' ) ) ) );
			exit;
		}

		$filters = get_user_meta( get_current_user_id(), 'ss_saved_license_filters', true );
		if ( is_array( $filters ) && isset( $filters[ $filter_id ] ) ) {
			unset( $filters[ $filter_id ] );
			update_user_meta( get_current_user_id(), 'ss_saved_license_filters', $filters );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&filter_deleted=1' ) );
		exit;
	}

	/**
	 * Handle transfer licenses.
	 */
	public function handle_transfer_licenses() {
		if ( ! current_user_can( 'ss_manage_licenses' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_transfer_licenses' );

		$license_ids = isset( $_POST['license_ids'] ) ? array_map( 'intval', $_POST['license_ids'] ) : array();
		$target_product_id = isset( $_POST['target_product_id'] ) ? intval( $_POST['target_product_id'] ) : 0;

		if ( empty( $license_ids ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( __( 'No licenses selected.', 'ss-core-licenses' ) ) ) );
			exit;
		}

		if ( ! $target_product_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&error=' . urlencode( __( 'Please select a target product.', 'ss-core-licenses' ) ) ) );
			exit;
		}

		$license_repo = new \SS_Core_Licenses\Licenses\Repository();
		$pool_repo = new \SS_Core_Licenses\Pools\Repository();
		$transferred = 0;

		foreach ( $license_ids as $license_id ) {
			$license = $license_repo->get_by_id( $license_id );
			if ( $license && $license_repo->update( $license_id, array( 'product_id' => $target_product_id ) ) ) {
				$transferred++;
				// Update pool counts.
				$pool_repo->update_count( $license['product_id'] );
				$pool_repo->update_count( $target_product_id );
				// Log audit event.
				$this->audit_logger->log(
					get_current_user_id(),
					'license_transferred',
					'license',
					$license_id,
					array(
						'old_product_id' => $license['product_id'],
						'new_product_id' => $target_product_id,
					)
				);
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=securesoft-licenses&transferred=' . $transferred ) );
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

