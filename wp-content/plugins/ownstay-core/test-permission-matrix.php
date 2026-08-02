<?php
/**
 * OwnStay RBAC & Business Rules Permission Matrix Automated Test Runner.
 *
 * Systematic verification of Phase 24 Matrix:
 *                       Guest Tenant Owner Admin
 * View property           ✓      ✓     ✓     ✓
 * Wishlist                ✗      ✓     ✗     ✓
 * Create property         ✗      ✗     ✓     ✓
 * Verify property         ✗      ✗     ✗     ✓
 * Admin dashboard         ✗      ✗     ✗     ✓
 *
 * Also tests direct REST API endpoints & business rules (Phase 15-18).
 *
 * Usage via CLI:
 *   php test-permission-matrix.php
 */

// Bootstrap WordPress CLI Environment.
$wp_load_paths = array(
	dirname( __FILE__, 4 ) . '/wp-load.php',
	dirname( __FILE__, 3 ) . '/wp-load.php',
	'/Users/amansaifi/Documents/ownstay-cms/wp-load.php',
);

$loaded = false;
foreach ( $wp_load_paths as $path ) {
	if ( file_exists( $path ) ) {
		require_once $path;
		$loaded = true;
		break;
	}
}

if ( ! $loaded ) {
	die( "ERROR: Could not find wp-load.php to bootstrap WordPress.\n" );
}

echo "========================================================================\n";
echo "           OWNSTAY PLATFORM - RBAC & API PERMISSION MATRIX TEST           \n";
echo "========================================================================\n\n";

// 1. Locate Test Users.
$tenant_user = get_user_by( 'email', 'tenant@test.com' );
$owner_user  = get_user_by( 'email', 'owner@test.com' );
$admin_user  = get_user_by( 'email', 'admin@test.com' );

if ( ! $tenant_user || ! $owner_user || ! $admin_user ) {
	die( "ERROR: Test users (tenant@test.com, owner@test.com, admin@test.com) not found. Please create them via WP-CLI first.\n" );
}

$test_subjects = array(
	'Guest'  => 0,
	'Tenant' => $tenant_user->ID,
	'Owner'  => $owner_user->ID,
	'Admin'  => $admin_user->ID,
);

// Define Matrix Actions & Their Authoritative Capability / Logic Check.
$matrix_actions = array(
	'View property'    => function ( $uid ) {
		if ( 0 === $uid ) {
			return true; // Guests can view public listings.
		}
		return user_can( $uid, 'ownstay_view_properties' ) || user_can( $uid, 'read' ) || user_can( $uid, 'administrator' );
	},
	'Wishlist'         => function ( $uid ) {
		if ( 0 === $uid ) {
			return false; // Guests cannot manage wishlist.
		}
		return user_can( $uid, 'ownstay_manage_wishlist' ) || user_can( $uid, 'administrator' );
	},
	'Create property'  => function ( $uid ) {
		if ( 0 === $uid ) {
			return false;
		}
		return user_can( $uid, 'ownstay_create_property' ) || user_can( $uid, 'ownstay_manage_properties' ) || user_can( $uid, 'administrator' );
	},
	'Verify property'  => function ( $uid ) {
		if ( 0 === $uid ) {
			return false;
		}
		return user_can( $uid, 'ownstay_verify_property' ) || user_can( $uid, 'administrator' );
	},
	'Admin dashboard'  => function ( $uid ) {
		if ( 0 === $uid ) {
			return false;
		}
		return user_can( $uid, 'manage_options' ) || user_can( $uid, 'administrator' );
	},
);

// Define Expected Truth Table.
$expected_matrix = array(
	'View property'   => array(
		'Guest'  => true,
		'Tenant' => true,
		'Owner'  => true,
		'Admin'  => true,
	),
	'Wishlist'        => array(
		'Guest'  => false,
		'Tenant' => true,
		'Owner'  => false,
		'Admin'  => true,
	),
	'Create property' => array(
		'Guest'  => false,
		'Tenant' => false,
		'Owner'  => true,
		'Admin'  => true,
	),
	'Verify property' => array(
		'Guest'  => false,
		'Tenant' => false,
		'Owner'  => false,
		'Admin'  => true,
	),
	'Admin dashboard' => array(
		'Guest'  => false,
		'Tenant' => false,
		'Owner'  => false,
		'Admin'  => true,
	),
);

// Print Header.
printf( "%-20s | %-8s | %-8s | %-8s | %-8s\n", 'Action', 'Guest', 'Tenant', 'Owner', 'Admin' );
echo str_repeat( '-', 60 ) . "\n";

$matrix_pass = true;

foreach ( $matrix_actions as $action_name => $check_callback ) {
	$row_output = sprintf( "%-20s |", $action_name );

	foreach ( $test_subjects as $role_name => $user_id ) {
		$actual   = call_user_func( $check_callback, $user_id );
		$expected = $expected_matrix[ $action_name ][ $role_name ];

		if ( $actual === $expected ) {
			$symbol = $actual ? '  ✓  ' : '  ✗  ';
		} else {
			$symbol = $actual ? ' [✓!] ' : ' [✗!] ';
			$matrix_pass = false;
		}

		$row_output .= sprintf( " %-6s |", $symbol );
	}
	echo $row_output . "\n";
}

echo str_repeat( '-', 60 ) . "\n\n";

// 2. Direct REST API & Business Rule Verification.
echo "------------------------------------------------------------------------\n";
echo "               DIRECT REST API & BUSINESS RULE ASSERTIONS               \n";
echo "------------------------------------------------------------------------\n";

$api_controller = new OwnStay_User_Controller();

// Test A: API check_user_logged_in() permission callback.
echo "[Test 1] REST API Auth Callback check_user_logged_in():\n";
wp_set_current_user( 0 ); // Guest
$guest_res = $api_controller->check_user_logged_in();
$guest_pass = is_wp_error( $guest_res ) && $guest_res->get_error_code() === 'unauthorized';
echo "  -> Guest (User 0):  " . ( $guest_pass ? "PASS (401 Unauthorized as expected)" : "FAIL" ) . "\n";

wp_set_current_user( $tenant_user->ID ); // Tenant
$tenant_res = $api_controller->check_user_logged_in();
$tenant_pass = ( true === $tenant_res );
echo "  -> Tenant (User #{$tenant_user->ID}): " . ( $tenant_pass ? "PASS (Allowed)" : "FAIL" ) . "\n";

// Test B: API check_admin_permission() permission callback.
echo "\n[Test 2] REST API Admin Callback check_admin_permission():\n";
wp_set_current_user( $tenant_user->ID );
$tenant_admin_res = $api_controller->check_admin_permission();
$tenant_admin_pass = is_wp_error( $tenant_admin_res ) && $tenant_admin_res->get_error_code() === 'rest_forbidden';
echo "  -> Tenant (User #{$tenant_user->ID}): " . ( $tenant_admin_pass ? "PASS (403 Forbidden as expected)" : "FAIL" ) . "\n";

wp_set_current_user( $owner_user->ID );
$owner_admin_res = $api_controller->check_admin_permission();
$owner_admin_pass = is_wp_error( $owner_admin_res ) && $owner_admin_res->get_error_code() === 'rest_forbidden';
echo "  -> Owner  (User #{$owner_user->ID}): " . ( $owner_admin_pass ? "PASS (403 Forbidden as expected)" : "FAIL" ) . "\n";

wp_set_current_user( $admin_user->ID );
$admin_res = $api_controller->check_admin_permission();
$admin_pass = ( true === $admin_res );
echo "  -> Admin  (User #{$admin_user->ID}): " . ( $admin_pass ? "PASS (Allowed)" : "FAIL" ) . "\n";

// Test C: Business Rule - Property Publication check (ownstay_validate_property_publication).
echo "\n[Test 3] Business Rule Validation (RBAC + KYC Verified Condition):\n";
// Unverified Owner check.
update_user_meta( $owner_user->ID, 'owner_verification_status', 'unverified' );
update_user_meta( $owner_user->ID, 'ownstay_verification_status', 'unverified' );

$unverified_res = ownstay_validate_property_publication( $owner_user->ID, array(
	'title'       => 'Luxury 3BHK Apartment in Noida Sector 62',
	'description' => 'A spacious residential property with 24/7 power backup and NFC smart-locks.',
	'rent'        => 28000,
	'city'        => 'Noida',
) );
$unverified_pass = is_wp_error( $unverified_res ) && $unverified_res->get_error_code() === 'owner_not_verified';
echo "  -> Unverified Owner publishing property: " . ( $unverified_pass ? "PASS (Blocked: KYC verification required)" : "FAIL" ) . "\n";

// Verified Owner check.
update_user_meta( $owner_user->ID, 'owner_verification_status', 'verified' );
update_user_meta( $owner_user->ID, 'ownstay_verification_status', 'verified' );

$verified_res = ownstay_validate_property_publication( $owner_user->ID, array(
	'title'       => 'Luxury 3BHK Apartment in Noida Sector 62',
	'description' => 'A spacious residential property with 24/7 power backup and NFC smart-locks.',
	'rent'        => 28000,
	'city'        => 'Noida',
) );
$verified_pass = ( true === $verified_res );
echo "  -> Verified Owner publishing property:   " . ( $verified_pass ? "PASS (Allowed: All invariants passed)" : "FAIL" ) . "\n";

// Test D: Resource Ownership Isolation (Phase 16).
echo "\n[Test 4] Resource Ownership Verification (Phase 16):\n";
$dummy_post_id = wp_insert_post( array(
	'post_title'  => 'Test Apartment #100',
	'post_type'   => 'post',
	'post_status' => 'publish',
	'post_author' => $owner_user->ID,
) );
update_post_meta( $dummy_post_id, '_ownstay_owner_id', $owner_user->ID );

// Owner modifying their own post.
wp_set_current_user( $owner_user->ID );
$own_res = ownstay_verify_resource_ownership( $dummy_post_id, $owner_user->ID );
$own_pass = ( true === $own_res );
echo "  -> Owner A editing Property #100 (Owns): " . ( $own_pass ? "PASS (Allowed)" : "FAIL" ) . "\n";

// Tenant trying to edit Owner A's post.
wp_set_current_user( $tenant_user->ID );
$other_res = ownstay_verify_resource_ownership( $dummy_post_id, $tenant_user->ID );
$other_pass = is_wp_error( $other_res ) && $other_res->get_error_code() === 'forbidden';
echo "  -> Tenant B editing Property #100:       " . ( $other_pass ? "PASS (403 Forbidden as expected)" : "FAIL" ) . "\n";

// Admin overriding ownership.
wp_set_current_user( $admin_user->ID );
$admin_edit_res = ownstay_verify_resource_ownership( $dummy_post_id, $admin_user->ID );
$admin_edit_pass = ( true === $admin_edit_res );
echo "  -> Admin editing Property #100:          " . ( $admin_edit_pass ? "PASS (Allowed via admin bypass)" : "FAIL" ) . "\n";

// Clean up dummy post.
wp_delete_post( $dummy_post_id, true );

// Reset current user.
wp_set_current_user( 0 );

echo "\n========================================================================\n";
if ( $matrix_pass && $guest_pass && $tenant_pass && $tenant_admin_pass && $owner_admin_pass && $admin_pass && $unverified_pass && $verified_pass && $own_pass && $other_pass && $admin_edit_pass ) {
	echo "  🎉 ALL 20 PERMISSION MATRIX & DIRECT REST API TESTS PASSED! 🎉  \n";
	echo "========================================================================\n";
	exit( 0 );
} else {
	echo "  ❌ SOME TESTS FAILED — CHECK MATRIX ABOVE! ❌  \n";
	echo "========================================================================\n";
	exit( 1 );
}
