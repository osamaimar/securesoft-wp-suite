<?php
/**
 * Main plugin class for SecureSoft Roles & Capabilities.
 *
 * @package SS_Roles_Capabilities
 */

namespace SS_Roles_Capabilities;

use SS_Roles_Capabilities\Roles\Registrar;
use SS_Roles_Capabilities\Users\Actions as User_Actions;
use SS_Roles_Capabilities\Admin\Screens\Roles as Roles_Screen;
use SS_Roles_Capabilities\Admin\Screens\Matrix as Matrix_Screen;
use SS_Roles_Capabilities\Admin\Screens\Policy as Policy_Screen;
use SS_Roles_Capabilities\Admin\Screens\Webhooks as Webhooks_Screen;
use SS_Roles_Capabilities\REST\Controllers\Roles as Roles_Controller;
use SS_Roles_Capabilities\REST\Controllers\Users as Users_Controller;
use SS_Roles_Capabilities\REST\Controllers\Webhooks as Webhooks_Controller;

/**
 * Plugin main class.
 */
class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Roles registrar instance.
	 *
	 * @var Registrar
	 */
	public $roles_registrar;

	/**
	 * User actions handler.
	 *
	 * @var User_Actions
	 */
	public $user_actions;

	/**
	 * Get plugin instance (singleton).
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
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 25 );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'ss-roles-capabilities',
			false,
			dirname( SS_ROLES_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize plugin components.
	 *
	 * @return void
	 */
	public function init() {
		// Initialize roles registrar.
		$this->roles_registrar = new Registrar();
		$this->roles_registrar->register_hooks();

		// Initialize user actions (events, suspension, profile meta).
		$this->user_actions = new User_Actions();
		$this->user_actions->register_hooks();

		// Initialize admin screens.
		if ( is_admin() ) {
			$this->init_admin_screens();
		}

		// Initialize REST API controllers.
		add_action(
			'rest_api_init',
			function () {
				$roles_controller    = new Roles_Controller( $this->roles_registrar );
				$users_controller    = new Users_Controller( $this->roles_registrar );
				$webhooks_controller = new Webhooks_Controller();

				$roles_controller->register_routes();
				$users_controller->register_routes();
				$webhooks_controller->register_routes();
			}
		);
	}

	/**
	 * Initialize admin screens.
	 *
	 * @return void
	 */
	private function init_admin_screens() {
		// Main menu + submenus.
		new Roles_Screen( $this->roles_registrar );
		new Matrix_Screen( $this->roles_registrar );
		new Policy_Screen();
		new Webhooks_Screen();
	}
}



