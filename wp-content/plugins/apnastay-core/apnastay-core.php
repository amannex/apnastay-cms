<?php
/**
 * Plugin Name:       ApnaStay Core
 * Plugin URI:        https://apnastay.com
 * Description:       Backend Application Layer & RBAC Engine for the ApnaStay Verified Long-Term Rental Platform.
 * Version:           1.0.0
 * Author:            ApnaStay Engineering
 * Author URI:        https://apnastay.com
 * Text Domain:       apnastay-core
 * Domain Path:       /languages
 *
 * @package           ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants.
define( 'APNASTAY_CORE_VERSION', '1.0.0' );
define( 'APNASTAY_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'APNASTAY_CORE_URL', plugin_dir_url( __FILE__ ) );

// Include Helpers first.
require_once APNASTAY_CORE_PATH . 'includes/helpers.php';

// Include Core Components.
require_once APNASTAY_CORE_PATH . 'includes/class-activator.php';
require_once APNASTAY_CORE_PATH . 'includes/class-roles.php';
require_once APNASTAY_CORE_PATH . 'includes/class-auth.php';
require_once APNASTAY_CORE_PATH . 'includes/class-api.php';

// Include REST API Controllers.
require_once APNASTAY_CORE_PATH . 'api/class-auth-controller.php';
require_once APNASTAY_CORE_PATH . 'api/class-user-controller.php';
require_once APNASTAY_CORE_PATH . 'api/class-permission-controller.php';

// Include Admin Components.
require_once APNASTAY_CORE_PATH . 'admin/class-admin-menu.php';

/**
 * Activate the plugin.
 */
function activate_apnastay_core() {
	ApnaStay_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_apnastay_core' );

/**
 * Deactivate the plugin.
 */
function deactivate_apnastay_core() {
	ApnaStay_Activator::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_apnastay_core' );

/**
 * Initialize ApnaStay Core Plugin.
 */
function run_apnastay_core() {
	// Initialize RBAC Roles & Permissions.
	$roles = ApnaStay_Roles::get_instance();
	$roles->init();

	// Initialize Authentication layer.
	$auth = ApnaStay_Auth::get_instance();
	$auth->init();

	// Initialize REST API router.
	$api = ApnaStay_API::get_instance();
	$api->init();

	// Initialize WordPress Admin Menu Hierarchy.
	if ( is_admin() ) {
		$admin_menu = ApnaStay_Admin_Menu::get_instance();
		$admin_menu->init();
	}
}
add_action( 'plugins_loaded', 'run_apnastay_core' );
