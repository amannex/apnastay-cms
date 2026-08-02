<?php
/**
 * OwnStay Theme — functions.php
 * Handles CORS, REST API headers, and theme setup for headless deployments.
 */

// ============================================================================
// CORS — Allow Vercel frontend to call the WordPress REST API
// ============================================================================

add_action( 'rest_api_init', function () {
    // List of allowed frontend origins
    $allowed_origins = [
        'https://ownstay.vercel.app',
        'http://localhost:3000',   // local Next.js dev
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

// Handle OPTIONS preflight requests (browser sends these before POST/PUT)
add_action( 'init', function () {
    if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
        $allowed_origins = [
            'https://ownstay.vercel.app',
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

// ============================================================================
// Theme Setup
// ============================================================================

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'custom-logo' );
} );
