<?php
/**
 * Plugin Name:       ApnaStay Properties
 * Plugin URI:        https://apnastay.com
 * Description:       Real Estate Listings, Inventory Management, and Property REST API for the ApnaStay Platform.
 * Version:           1.0.0
 * Author:            ApnaStay Engineering
 * Author URI:        https://apnastay.com
 * Text Domain:       apnastay-properties
 * Domain Path:       /languages
 *
 * @package           ApnaStay_Properties
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants.
define( 'APNASTAY_PROPERTIES_VERSION', '1.0.0' );
define( 'APNASTAY_PROPERTIES_PATH', plugin_dir_path( __FILE__ ) );
define( 'APNASTAY_PROPERTIES_URL', plugin_dir_url( __FILE__ ) );

// Include Helper Functions.
require_once APNASTAY_PROPERTIES_PATH . 'includes/property-helpers.php';

// Include Core Entities.
require_once APNASTAY_PROPERTIES_PATH . 'includes/class-cpt.php';
require_once APNASTAY_PROPERTIES_PATH . 'includes/class-meta.php';

// Include REST API Controllers.
require_once APNASTAY_PROPERTIES_PATH . 'api/class-property-controller.php';
require_once APNASTAY_PROPERTIES_PATH . 'api/class-tenant-controller.php';

/**
 * Activation hook for ApnaStay Properties plugin.
 */
function activate_apnastay_properties() {
	ApnaStay_CPT::register_post_types();
	ApnaStay_CPT::register_taxonomies();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'activate_apnastay_properties' );

/**
 * Deactivation hook for ApnaStay Properties plugin.
 */
function deactivate_apnastay_properties() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'deactivate_apnastay_properties' );

/**
 * Check dependencies and initialize the plugin.
 */
function run_apnastay_properties() {
	// Verify that ApnaStay Core is active.
	if ( ! defined( 'APNASTAY_CORE_VERSION' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'ApnaStay Properties requires the ApnaStay Core plugin to be installed and active.', 'apnastay-properties' ) .
				'</p></div>';
		} );
		return;
	}

	// Initialize Custom Post Types & Taxonomies.
	ApnaStay_CPT::init();

	// Initialize Meta Fields.
	ApnaStay_Meta::init();

	// Register REST API Routes.
	add_action( 'rest_api_init', function () {
		$property_controller = new ApnaStay_Property_Controller();
		$property_controller->register_routes();

		$tenant_controller = new ApnaStay_Tenant_Controller();
		$tenant_controller->register_routes();
	} );
}
add_action( 'plugins_loaded', 'run_apnastay_properties' );
