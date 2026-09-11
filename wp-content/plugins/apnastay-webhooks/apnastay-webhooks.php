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

if ( ! function_exists( 'apnastay_is_allowed_cors_origin' ) ) {
	/**
	 * Validate incoming CORS request origin against allowed enterprise origins.
	 * Supports:
	 * - Explicitly configured APNASTAY_FRONTEND_URL
	 * - Any ApnaStay production/staging domain (*.apnastay.in, apnastay.in, *.apnastay.com)
	 * - Vercel Preview Deployments (*.vercel.app)
	 * - Local Development (localhost or 127.0.0.1 on any port)
	 *
	 * @param string $origin Incoming Origin header.
	 * @return bool
	 */
	function apnastay_is_allowed_cors_origin( $origin ) {
		if ( empty( $origin ) ) {
			return false;
		}

		$origin = rtrim( strtolower( trim( $origin ) ), '/' );

		// 1. Explicitly configured frontend URL constant
		if ( defined( 'APNASTAY_FRONTEND_URL' ) && ! empty( APNASTAY_FRONTEND_URL ) ) {
			if ( rtrim( strtolower( APNASTAY_FRONTEND_URL ), '/' ) === $origin ) {
				return true;
			}
		}

		// 2. Production & Staging ApnaStay domains
		if ( preg_match( '/^https:\/\/(.*\.)?apnastay\.(in|com)(:[0-9]+)?$/', $origin ) ) {
			return true;
		}

		// 3. Vercel Preview Deployments
		if ( preg_match( '/^https:\/\/[a-z0-9-]+(\.[a-z0-9-]+)*\.vercel\.app$/', $origin ) ) {
			return true;
		}

		// 4. Local Development (localhost or 127.0.0.1 on any port)
		if ( preg_match( '/^http:\/\/(localhost|127\.0\.0\.1)(:[0-9]+)?$/', $origin ) ) {
			return true;
		}

		return false;
	}
}

add_action( 'rest_api_init', function () {
	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '';

	if ( apnastay_is_allowed_cors_origin( $origin ) ) {
		header( "Access-Control-Allow-Origin: {$origin}" );
		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
	}
}, 15 );

// Handle OPTIONS preflight requests (browser sends these before POST/PUT/DELETE)
add_action( 'init', function () {
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '';

		if ( apnastay_is_allowed_cors_origin( $origin ) ) {
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
