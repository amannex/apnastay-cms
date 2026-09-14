<?php
/**
 * Plugin Name:       ApnaStay Core
 * Plugin URI:        https://apnastay.com
 * Description:       Backend Application Layer, RBAC Engine, Property Management & Headless API for ApnaStay.
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

// Backwards-compatibility aliases.
define( 'APNASTAY_PROPERTIES_VERSION', '1.0.0' );
define( 'APNASTAY_WEBHOOKS_VERSION', '1.0.0' );

// Include Helpers first.
require_once APNASTAY_CORE_PATH . 'includes/helpers.php';
require_once APNASTAY_CORE_PATH . 'properties/property-helpers.php';

// Include Core Components.
require_once APNASTAY_CORE_PATH . 'includes/class-activator.php';
require_once APNASTAY_CORE_PATH . 'includes/class-roles.php';
require_once APNASTAY_CORE_PATH . 'includes/class-auth.php';
require_once APNASTAY_CORE_PATH . 'includes/class-api.php';
require_once APNASTAY_CORE_PATH . 'includes/class-cors.php';
require_once APNASTAY_CORE_PATH . 'includes/class-webhook-trigger.php';

// Include Properties Module (CPT & Meta).
require_once APNASTAY_CORE_PATH . 'properties/class-cpt.php';
require_once APNASTAY_CORE_PATH . 'properties/class-meta.php';

// Include REST API Controllers.
require_once APNASTAY_CORE_PATH . 'api/class-auth-controller.php';
require_once APNASTAY_CORE_PATH . 'api/class-user-controller.php';
require_once APNASTAY_CORE_PATH . 'api/class-permission-controller.php';
require_once APNASTAY_CORE_PATH . 'api/class-property-controller.php';
require_once APNASTAY_CORE_PATH . 'api/class-tenant-controller.php';

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

	// Initialize Headless CORS handler.
	ApnaStay_CORS::init();

	// Initialize Custom Post Types & Taxonomies.
	ApnaStay_CPT::init();

	// Initialize Meta Fields.
	ApnaStay_Meta::init();

	// Initialize On-Demand Next.js ISR Webhooks.
	ApnaStay_Webhook_Trigger::init();

	// Initialize WordPress Admin Menu Hierarchy.
	if ( is_admin() ) {
		$admin_menu = ApnaStay_Admin_Menu::get_instance();
		$admin_menu->init();
	}
}
add_action( 'plugins_loaded', 'run_apnastay_core' );
