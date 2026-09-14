<?php
/**
 * ApnaStay Headless CORS Handler.
 *
 * Consolidated CORS management for headless Next.js frontends.
 * Supports production, staging, Vercel preview, and local development origins.
 *
 * @package ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_CORS Class.
 */
class ApnaStay_CORS {

	/**
	 * Initialize CORS hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'send_cors_headers' ), 15 );
		add_action( 'init', array( __CLASS__, 'handle_preflight' ) );
	}

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
	public static function is_allowed_origin( $origin ) {
		if ( empty( $origin ) ) {
			return false;
		}

		$origin = rtrim( strtolower( trim( $origin ) ), '/' );

		// 1. Explicitly configured frontend URL constant.
		if ( defined( 'APNASTAY_FRONTEND_URL' ) && ! empty( APNASTAY_FRONTEND_URL ) ) {
			if ( rtrim( strtolower( APNASTAY_FRONTEND_URL ), '/' ) === $origin ) {
				return true;
			}
		}

		// 2. Production & Staging ApnaStay domains.
		if ( preg_match( '/^https:\/\/(.*\.)?apnastay\.(in|com)(:[0-9]+)?$/', $origin ) ) {
			return true;
		}

		// 3. Vercel Preview Deployments.
		if ( preg_match( '/^https:\/\/[a-z0-9-]+(\.[a-z0-9-]+)*\.vercel\.app$/', $origin ) ) {
			return true;
		}

		// 4. Local Development (localhost or 127.0.0.1 on any port).
		if ( preg_match( '/^http:\/\/(localhost|127\.0\.0\.1)(:[0-9]+)?$/', $origin ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Send CORS headers on REST API requests.
	 */
	public static function send_cors_headers() {
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '';

		if ( self::is_allowed_origin( $origin ) ) {
			header( "Access-Control-Allow-Origin: {$origin}" );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
		}
	}

	/**
	 * Handle OPTIONS preflight requests (browser sends these before POST/PUT/DELETE).
	 */
	public static function handle_preflight() {
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
			$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '';

			if ( self::is_allowed_origin( $origin ) ) {
				header( "Access-Control-Allow-Origin: {$origin}" );
				header( 'Access-Control-Allow-Credentials: true' );
				header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
				header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
				header( 'HTTP/1.1 204 No Content' );
				exit;
			}
		}
	}
}

if ( ! function_exists( 'apnastay_is_allowed_cors_origin' ) ) {
	/**
	 * Backwards-compatible global function wrapper for CORS check.
	 *
	 * @param string $origin Incoming Origin header.
	 * @return bool
	 */
	function apnastay_is_allowed_cors_origin( $origin ) {
		return ApnaStay_CORS::is_allowed_origin( $origin );
	}
}
