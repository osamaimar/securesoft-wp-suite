<?php
/**
 * Main plugin class.
 *
 * @package SS_Core_Licenses
 */

namespace SS_Core_Licenses;

/**
 * Plugin main class.
 */
class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	public $database;

	/**
	 * Encryption instance.
	 *
	 * @var Crypto\Encryption
	 */
	public $encryption;

	/**
	 * Audit logger instance.
	 *
	 * @var Audit\Logger
	 */
	public $audit_logger;

	/**
	 * License service instance.
	 *
	 * @var Licenses\Service
	 */
	public $license_service;

	/**
	 * Get plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Initialize on plugins_loaded with high priority to ensure WooCommerce is loaded.
		add_action( 'plugins_loaded', array( $this, 'init' ), 25 );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Initialize plugin components.
	 */
	public function init() {
		// Initialize database.
		$this->database = new Database();
		$this->database->create_tables();
		
		// Verify database tables exist and have correct structure.
		$this->verify_database_tables();

		// Initialize encryption.
		$this->encryption = new Crypto\Encryption();
		
		// Ensure encryption keys are initialized.
		$this->encryption->initialize_keys();

		// Initialize audit logger.
		$this->audit_logger = new Audit\Logger();

		// Initialize license service.
		$this->license_service = new Licenses\Service(
			new Licenses\Repository(),
			$this->encryption,
			$this->audit_logger
		);

		// Initialize WooCommerce integration.
		if ( $this->is_woocommerce_available() ) {
			new Woo\ProductMeta();
			new Woo\OrderHooks( $this->license_service, $this->audit_logger );
		} else {
			// Show admin notice if WooCommerce is not available.
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
		}

		// Initialize admin screens.
		if ( is_admin() ) {
			// Initialize admin screens - they will register their own menus.
			// Licenses screen creates the main menu, others create submenus.
			$this->init_admin_screens();
		}

		// Initialize REST API.
		new REST\Controllers\Licenses( $this->license_service, $this->audit_logger );
		new REST\Controllers\Audit( $this->audit_logger );
		new REST\Controllers\Rotate( $this->encryption, $this->audit_logger );
	}

	/**
	 * Initialize admin screens.
	 */
	private function init_admin_screens() {
		// Initialize admin screens - they will register their own menus.
		// Licenses screen creates the main menu, others create submenus.
		new Admin\Screens\Licenses( $this->license_service, $this->audit_logger );
		new Admin\Screens\Pools( $this->license_service, $this->audit_logger );
		new Admin\Screens\Import( $this->license_service, $this->audit_logger );
		new Admin\Screens\Keys( $this->encryption, $this->audit_logger );
		new Admin\Screens\Audit( $this->audit_logger );
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'ss-core-licenses',
			false,
			dirname( SS_CORE_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Verify database tables exist and have correct structure.
	 */
	private function verify_database_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'ss_licenses',
			$wpdb->prefix . 'ss_license_pools',
			$wpdb->prefix . 'ss_license_events',
			$wpdb->prefix . 'ss_audit_log',
		);

		foreach ( $tables as $table ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
			if ( ! $table_exists ) {
				// Table doesn't exist, recreate it.
				$this->database->create_tables();
				break;
			}
		}

		// Specifically check audit_log table structure and fix if needed.
		$audit_table = $wpdb->prefix . 'ss_audit_log';
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $audit_table ) ) === $audit_table;
		
		if ( $table_exists ) {
			$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$audit_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$column_names = array();
			if ( $columns ) {
				$column_names = wp_list_pluck( $columns, 'Field' );
			}
			
			if ( ! in_array( 'actor_user_id', $column_names, true ) ) {
				// Add missing column.
				$wpdb->query( "ALTER TABLE {$audit_table} ADD COLUMN actor_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER id" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// Check if index exists before adding.
				$indexes = $wpdb->get_results( "SHOW INDEXES FROM {$audit_table} WHERE Key_name = 'actor_user_id'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( empty( $indexes ) ) {
					$wpdb->query( "ALTER TABLE {$audit_table} ADD INDEX actor_user_id (actor_user_id)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
			}
		}
	}

	/**
	 * Check if WooCommerce is available.
	 *
	 * @return bool
	 */
	private function is_woocommerce_available() {
		// Check for WooCommerce class.
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		// Check for WooCommerce constant.
		if ( defined( 'WC_VERSION' ) ) {
			return true;
		}

		// Check for WooCommerce function.
		if ( function_exists( 'WC' ) ) {
			return true;
		}

		// Check if plugin is active.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			if ( file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}

		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( 'woocommerce/woocommerce.php' );
		}

		return false;
	}

	/**
	 * Show WooCommerce missing notice.
	 */
	public function woocommerce_missing_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'SecureSoft Core & Licenses requires WooCommerce to be installed and activated. Some features may not work correctly.', 'ss-core-licenses' );
		echo '</p></div>';
	}

}

