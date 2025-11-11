<?php
/**
 * Key management admin screen.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses\Admin\Screens;

use SS_Core_Licenses\Crypto\Encryption;
use SS_Core_Licenses\Audit\Logger;
use SS_Core_Licenses\KeyStore\Manager;

/**
 * Keys screen class.
 */
class Keys {

	/**
	 * Encryption instance.
	 *
	 * @var Encryption
	 */
	private $encryption;

	/**
	 * Audit logger instance.
	 *
	 * @var Logger
	 */
	private $audit_logger;

	/**
	 * Constructor.
	 *
	 * @param Encryption $encryption   Encryption instance.
	 * @param Logger     $audit_logger Audit logger instance.
	 */
	public function __construct( Encryption $encryption, Logger $audit_logger ) {
		$this->encryption = $encryption;
		$this->audit_logger = $audit_logger;

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_ss_generate_key', array( $this, 'handle_generate_key' ) );
		add_action( 'admin_post_ss_rotate_keys', array( $this, 'handle_rotate_keys' ) );
		add_action( 'admin_post_ss_backup_keys', array( $this, 'handle_backup_keys' ) );
	}

	/**
	 * Add menu page.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'securesoft-licenses',
			__( 'Key Management', 'ss-core-licenses' ),
			__( 'Key Management', 'ss-core-licenses' ),
			'ss_manage_keys',
			'securesoft-keys',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render page.
	 */
	public function render_page() {
		// Ensure keys are initialized.
		$this->encryption->initialize_keys();
		
		$active_version = $this->encryption->get_active_key_version();
		$all_keys = $this->encryption->get_all_keys();
		
		// Show error message if present.
		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error"><p>' . esc_html( urldecode( $_GET['error'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		
		// Show success message if present.
		if ( isset( $_GET['key_generated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Encryption key generated successfully.', 'ss-core-licenses' ) . '</p></div>';
		}
		
		if ( isset( $_GET['keys_rotated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Encryption keys rotated successfully.', 'ss-core-licenses' ) . '</p></div>';
		}
		
		// Show warning if no keys exist.
		if ( empty( $all_keys ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'No encryption keys found. Please generate a new key to enable encryption.', 'ss-core-licenses' ) . '</p></div>';
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Key Management', 'ss-core-licenses' ); ?></h1>

			<div class="card">
				<h2><?php esc_html_e( 'Active Encryption Key', 'ss-core-licenses' ); ?></h2>
				<p>
					<strong><?php esc_html_e( 'Version:', 'ss-core-licenses' ); ?></strong>
					<?php echo esc_html( 'v' . $active_version ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'This is the currently active encryption key used for encrypting new licenses.', 'ss-core-licenses' ); ?>
				</p>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Legacy Keys', 'ss-core-licenses' ); ?></h2>
				<?php if ( count( $all_keys ) > 1 ) : ?>
					<ul>
						<?php foreach ( $all_keys as $version => $key ) : ?>
							<?php if ( $version !== $active_version ) : ?>
								<li>
									<?php echo esc_html( 'v' . $version ); ?>
									<span class="description"><?php esc_html_e( '(Legacy - used for decrypting old licenses)', 'ss-core-licenses' ); ?></span>
								</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p><?php esc_html_e( 'No legacy keys found.', 'ss-core-licenses' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Actions', 'ss-core-licenses' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ss_generate_key' ); ?>
					<input type="hidden" name="action" value="ss_generate_key">
					<?php submit_button( __( 'Generate New Key', 'ss-core-licenses' ), 'secondary', 'generate_key', false ); ?>
				</form>
				<p class="description">
					<?php esc_html_e( 'Create a new encryption key. If no keys exist, this will create the first active key.', 'ss-core-licenses' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ss_rotate_keys' ); ?>
					<input type="hidden" name="action" value="ss_rotate_keys">
					<?php submit_button( __( 'Rotate Keys', 'ss-core-licenses' ), 'primary', 'rotate_keys', false ); ?>
				</form>
				<p class="description">
					<?php esc_html_e( 'Activate a new key version for encrypting future licenses. Old keys are kept for decrypting existing licenses.', 'ss-core-licenses' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ss_backup_keys' ); ?>
					<input type="hidden" name="action" value="ss_backup_keys">
					<?php submit_button( __( 'Backup Keys', 'ss-core-licenses' ), 'secondary', 'backup_keys', false ); ?>
				</form>
				<p class="description">
					<?php esc_html_e( 'Download a backup file containing all encryption keys. Keep this file secure for disaster recovery.', 'ss-core-licenses' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ss_rotate_keys' ); ?>
					<input type="hidden" name="action" value="ss_rotate_keys">
					<input type="hidden" name="dry_run" value="1">
					<?php submit_button( __( 'Dry-Run Rotation', 'ss-core-licenses' ), 'secondary', 'dry_run', false ); ?>
				</form>
				<p class="description">
					<?php esc_html_e( 'Simulate key rotation without making changes. Use this to test the rotation process safely.', 'ss-core-licenses' ); ?>
				</p>

				<p class="description">
					<strong><?php esc_html_e( 'Warning:', 'ss-core-licenses' ); ?></strong>
					<?php esc_html_e( 'Key rotation will create a new key version. Old keys are retained for decrypting existing licenses.', 'ss-core-licenses' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle generate key.
	 */
	public function handle_generate_key() {
		if ( ! current_user_can( 'ss_manage_keys' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_generate_key' );

		// Ensure keys are initialized first.
		$this->encryption->initialize_keys();

		// Generate new key (this will become active on next rotation).
		$keys = $this->encryption->get_all_keys();
		
		// If no keys exist, create version 1 as active key.
		if ( empty( $keys ) ) {
			$new_version = 1;
			$new_key = $this->encryption->generate_key();
			if ( $new_key ) {
				$keys[ $new_version ] = base64_encode( $new_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				update_option( 'ss_core_encryption_keys', $keys );
				update_option( 'ss_core_encryption_key_version', $new_version );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=securesoft-keys&error=' . urlencode( __( 'Failed to generate encryption key.', 'ss-core-licenses' ) ) ) );
				exit;
			}
		} else {
			// Create a new version (will be activated on rotation).
			$existing_versions = array_keys( $keys );
			$new_version = max( $existing_versions ) + 1;
			$new_key = $this->encryption->generate_key();
			if ( $new_key ) {
				$keys[ $new_version ] = base64_encode( $new_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				update_option( 'ss_core_encryption_keys', $keys );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=securesoft-keys&error=' . urlencode( __( 'Failed to generate encryption key.', 'ss-core-licenses' ) ) ) );
				exit;
			}
		}

		// Log event.
		$this->audit_logger->log(
			get_current_user_id(),
			'key_generated',
			'key',
			$new_version,
			array( 'version' => $new_version )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=securesoft-keys&key_generated=1' ) );
		exit;
	}

	/**
	 * Handle rotate keys.
	 */
	public function handle_rotate_keys() {
		if ( ! current_user_can( 'ss_manage_keys' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_rotate_keys' );

		$dry_run = isset( $_POST['dry_run'] ) && '1' === $_POST['dry_run'];

		$result = $this->encryption->rotate_keys( $dry_run );

		if ( $dry_run ) {
			wp_die( esc_html( $result['message'] ) );
		} else {
			// Log event.
			$this->audit_logger->log(
				get_current_user_id(),
				'keys_rotated',
				'key',
				$result['new_version'],
				array( 'new_version' => $result['new_version'] )
			);

			wp_safe_redirect( admin_url( 'admin.php?page=securesoft-keys&keys_rotated=1' ) );
			exit;
		}
	}

	/**
	 * Handle backup keys.
	 */
	public function handle_backup_keys() {
		if ( ! current_user_can( 'ss_manage_keys' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ss-core-licenses' ) );
		}

		check_admin_referer( 'ss_backup_keys' );

		$manager = new Manager();
		$backup = $manager->backup_keys();

		// Send as download.
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="ss-keys-backup-' . date( 'Y-m-d' ) . '.json"' );
		echo $backup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}

