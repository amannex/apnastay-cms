<?php
/**
 * OwnStay Core Helper Functions.
 *
 * @package OwnStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get primary OwnStay role for a user.
 *
 * @param int $user_id User ID.
 * @return string
 */
function ownstay_get_user_role( $user_id = 0 ) {
	return OwnStay_Roles::get_user_role( $user_id );
}

/**
 * Check if user has a specific OwnStay role.
 *
 * @param int    $user_id User ID.
 * @param string $role Role slug.
 * @return bool
 */
function ownstay_has_role( $user_id, $role ) {
	return OwnStay_Roles::has_role( $user_id, $role );
}

/**
 * Get formatted user profile with OwnStay metadata.
 *
 * @param int $user_id User ID.
 * @return array|WP_Error
 */
function ownstay_get_user_profile( $user_id = 0 ) {
	return OwnStay_Auth::get_user_profile( $user_id );
}

/**
 * Check if a user has a specific OwnStay capability.
 *
 * @param int    $user_id User ID.
 * @param string $capability Capability slug.
 * @return bool
 */
function ownstay_user_can( $user_id, $capability ) {
	return OwnStay_Roles::user_can( $user_id, $capability );
}

/**
 * Get capability dictionary for a specific role slug.
 *
 * @param string $role_slug Role slug.
 * @return array
 */
function ownstay_get_role_capabilities( $role_slug ) {
	return OwnStay_Roles::get_role_capabilities( $role_slug );
}

/**
 * Get all defined roles and their capabilities in the central permission model.
 *
 * @return array
 */
function ownstay_get_all_capabilities() {
	return OwnStay_Roles::get_capabilities();
}

/**
 * Get a flat array of every unique OwnStay platform capability slug across all roles.
 *
 * @return array
 */
function ownstay_get_all_platform_capabilities() {
	return OwnStay_Roles::get_all_platform_capabilities();
}

/**
 * Format a standard REST API error response.
 *
 * @param string $message Error message.
 * @param int    $status HTTP status code.
 * @param string $code Error code slug.
 * @return WP_Error
 */
function ownstay_format_error_response( $message, $status = 400, $code = 'ownstay_error' ) {
	return new WP_Error( $code, $message, array( 'status' => $status ) );
}

/**
 * Format a standard REST API success response.
 *
 * @param mixed  $data Response payload.
 * @param string $message Optional success message.
 * @param int    $status HTTP status code.
 * @return WP_REST_Response
 */
function ownstay_format_success_response( $data = array(), $message = '', $status = 200 ) {
	$response_data = array(
		'success' => true,
		'data'    => $data,
	);
	if ( ! empty( $message ) ) {
		$response_data['message'] = $message;
	}
	return new WP_REST_Response( $response_data, $status );
}

/**
 * Verify resource ownership for a property or post.
 * Enforces: Authenticated -> Has Capability -> Owns Resource -> Allowed.
 * Admin can bypass ownership where appropriate.
 *
 * @param int $post_id Post ID to check ownership against.
 * @param int $user_id Optional user ID (defaults to current logged-in user).
 * @return bool|WP_Error True if user owns the resource or is admin, WP_Error otherwise.
 */
function ownstay_verify_resource_ownership( $post_id, $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return new WP_Error(
			'unauthorized',
			__( 'You must be logged in to modify this resource.', 'ownstay-core' ),
			array( 'status' => 401 )
		);
	}

	// Admin can bypass ownership where appropriate
	if ( user_can( $user_id, 'ownstay_manage_properties' ) || user_can( $user_id, 'administrator' ) ) {
		return true;
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error(
			'not_found',
			__( 'Resource not found.', 'ownstay-core' ),
			array( 'status' => 404 )
		);
	}

	$owner_id = (int) get_post_meta( $post->ID, '_ownstay_owner_id', true );
	if ( ! $owner_id ) {
		$owner_id = (int) $post->post_author;
	}

	if ( $owner_id !== (int) $user_id ) {
		return new WP_Error(
			'forbidden',
			__( 'You cannot edit this property as you are not the owner.', 'ownstay-core' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Validate business rules for publishing a property listing.
 * Combines RBAC capability checks with domain business rules:
 * 1. Owner Verified? (KYC verification condition)
 * 2. Property Valid? (Business invariants: required fields, minimum rent, valid city)
 *
 * @param int   $user_id       User ID attempting publication.
 * @param array $property_data Property fields array (title, description, rent, city).
 * @return bool|WP_Error True if all business rules pass, WP_Error otherwise.
 */
function ownstay_validate_property_publication( $user_id, $property_data = array() ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	// 1. Business Rule: Owner Verified?
	// Even if an owner has 'ownstay_create_property' RBAC capability, they can only publish if verified (unless Admin).
	if ( ! user_can( $user_id, 'ownstay_manage_properties' ) && ! user_can( $user_id, 'administrator' ) ) {
		$verification_status = get_user_meta( $user_id, 'owner_verification_status', true );
		if ( empty( $verification_status ) ) {
			$verification_status = get_user_meta( $user_id, 'ownstay_verification_status', true );
		}
		if ( 'verified' !== strtolower( trim( $verification_status ) ) ) {
			return new WP_Error(
				'owner_not_verified',
				__( 'Business Rule Violation: Owner account must be KYC-verified before publishing a property listing.', 'ownstay-core' ),
				array( 'status' => 403 )
			);
		}
	}

	// 2. Business Rule: Property Valid?
	$title       = isset( $property_data['title'] ) ? trim( $property_data['title'] ) : '';
	$description = isset( $property_data['description'] ) ? trim( $property_data['description'] ) : '';
	$rent        = isset( $property_data['rent'] ) ? floatval( $property_data['rent'] ) : 0;
	$city        = isset( $property_data['city'] ) ? trim( $property_data['city'] ) : '';

	if ( strlen( $title ) < 5 ) {
		return new WP_Error(
			'invalid_property_title',
			__( 'Business Rule Violation: Property title must be at least 5 characters long.', 'ownstay-core' ),
			array( 'status' => 422 )
		);
	}

	if ( strlen( $description ) < 20 ) {
		return new WP_Error(
			'invalid_property_description',
			__( 'Business Rule Violation: Property description must be at least 20 characters long to provide adequate information for tenants.', 'ownstay-core' ),
			array( 'status' => 422 )
		);
	}

	if ( $rent < 1000 ) {
		return new WP_Error(
			'invalid_property_rent',
			__( 'Business Rule Violation: Minimum monthly rent must be at least ₹1,000 to publish a verified listing.', 'ownstay-core' ),
			array( 'status' => 422 )
		);
	}

	if ( empty( $city ) ) {
		return new WP_Error(
			'invalid_property_city',
			__( 'Business Rule Violation: Property city is required.', 'ownstay-core' ),
			array( 'status' => 422 )
		);
	}

	return true;
}

