<?php
/**
 * License pools admin screen.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Admin\Screens;

use SS_Core_Licenses\Licenses\Service;
use SS_Core_Licenses\Audit\Logger;

/**
 * Pools screen class.
 */
class Pools {

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
		add_action( 'admin_post_ss_recount_pool', array( $this, 'handle_recount_pool' ) );
		add_action( 'admin_post_ss_export_pools', array( $this, 'handle_export' ) );
		add_action( 'admin_post_ss_save_pool_filter', array( $this, 'handle_save_filter' ) );
		add_action( 'admin_post_ss_delete_pool_filter', array( $this, 'handle_delete_filter' ) );
	}

	/**
	 * Add menu page.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'securesoft-licenses',
			__( 'License Pools', 'ss-core-licenses' ),
			__( 'License Pools', 'ss-core-licenses' ),
			'ss_manage_licenses',
			'securesoft-pools',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render page.
	 */
	public function render_page() {
		// Handle saved filter.
		$product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;
		if ( isset( $_GET['filter_id'] ) ) {
			$filter_id = intval( $_GET['filter_id'] );
			$saved_filters = get_user_meta( get_current_user_id(), 'ss_saved_pool_filters', true );
			if ( is_array( $saved_filters ) && isset( $saved_filters[ $filter_id ] ) ) {
				$filter_data = $saved_filters[ $filter_id ];
				$product_id = isset( $filter_data['product_id'] ) ? intval( $filter_data['product_id'] ) : 0;
			}
		}
		
		// Get sorting parameters.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'updated_at';
		$order = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';

		// Get saved filters.
		$saved_filters = get_user_meta( get_current_user_id(), 'ss_saved_pool_filters', true );
		if ( ! is_array( $saved_filters ) ) {
			$saved_filters = array();
		}

		// Get pools.
		$pool_repo = new \SS_Core_Licenses\Pools\Repository();
		$all_pools = $pool_repo->get_all(
			array(
				'orderby' => $orderby,
				'order' => $order,
			)
		);

		// Filter pools by product.
		$pools = $all_pools;
		if ( $product_id > 0 ) {
			$pools = array_filter( $pools, function( $pool ) use ( $product_id ) {
				return $pool['product_id'] == $product_id;
			} );
		}

		// Get license counts for each pool.
		$license_repo = new \SS_Core_Licenses\Licenses\Repository();

		// Get products for filter.
		if ( ! function_exists( 'wc_get_products' ) ) {
			$products = array();
		} else {
			$products = wc_get_products( array( 'limit' => -1 ) );
		}

		// Show success messages.
		if ( isset( $_GET['recounted'] ) && $_GET['recounted'] == 1 ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Pool recounted successfully.', 'ss-core-licenses' ) . '</p></div>';
		}
		if ( isset( $_GET['filter_saved'] ) && $_GET['filter_saved'] == 1 ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Filter saved successfully.', 'ss-core-licenses' ) . '</p></div>';
		}
		if ( isset( $_GET['filter_deleted'] ) && $_GET['filter_deleted'] == 1 ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Filter deleted successfully.', 'ss-core-licenses' ) . '</p></div>';
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'License Pools', 'ss-core-licenses' ); ?></h1>

			<!-- Filters -->
			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<form method="get" action="" id="ss-pool-filters-form" style="display: inline-block;">
						<input type="hidden" name="page" value="securesoft-pools">
						<?php if ( $orderby && 'updated_at' !== $orderby ) : ?>
							<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
						<?php endif; ?>
						<?php if ( $order && 'DESC' !== $order ) : ?>
							<input type="hidden" name="order" value="<?php echo esc_attr( strtolower( $order ) ); ?>">
						<?php endif; ?>
						
						<?php if ( ! empty( $saved_filters ) ) : ?>
							<select name="saved_filter" id="ss-saved-pool-filter" onchange="if(this.value) { window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=securesoft-pools' ) ); ?>&filter_id=' + this.value; }">
								<option value=""><?php esc_html_e( 'Saved Filters...', 'ss-core-licenses' ); ?></option>
								<?php foreach ( $saved_filters as $id => $filter ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>">
										<?php echo esc_html( $filter['name'] ); ?>
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

						<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'ss-core-licenses' ); ?>">
						
						<?php if ( $product_id > 0 ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=securesoft-pools' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'ss-core-licenses' ); ?></a>
							<button type="button" class="button" onclick="document.getElementById('ss-save-pool-filter-modal').style.display='block';"><?php esc_html_e( 'Save Filter', 'ss-core-licenses' ); ?></button>
						<?php endif; ?>
					</form>
				</div>
				<div class="alignright actions">
					<?php if ( ! empty( $pools ) ) : ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ss_export_pools' . ( $product_id ? '&product_id=' . $product_id : '' ) ), 'ss_export_pools' ) ); ?>" class="button">
							<?php esc_html_e( 'Export', 'ss-core-licenses' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<?php echo $this->get_column_header( 'product_id', __( 'Product', 'ss-core-licenses' ), $orderby, $order ); ?>
						<?php echo $this->get_column_header( 'id', __( 'Pool ID', 'ss-core-licenses' ), $orderby, $order ); ?>
						<th class="manage-column"><?php esc_html_e( 'Available', 'ss-core-licenses' ); ?></th>
						<th class="manage-column"><?php esc_html_e( 'Reserved', 'ss-core-licenses' ); ?></th>
						<th class="manage-column"><?php esc_html_e( 'Sold', 'ss-core-licenses' ); ?></th>
						<?php echo $this->get_column_header( 'updated_at', __( 'Updated', 'ss-core-licenses' ), $orderby, $order ); ?>
						<th class="manage-column"><?php esc_html_e( 'Actions', 'ss-core-licenses' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $pools ) ) : ?>
						<tr>
							<td colspan="7"><?php esc_html_e( 'No license pools found.', 'ss-core-licenses' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $pools as $pool ) : ?>
							<?php
							$product = wc_get_product( $pool['product_id'] );
							$available = $license_repo->count_by_status( $pool['product_id'], 'available' );
							$reserved = $license_repo->count_by_status( $pool['product_id'], 'reserved' );
							$sold = $license_repo->count_by_status( $pool['product_id'], 'sold' );
							?>
							<tr>
								<td><?php echo $product ? esc_html( $product->get_name() ) : '—'; ?></td>
								<td><?php echo esc_html( $pool['id'] ); ?></td>
								<td><strong><?php echo esc_html( $available ); ?></strong></td>
								<td><strong><?php echo esc_html( $reserved ); ?></strong></td>
								<td><strong><?php echo esc_html( $sold ); ?></strong></td>
								<td><?php echo esc_html( $pool['updated_at'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ss_recount_pool&product_id=' . $pool['product_id'] ), 'ss_recount_pool_' . $pool['product_id'] ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Recount', 'ss-core-licenses' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Save Filter Modal -->
			<div id="ss-save-pool-filter-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; border:1px solid #ccc; z-index:100000;">
				<h2><?php esc_html_e( 'Save Filter', 'ss-core-licenses' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ss_save_pool_filter' ); ?>
					<input type="hidden" name="action" value="ss_save_pool_filter">
					<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
					<p>
						<label><?php esc_html_e( 'Filter Name:', 'ss-core-licenses' ); ?>
							<input type="text" name="filter_name" required class="regular-text">
						</label>
					</p>
					<p>
						<?php submit_button( __( 'Save', 'ss-core-licenses' ), 'primary', 'submit', false ); ?>
						<button type="button" class="button" onclick="document.getElementById('ss-save-pool-filter-modal').style.display='none';"><?php esc_html_e( 'Cancel', 'ss-core-licenses' ); ?></button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle recount pool.
	 */
	public function handle_recount_pool() {
		if ( ! current_user_can( 'ss_manage_licenses' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		$product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_die( esc_html__( 'Invalid product ID.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_recount_pool_' . $product_id );

		$pool_repo = new \SS_Core_Licenses\Pools\Repository();
		$pool_repo->update_count( $product_id );

		// Log audit event.
		$this->audit_logger->log(
			get_current_user_id(),
			'pool_recounted',
			'pool',
			$product_id,
			array(
				'product_id' => $product_id,
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=securesoft-pools&recounted=1' ) );
		exit;
	}

	/**
	 * Handle export.
	 */
	public function handle_export() {
		if ( ! current_user_can( 'ss_manage_licenses' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_export_pools' );

		$product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;

		// Get pools.
		$pool_repo = new \SS_Core_Licenses\Pools\Repository();
		$all_pools = $pool_repo->get_all();

		// Filter pools by product.
		$pools = $all_pools;
		if ( $product_id > 0 ) {
			$pools = array_filter( $pools, function( $pool ) use ( $product_id ) {
				return $pool['product_id'] == $product_id;
			} );
		}

		// Get license counts.
		$license_repo = new \SS_Core_Licenses\Licenses\Repository();

		// Generate CSV.
		$filename = 'license-pools-' . date( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		// Add UTF-8 BOM for Excel compatibility.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Header.
		fputcsv( $output, array( 'Product', 'Pool ID', 'Available', 'Reserved', 'Sold', 'Updated' ) );

		// Rows.
		foreach ( $pools as $pool ) {
			$product = wc_get_product( $pool['product_id'] );
			$available = $license_repo->count_by_status( $pool['product_id'], 'available' );
			$reserved = $license_repo->count_by_status( $pool['product_id'], 'reserved' );
			$sold = $license_repo->count_by_status( $pool['product_id'], 'sold' );
			
			fputcsv(
				$output,
				array(
					$product ? $product->get_name() : '',
					$pool['id'],
					$available,
					$reserved,
					$sold,
					$pool['updated_at'],
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

		check_admin_referer( 'ss_save_pool_filter' );

		$filter_name = isset( $_POST['filter_name'] ) ? sanitize_text_field( $_POST['filter_name'] ) : '';
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

		if ( empty( $filter_name ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-pools&error=1' ) );
			exit;
		}

		$saved_filters = get_user_meta( get_current_user_id(), 'ss_saved_pool_filters', true );
		if ( ! is_array( $saved_filters ) ) {
			$saved_filters = array();
		}

		$filter_id = time();
		$saved_filters[ $filter_id ] = array(
			'name' => $filter_name,
			'product_id' => $product_id,
		);

		update_user_meta( get_current_user_id(), 'ss_saved_pool_filters', $saved_filters );

		wp_safe_redirect( admin_url( 'admin.php?page=securesoft-pools&filter_saved=1' ) );
		exit;
	}

	/**
	 * Handle delete filter.
	 */
	public function handle_delete_filter() {
		if ( ! current_user_can( 'ss_manage_licenses' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_delete_pool_filter' );

		$filter_id = isset( $_GET['filter_id'] ) ? intval( $_GET['filter_id'] ) : 0;

		if ( $filter_id > 0 ) {
			$saved_filters = get_user_meta( get_current_user_id(), 'ss_saved_pool_filters', true );
			if ( is_array( $saved_filters ) && isset( $saved_filters[ $filter_id ] ) ) {
				unset( $saved_filters[ $filter_id ] );
				update_user_meta( get_current_user_id(), 'ss_saved_pool_filters', $saved_filters );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=securesoft-pools&filter_deleted=1' ) );
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

