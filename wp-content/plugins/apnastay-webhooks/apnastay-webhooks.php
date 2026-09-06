<?php
/**
 * Plugin Name:       ApnaStay Headless Webhooks & CORS
 * Plugin URI:        https://apnastay.com
 * Description:       On-Demand Next.js ISR Cache Revalidation Webhooks and Headless CORS Management for ApnaStay.
 * Version:           1.0.0
 * Author:            ApnaStay Engineering
 * Author URI:        https://apnastay.com
 * Text Domain:       apnastay-webhooks
 * Domain Path:       /languages
 *
 * @package           ApnaStay_Webhooks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants.
define( 'APNASTAY_WEBHOOKS_VERSION', '1.0.0' );
define( 'APNASTAY_WEBHOOKS_PATH', plugin_dir_path( __FILE__ ) );
define( 'APNASTAY_WEBHOOKS_URL', plugin_dir_url( __FILE__ ) );

// Include Webhook Trigger Component.
require_once APNASTAY_WEBHOOKS_PATH . 'includes/class-webhook-trigger.php';

// ============================================================================
// CORS — Allow Headless Next.js frontends to call the WordPress REST API
// ============================================================================

add_action( 'rest_api_init', function () {
	$allowed_origins = array(
		'https://apnastay.vercel.app',
		'https://apnastay.com',
		'http://localhost:3000',
		'http://localhost:3001',
	);

	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '';

	if ( in_array( $origin, $allowed_origins, true ) ) {
		header( "Access-Control-Allow-Origin: {$origin}" );
		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
	}
}, 15 );

// Handle OPTIONS preflight requests (browser sends these before POST/PUT/DELETE)
add_action( 'init', function () {
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
		$allowed_origins = array(
			'https://apnastay.vercel.app',
			'https://apnastay.com',
			'http://localhost:3000',
			'http://localhost:3001',
		);

		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '';

		if ( in_array( $origin, $allowed_origins, true ) ) {
			header( "Access-Control-Allow-Origin: {$origin}" );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
			header( 'HTTP/1.1 204 No Content' );
			exit;
		}
	}
} );

/**
 * Initialize Webhooks.
 */
function run_apnastay_webhooks() {
	ApnaStay_Webhook_Trigger::init();
}
add_action( 'plugins_loaded', 'run_apnastay_webhooks' );
