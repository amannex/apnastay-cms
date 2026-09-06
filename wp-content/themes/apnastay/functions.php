<?php
/**
 * ApnaStay Theme — functions.php
 * Handles CORS, REST API headers, and theme setup for headless deployments.
 */

// ============================================================================
// CORS — Fallback for Headless Frontends if apnastay-webhooks plugin is inactive
// ============================================================================

if ( ! defined( 'APNASTAY_WEBHOOKS_VERSION' ) ) {
    add_action( 'rest_api_init', function () {
        $allowed_origins = [
            'https://apnastay.vercel.app',
            'http://localhost:3000',
            'http://localhost:3001',
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ( in_array( $origin, $allowed_origins, true ) ) {
            header( "Access-Control-Allow-Origin: {$origin}" );
            header( 'Access-Control-Allow-Credentials: true' );
            header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
        }
    }, 15 );

    add_action( 'init', function () {
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
            $allowed_origins = [
                'https://apnastay.vercel.app',
                'http://localhost:3000',
                'http://localhost:3001',
            ];

            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

            if ( in_array( $origin, $allowed_origins, true ) ) {
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


