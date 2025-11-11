<?php
/**
 * Import admin screen.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Admin\Screens;

use SS_Core_Licenses\Licenses\Service;
use SS_Core_Licenses\Audit\Logger;
use SS_Core_Licenses\Import\Importer;

/**
 * Import screen class.
 */
class Import {

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
		add_action( 'admin_post_ss_import_licenses', array( $this, 'handle_import' ) );
	}

	/**
	 * Add menu page.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'securesoft-licenses',
			__( 'Import Licenses', 'ss-core-licenses' ),
			__( 'Import', 'ss-core-licenses' ),
			'ss_manage_licenses',
			'securesoft-import',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render page.
	 */
	public function render_page() {
		// Get products for selection.
		if ( ! function_exists( 'wc_get_products' ) ) {
			$products = array();
		} else {
			$products = wc_get_products( array( 'limit' => -1 ) );
		}

		// Show success message if import was successful.
		if ( isset( $_GET['imported'] ) && $_GET['imported'] == '1' ) {
			$count = isset( $_GET['count'] ) ? intval( $_GET['count'] ) : 0;
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						// translators: %d: number of imported licenses.
						esc_html( _n( '%d license imported successfully.', '%d licenses imported successfully.', $count, 'ss-core-licenses' ) ),
						$count
					);
					?>
				</p>
			</div>
			<?php
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Licenses', 'ss-core-licenses' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'ss_import_licenses' ); ?>
				<input type="hidden" name="action" value="ss_import_licenses">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="product_id"><?php esc_html_e( 'Product', 'ss-core-licenses' ); ?></label>
						</th>
						<td>
							<select name="product_id" id="product_id" required>
								<option value=""><?php esc_html_e( 'Select a product', 'ss-core-licenses' ); ?></option>
								<?php foreach ( $products as $product ) : ?>
									<option value="<?php echo esc_attr( $product->get_id() ); ?>">
										<?php echo esc_html( $product->get_name() ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Select the WooCommerce product to assign licenses to.', 'ss-core-licenses' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="import_file"><?php esc_html_e( 'Import File', 'ss-core-licenses' ); ?></label>
						</th>
						<td>
							<input type="file" name="import_file" id="import_file" accept=".csv,.xlsx,.xls" required>
							<p class="description">
								<?php esc_html_e( 'Upload a CSV or Excel file containing license codes. Expected format: one license code per line, or CSV with columns: code, provider_ref (optional).', 'ss-core-licenses' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="provider_ref_column"><?php esc_html_e( 'Provider Reference Column', 'ss-core-licenses' ); ?></label>
						</th>
						<td>
							<input type="text" name="provider_ref_column" id="provider_ref_column" value="provider_ref" placeholder="provider_ref">
							<p class="description"><?php esc_html_e( 'Column name for provider reference (optional).', 'ss-core-licenses' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Import Licenses', 'ss-core-licenses' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Required File Structure', 'ss-core-licenses' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Your import file must follow one of the formats below. The file can be CSV (.csv) or Excel (.xlsx, .xls).', 'ss-core-licenses' ); ?>
			</p>

			<h3><?php esc_html_e( 'Format 1: CSV with Header Row (Recommended)', 'ss-core-licenses' ); ?></h3>
			<div class="ss-import-preview" style="margin: 20px 0;">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="background-color: #f0f0f1; font-weight: 600;">
								<?php esc_html_e( 'code', 'ss-core-licenses' ); ?>
								<span style="color: #d63638;">*</span>
							</th>
							<th style="background-color: #f0f0f1; font-weight: 600;">
								<?php esc_html_e( 'provider_ref', 'ss-core-licenses' ); ?>
								<span style="color: #72aee6;">(<?php esc_html_e( 'optional', 'ss-core-licenses' ); ?>)</span>
							</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>XXXXX-XXXXX-XXXXX-XXXXX-XXXXX</td>
							<td>REF-001</td>
						</tr>
						<tr>
							<td>YYYYY-YYYYY-YYYYY-YYYYY-YYYYY</td>
							<td>REF-002</td>
						</tr>
						<tr>
							<td>ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ</td>
							<td></td>
						</tr>
					</tbody>
				</table>
				<p class="description" style="margin-top: 10px;">
					<strong><?php esc_html_e( 'Note:', 'ss-core-licenses' ); ?></strong>
					<?php esc_html_e( 'The "code" column is required and must contain the license key. Alternative column names accepted: license, license_code, key, activation_key, product_key.', 'ss-core-licenses' ); ?>
				</p>
			</div>

			<h3><?php esc_html_e( 'Format 2: Simple List (No Header)', 'ss-core-licenses' ); ?></h3>
			<div class="ss-import-preview" style="margin: 20px 0;">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="background-color: #f0f0f1; font-weight: 600;">
								<?php esc_html_e( 'License Code', 'ss-core-licenses' ); ?>
								<span style="color: #d63638;">*</span>
							</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>XXXXX-XXXXX-XXXXX-XXXXX-XXXXX</td>
						</tr>
						<tr>
							<td>YYYYY-YYYYY-YYYYY-YYYYY-YYYYY</td>
						</tr>
						<tr>
							<td>ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ</td>
						</tr>
					</tbody>
				</table>
				<p class="description" style="margin-top: 10px;">
					<?php esc_html_e( 'For simple files without headers, place one license code per line. The first column will be used as the license code.', 'ss-core-licenses' ); ?>
				</p>
			</div>

			<h2><?php esc_html_e( 'Import Instructions', 'ss-core-licenses' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Select the WooCommerce product to assign licenses to.', 'ss-core-licenses' ); ?></li>
				<li><?php esc_html_e( 'Upload a CSV or Excel file with license codes.', 'ss-core-licenses' ); ?></li>
				<li><?php esc_html_e( 'The import will encrypt and store all licenses.', 'ss-core-licenses' ); ?></li>
				<li><?php esc_html_e( 'Large imports will be processed in the background.', 'ss-core-licenses' ); ?></li>
			</ol>
		</div>
		<?php
	}

	/**
	 * Handle import.
	 */
	public function handle_import() {
		if ( ! current_user_can( 'ss_manage_licenses' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_import_licenses' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$provider_ref_column = isset( $_POST['provider_ref_column'] ) ? sanitize_text_field( $_POST['provider_ref_column'] ) : 'provider_ref';

		if ( ! $product_id ) {
			wp_die( esc_html__( 'Invalid product ID.', 'ss-core-licenses' ) );
		}

		if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_die( esc_html__( 'File upload failed.', 'ss-core-licenses' ) );
		}

		$file = $_FILES['import_file'];
		$importer = new Importer( $this->license_service, $this->audit_logger );

		$result = $importer->import_file( $file, $product_id, $provider_ref_column );

		if ( $result['success'] ) {
			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-import&imported=1&count=' . $result['count'] ) );
			exit;
		} else {
			wp_die( esc_html( $result['message'] ) );
		}
	}
}

