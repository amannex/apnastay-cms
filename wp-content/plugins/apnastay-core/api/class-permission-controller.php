<?php
/**
 * REST API Controller for Phase 24 RBAC Permission Matrix Verification.
 *
 * @package ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_Permission_Controller Class.
 */
class ApnaStay_Permission_Controller {

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			ApnaStay_API::$namespace,
			'/permissions/matrix',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_permission_matrix' ),
					'permission_callback' => '__return_true', // Public so automated test suite & UI can inspect matrix.
				),
			)
		);
	}

	/**
	 * Execute permission matrix & API assertions and return structured JSON.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_permission_matrix( $request ) {
		// Locate users dynamically by role (or provision temporary mock subjects)
		$created_temp_tenant = false;
		$created_temp_owner  = false;

		$tenants = get_users( array( 'role' => 'apnastay_tenant', 'number' => 1 ) );
		if ( ! empty( $tenants ) ) {
			$tenant_user = $tenants[0];
		} else {
			$tid = wp_insert_user( array(
				'user_login' => 'temp_matrix_tenant_' . time(),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'temp_tenant_' . time() . '@matrix.local',
				'role'       => 'apnastay_tenant',
			) );
			$tenant_user = get_userdata( $tid );
			$created_temp_tenant = true;
		}

		$owners = get_users( array( 'role' => 'apnastay_owner', 'number' => 1 ) );
		if ( ! empty( $owners ) ) {
			$owner_user = $owners[0];
		} else {
			$oid = wp_insert_user( array(
				'user_login' => 'temp_matrix_owner_' . time(),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'temp_owner_' . time() . '@matrix.local',
				'role'       => 'apnastay_owner',
			) );
			$owner_user = get_userdata( $oid );
			$created_temp_owner = true;
		}

		$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
		$admin_user = ! empty( $admins ) ? $admins[0] : get_user_by( 'ID', 1 );

		$subjects = array(
			'Guest'  => 0,
			'Tenant' => $tenant_user->ID,
			'Owner'  => $owner_user->ID,
			'Admin'  => $admin_user->ID,
		);

		$actions = array(
			'View property'    => function ( $uid ) {
				if ( 0 === $uid ) {
					return true;
				}
				return user_can( $uid, 'apnastay_view_properties' ) || user_can( $uid, 'read' ) || user_can( $uid, 'administrator' );
			},
			'Wishlist'         => function ( $uid ) {
				if ( 0 === $uid ) {
					return false;
				}
				return user_can( $uid, 'apnastay_manage_wishlist' ) || user_can( $uid, 'administrator' );
			},
			'Create property'  => function ( $uid ) {
				if ( 0 === $uid ) {
					return false;
				}
				return user_can( $uid, 'apnastay_create_property' ) || user_can( $uid, 'apnastay_manage_properties' ) || user_can( $uid, 'administrator' );
			},
			'Verify property'  => function ( $uid ) {
				if ( 0 === $uid ) {
					return false;
				}
				return user_can( $uid, 'apnastay_verify_property' ) || user_can( $uid, 'administrator' );
			},
			'Admin dashboard'  => function ( $uid ) {
				if ( 0 === $uid ) {
					return false;
				}
				return user_can( $uid, 'manage_options' ) || user_can( $uid, 'administrator' );
			},
		);

		$expected = array(
			'View property'   => array( 'Guest' => true, 'Tenant' => true, 'Owner' => true, 'Admin' => true ),
			'Wishlist'        => array( 'Guest' => false, 'Tenant' => true, 'Owner' => false, 'Admin' => true ),
			'Create property' => array( 'Guest' => false, 'Tenant' => false, 'Owner' => true, 'Admin' => true ),
			'Verify property' => array( 'Guest' => false, 'Tenant' => false, 'Owner' => false, 'Admin' => true ),
			'Admin dashboard' => array( 'Guest' => false, 'Tenant' => false, 'Owner' => false, 'Admin' => true ),
		);

		$matrix_rows = array();
		$all_matrix_pass = true;

		foreach ( $actions as $action_name => $cb ) {
			$row = array( 'action' => $action_name, 'roles' => array() );
			foreach ( $subjects as $role_name => $uid ) {
				$actual   = call_user_func( $cb, $uid );
				$expected_val = $expected[ $action_name ][ $role_name ];
				$pass     = ( $actual === $expected_val );
				if ( ! $pass ) {
					$all_matrix_pass = false;
				}
				$row['roles'][ $role_name ] = array(
					'actual'   => $actual,
					'expected' => $expected_val,
					'pass'     => $pass,
				);
			}
			$matrix_rows[] = $row;
		}

		// Direct API & Business Rule Assertions.
		$api_controller = new ApnaStay_User_Controller();
		$assertions = array();

		// Test 1: check_user_logged_in.
		wp_set_current_user( 0 );
		$guest_res  = $api_controller->check_user_logged_in();
		$guest_pass = is_wp_error( $guest_res ) && 'unauthorized' === $guest_res->get_error_code();
		$assertions[] = array(
			'test'   => 'REST API Auth Callback check_user_logged_in() [Guest -> 401]',
			'pass'   => $guest_pass,
			'status' => $guest_pass ? '401 Unauthorized (Expected)' : 'FAIL',
		);

		wp_set_current_user( $tenant_user->ID );
		$tenant_res  = $api_controller->check_user_logged_in();
		$tenant_pass = ( true === $tenant_res );
		$assertions[] = array(
			'test'   => 'REST API Auth Callback check_user_logged_in() [Tenant -> Pass]',
			'pass'   => $tenant_pass,
			'status' => $tenant_pass ? 'Allowed' : 'FAIL',
		);

		// Test 2: check_admin_permission.
		wp_set_current_user( $tenant_user->ID );
		$tenant_adm_res  = $api_controller->check_admin_permission();
		$tenant_adm_pass = is_wp_error( $tenant_adm_res ) && 'rest_forbidden' === $tenant_adm_res->get_error_code();
		$assertions[] = array(
			'test'   => 'REST API Admin Callback check_admin_permission() [Tenant -> 403]',
			'pass'   => $tenant_adm_pass,
			'status' => $tenant_adm_pass ? '403 Forbidden (Expected)' : 'FAIL',
		);

		wp_set_current_user( $owner_user->ID );
		$owner_adm_res  = $api_controller->check_admin_permission();
		$owner_adm_pass = is_wp_error( $owner_adm_res ) && 'rest_forbidden' === $owner_adm_res->get_error_code();
		$assertions[] = array(
			'test'   => 'REST API Admin Callback check_admin_permission() [Owner -> 403]',
			'pass'   => $owner_adm_pass,
			'status' => $owner_adm_pass ? '403 Forbidden (Expected)' : 'FAIL',
		);

		wp_set_current_user( $admin_user->ID );
		$admin_adm_res  = $api_controller->check_admin_permission();
		$admin_adm_pass = ( true === $admin_adm_res );
		$assertions[] = array(
			'test'   => 'REST API Admin Callback check_admin_permission() [Admin -> Pass]',
			'pass'   => $admin_adm_pass,
			'status' => $admin_adm_pass ? 'Allowed' : 'FAIL',
		);

		// Test 3: Business rules.
		update_user_meta( $owner_user->ID, 'owner_verification_status', 'unverified' );
		update_user_meta( $owner_user->ID, 'apnastay_verification_status', 'unverified' );
		$unver_res  = apnastay_validate_property_publication( $owner_user->ID, array( 'title' => 'Luxury 3BHK Apt', 'description' => 'A spacious residential property with 24/7 power backup and NFC smart-locks.', 'rent' => 25000, 'city' => 'Noida' ) );
		$unver_pass = is_wp_error( $unver_res ) && 'owner_not_verified' === $unver_res->get_error_code();
		$assertions[] = array(
			'test'   => 'Business Rule: Unverified Owner Property Publishing [Blocked]',
			'pass'   => $unver_pass,
			'status' => $unver_pass ? 'owner_not_verified (Expected)' : 'FAIL',
		);

		update_user_meta( $owner_user->ID, 'owner_verification_status', 'verified' );
		update_user_meta( $owner_user->ID, 'apnastay_verification_status', 'verified' );
		$ver_res  = apnastay_validate_property_publication( $owner_user->ID, array( 'title' => 'Luxury 3BHK Apt', 'description' => 'A spacious residential property with 24/7 power backup and NFC smart-locks.', 'rent' => 25000, 'city' => 'Noida' ) );
		$ver_pass = ( true === $ver_res );
		$assertions[] = array(
			'test'   => 'Business Rule: Verified Owner Property Publishing [Allowed]',
			'pass'   => $ver_pass,
			'status' => $ver_pass ? 'Allowed' : 'FAIL',
		);

		// Test 4: Resource ownership.
		$dummy_id = wp_insert_post( array(
			'post_title'  => 'Test Apt #100',
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_author' => $owner_user->ID,
		) );
		update_post_meta( $dummy_id, '_apnastay_owner_id', $owner_user->ID );

		wp_set_current_user( $owner_user->ID );
		$own_res  = apnastay_verify_resource_ownership( $dummy_id, $owner_user->ID );
		$own_pass = ( true === $own_res );
		$assertions[] = array(
			'test'   => 'Resource Ownership: Owner A Editing Property #100 (Owns) [Allowed]',
			'pass'   => $own_pass,
			'status' => $own_pass ? 'Allowed' : 'FAIL',
		);

		wp_set_current_user( $tenant_user->ID );
		$other_res  = apnastay_verify_resource_ownership( $dummy_id, $tenant_user->ID );
		$other_pass = is_wp_error( $other_res ) && 'forbidden' === $other_res->get_error_code();
		$assertions[] = array(
			'test'   => 'Resource Ownership: Tenant B Editing Property #100 [403 Forbidden]',
			'pass'   => $other_pass,
			'status' => $other_pass ? '403 Forbidden (Expected)' : 'FAIL',
		);

		wp_set_current_user( $admin_user->ID );
		$adm_edit_res  = apnastay_verify_resource_ownership( $dummy_id, $admin_user->ID );
		$adm_edit_pass = ( true === $adm_edit_res );
		$assertions[] = array(
			'test'   => 'Resource Ownership: Admin Editing Property #100 [Bypass Allowed]',
			'pass'   => $adm_edit_pass,
			'status' => $adm_edit_pass ? 'Bypass Allowed' : 'FAIL',
		);

		wp_delete_post( $dummy_id, true );
		wp_set_current_user( 0 );

		$all_assertions_pass = true;
		foreach ( $assertions as $as ) {
			if ( ! $as['pass'] ) {
				$all_assertions_pass = false;
				break;
			}
		}

		if ( $created_temp_tenant && $tenant_user ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $tenant_user->ID );
		}
		if ( $created_temp_owner && $owner_user ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $owner_user->ID );
		}

		return new WP_REST_Response(
			array(
				'success'        => true,
				'all_passed'     => $all_matrix_pass && $all_assertions_pass,
				'matrix'         => $matrix_rows,
				'api_assertions' => $assertions,
				'timestamp'      => current_time( 'mysql' ),
			),
			200
		);
	}
}
