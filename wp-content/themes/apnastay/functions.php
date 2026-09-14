<?php
/**
 * ApnaStay Theme — functions.php
 * Handles CORS, REST API headers, and theme setup for headless deployments.
 */


// ============================================================================
// Theme Setup
// ============================================================================

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'custom-logo' );
} );


