<?php
/**
 * Plugin Name:       OwnStay Core
 * Plugin URI:        https://ownstay.com
 * Description:       Backend Application Layer & RBAC Engine for the OwnStay Verified Long-Term Rental Platform.
 * Version:           1.0.0
 * Author:            OwnStay Engineering
 * Author URI:        https://ownstay.com
 * Text Domain:       ownstay-core
 * Domain Path:       /languages
 *
 * @package           OwnStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants.
define( 'OWNSTAY_CORE_VERSION', '1.0.0' );
define( 'OWNSTAY_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'OWNSTAY_CORE_URL', plugin_dir_url( __FILE__ ) );

// Include Helpers first.
require_once OWNSTAY_CORE_PATH . 'includes/helpers.php';

// Include Core Components.
require_once OWNSTAY_CORE_PATH . 'includes/class-activator.php';
require_once OWNSTAY_CORE_PATH . 'includes/class-roles.php';
require_once OWNSTAY_CORE_PATH . 'includes/class-auth.php';
require_once OWNSTAY_CORE_PATH . 'includes/class-api.php';

// Include REST API Controllers.
require_once OWNSTAY_CORE_PATH . 'api/class-auth-controller.php';
require_once OWNSTAY_CORE_PATH . 'api/class-user-controller.php';
require_once OWNSTAY_CORE_PATH . 'api/class-property-controller.php';
require_once OWNSTAY_CORE_PATH . 'api/class-permission-controller.php';

// Include Admin Components.
require_once OWNSTAY_CORE_PATH . 'admin/class-admin-menu.php';

/**
 * Activate the plugin.
 */
function activate_ownstay_core() {
	OwnStay_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_ownstay_core' );

/**
 * Deactivate the plugin.
 */
function deactivate_ownstay_core() {
	OwnStay_Activator::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_ownstay_core' );

/**
 * Initialize OwnStay Core Plugin.
 */
function run_ownstay_core() {
	// Initialize RBAC Roles & Permissions.
	$roles = OwnStay_Roles::get_instance();
	$roles->init();

	// Initialize Authentication layer.
	$auth = OwnStay_Auth::get_instance();
	$auth->init();

	// Initialize REST API router.
	$api = OwnStay_API::get_instance();
	$api->init();

	// Initialize WordPress Admin Menu Hierarchy.
	if ( is_admin() ) {
		$admin_menu = OwnStay_Admin_Menu::get_instance();
		$admin_menu->init();
	}
}
add_action( 'plugins_loaded', 'run_ownstay_core' );
