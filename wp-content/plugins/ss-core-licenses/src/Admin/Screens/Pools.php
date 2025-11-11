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
		// Get pools.
		$pool_repo = new \SS_Core_Licenses\Pools\Repository();
		$pools = $pool_repo->get_all();

		// Get license counts for each pool.
		$license_repo = new \SS_Core_Licenses\Licenses\Repository();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'License Pools', 'ss-core-licenses' ); ?></h1>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Pool ID', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Available', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Reserved', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Sold', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'ss-core-licenses' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'ss-core-licenses' ); ?></th>
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
}

