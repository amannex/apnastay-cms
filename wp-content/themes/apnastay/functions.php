<?php
/**
 * ApnaStay Theme — functions.php
 * Handles CORS, REST API headers, and theme setup for headless deployments.
 */

// ============================================================================
// CORS — Fallback for Headless Frontends if apnastay-webhooks plugin is inactive
// ============================================================================

if ( ! defined( 'APNASTAY_WEBHOOKS_VERSION' ) ) {
    if ( ! function_exists( 'apnastay_is_allowed_cors_origin' ) ) {
        /**
         * Fallback origin validator if apnastay-webhooks is not active.
         */
        function apnastay_is_allowed_cors_origin( $origin ) {
            if ( empty( $origin ) ) {
                return false;
            }

            $origin = rtrim( strtolower( trim( $origin ) ), '/' );

            if ( defined( 'APNASTAY_FRONTEND_URL' ) && ! empty( APNASTAY_FRONTEND_URL ) ) {
                if ( rtrim( strtolower( APNASTAY_FRONTEND_URL ), '/' ) === $origin ) {
                    return true;
                }
            }

            if ( preg_match( '/^https:\/\/(.*\.)?apnastay\.(in|com)(:[0-9]+)?$/', $origin ) ) {
                return true;
            }

            if ( preg_match( '/^https:\/\/[a-z0-9-]+(\.[a-z0-9-]+)*\.vercel\.app$/', $origin ) ) {
                return true;
            }

            if ( preg_match( '/^http:\/\/(localhost|127\.0\.0\.1)(:[0-9]+)?$/', $origin ) ) {
                return true;
            }

            return false;
        }
    }

    add_action( 'rest_api_init', function () {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ( apnastay_is_allowed_cors_origin( $origin ) ) {
            header( "Access-Control-Allow-Origin: {$origin}" );
            header( 'Access-Control-Allow-Credentials: true' );
            header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
        }
    }, 15 );

    add_action( 'init', function () {
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

            if ( apnastay_is_allowed_cors_origin( $origin ) ) {
                header( "Access-Control-Allow-Origin: {$origin}" );
                header( 'Access-Control-Allow-Credentials: true' );
                header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
                header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
                header( 'HTTP/1.1 204 No Content' );
                exit;
            }
        }
    } );
}

// ============================================================================
// Theme Setup
// ============================================================================

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'custom-logo' );
} );


