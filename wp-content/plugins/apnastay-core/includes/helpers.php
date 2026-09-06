<?php
/**
 * ApnaStay Core Helper Functions.
 *
 * @package ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get primary ApnaStay role for a user.
 *
 * @param int $user_id User ID.
 * @return string
 */
function apnastay_get_user_role( $user_id = 0 ) {
	return ApnaStay_Roles::get_user_role( $user_id );
}

/**
 * Check if user has a specific ApnaStay role.
 *
 * @param int    $user_id User ID.
 * @param string $role Role slug.
 * @return bool
 */
function apnastay_has_role( $user_id, $role ) {
	return ApnaStay_Roles::has_role( $user_id, $role );
}

/**
 * Normalize an account type string ('tenant', 'property_owner', 'owner' -> canonical).
 *
 * @param string $account_type Account type input.
 * @return string|false Canonical account type or false.
 */
function apnastay_normalize_account_type( $account_type ) {
	return ApnaStay_Roles::normalize_account_type( $account_type );
}

/**
 * Map account type to WordPress internal role slug.
 *
 * @param string $account_type Account type ('tenant' or 'property_owner'/'owner').
 * @return string|false Role slug or false.
 */
function apnastay_map_account_type_to_role( $account_type ) {
	return ApnaStay_Roles::map_account_type_to_role( $account_type );
}

/**
 * Map internal WordPress role slug to public account type.
 *
 * @param string $role Internal role slug.
 * @return string Public account type.
 */
function apnastay_map_role_to_account_type( $role ) {
	return ApnaStay_Roles::map_role_to_account_type( $role );
}

/**
 * Check if an account type is valid for MVP.
 *
 * @param string $account_type Account type to check.
 * @return bool
 */
function apnastay_is_valid_account_type( $account_type ) {
	return ApnaStay_Roles::is_valid_account_type( $account_type );
}

/**
 * Get formatted user profile with ApnaStay metadata.
 *
 * @param int $user_id User ID.
 * @return array|WP_Error
 */
function apnastay_get_user_profile( $user_id = 0 ) {
	return ApnaStay_Auth::get_user_profile( $user_id );
}

/**
 * Check if a user has a specific ApnaStay capability.
 *
 * @param int    $user_id User ID.
 * @param string $capability Capability slug.
 * @return bool
 */
function apnastay_user_can( $user_id, $capability ) {
	return ApnaStay_Roles::user_can( $user_id, $capability );
}

/**
 * Get capability dictionary for a specific role slug.
 *
 * @param string $role_slug Role slug.
 * @return array
 */
function apnastay_get_role_capabilities( $role_slug ) {
	return ApnaStay_Roles::get_role_capabilities( $role_slug );
}

/**
 * Get all defined roles and their capabilities in the central permission model.
 *
 * @return array
 */
function apnastay_get_all_capabilities() {
	return ApnaStay_Roles::get_capabilities();
}

/**
 * Get a flat array of every unique ApnaStay platform capability slug across all roles.
 *
 * @return array
 */
function apnastay_get_all_platform_capabilities() {
	return ApnaStay_Roles::get_all_platform_capabilities();
}

/**
 * Format a standard REST API error response.
 *
 * @param string $message Error message.
 * @param int    $status HTTP status code.
 * @param string $code Error code slug.
 * @return WP_Error
 */
function apnastay_format_error_response( $message, $status = 400, $code = 'apnastay_error' ) {
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
function apnastay_format_success_response( $data = array(), $message = '', $status = 200 ) {
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
if ( ! function_exists( 'apnastay_verify_resource_ownership' ) ) {
	function apnastay_verify_resource_ownership( $post_id, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to modify this resource.', 'apnastay-core' ),
				array( 'status' => 401 )
			);
		}

		// Admin can bypass ownership where appropriate
		if ( user_can( $user_id, 'apnastay_manage_properties' ) || user_can( $user_id, 'administrator' ) ) {
			return true;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'not_found',
				__( 'Resource not found.', 'apnastay-core' ),
				array( 'status' => 404 )
			);
		}

		$owner_id = (int) get_post_meta( $post->ID, '_apnastay_owner_id', true );
		if ( ! $owner_id ) {
			$owner_id = (int) $post->post_author;
		}

		if ( $owner_id !== (int) $user_id ) {
			return new WP_Error(
				'forbidden',
				__( 'You cannot edit this property as you are not the owner.', 'apnastay-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
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
if ( ! function_exists( 'apnastay_validate_property_publication' ) ) {
	function apnastay_validate_property_publication( $user_id, $property_data = array() ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// 1. Business Rule: Owner Verified?
		// Even if an owner has 'apnastay_create_property' RBAC capability, they can only publish if verified (unless Admin).
		if ( ! user_can( $user_id, 'apnastay_manage_properties' ) && ! user_can( $user_id, 'administrator' ) ) {
			$verification_status = get_user_meta( $user_id, 'owner_verification_status', true );
			if ( empty( $verification_status ) ) {
				$verification_status = get_user_meta( $user_id, 'apnastay_verification_status', true );
			}
			if ( 'verified' !== strtolower( trim( $verification_status ) ) ) {
				return new WP_Error(
					'owner_not_verified',
					__( 'Business Rule Violation: Owner account must be KYC-verified before publishing a property listing.', 'apnastay-core' ),
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
				__( 'Business Rule Violation: Property title must be at least 5 characters long.', 'apnastay-core' ),
				array( 'status' => 422 )
			);
		}

		if ( strlen( $description ) < 20 ) {
			return new WP_Error(
				'invalid_property_description',
				__( 'Business Rule Violation: Property description must be at least 20 characters long to provide adequate information for tenants.', 'apnastay-core' ),
				array( 'status' => 422 )
			);
		}

		if ( $rent < 1000 ) {
			return new WP_Error(
				'invalid_property_rent',
				__( 'Business Rule Violation: Minimum monthly rent must be at least ₹1,000 to publish a verified listing.', 'apnastay-core' ),
				array( 'status' => 422 )
			);
		}

		if ( empty( $city ) ) {
			return new WP_Error(
				'invalid_property_city',
				__( 'Business Rule Violation: Property city is required.', 'apnastay-core' ),
				array( 'status' => 422 )
			);
		}

		return true;
	}
}

/**
 * Sanitize and standardize a phone number.
 *
 * @param string $phone Raw phone string.
 * @return string Sanitized phone number.
 */
function apnastay_sanitize_phone( $phone ) {
	$clean = preg_replace( "/[^0-9+]/", "", trim( (string) $phone ) );
	return $clean;
}

/**
 * Get user ID associated with a phone number.
 *
 * @param string $phone Phone number to look up.
 * @return WP_User|false WP_User object or false if not found.
 */
function apnastay_get_user_by_phone( $phone ) {
	$clean_phone = apnastay_sanitize_phone( $phone );
	if ( empty( $clean_phone ) ) {
		return false;
	}

	global $wpdb;
	$user_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'apnastay_phone' AND meta_value = %s LIMIT 1",
			$clean_phone
		)
	);

	if ( $user_id ) {
		return get_userdata( (int) $user_id );
	}

	return false;
}

/**
 * Check if a phone number is already registered to a user.
 *
 * @param string $phone Phone number.
 * @param int    $exclude_user_id Optional user ID to exclude from check.
 * @return bool True if registered to another user, false otherwise.
 */
function apnastay_is_phone_registered( $phone, $exclude_user_id = 0 ) {
	$clean_phone = apnastay_sanitize_phone( $phone );
	if ( empty( $clean_phone ) ) {
		return false;
	}

	global $wpdb;
	if ( $exclude_user_id ) {
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'apnastay_phone' AND meta_value = %s AND user_id != %d LIMIT 1",
				$clean_phone,
				$exclude_user_id
			)
		);
	} else {
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'apnastay_phone' AND meta_value = %s LIMIT 1",
				$clean_phone
			)
		);
	}

	return ! empty( $found );
}

/**
 * Get user phone number.
 *
 * @param int $user_id User ID.
 * @return string|null
 */
function apnastay_get_user_phone( $user_id ) {
	$phone = get_user_meta( $user_id, 'apnastay_phone', true );
	return ! empty( $phone ) ? (string) $phone : null;
}

/**
 * Set user phone number.
 *
 * @param int    $user_id User ID.
 * @param string $phone Phone number.
 * @return bool|int
 */
function apnastay_set_user_phone( $user_id, $phone ) {
	$clean = apnastay_sanitize_phone( $phone );
	return update_user_meta( $user_id, 'apnastay_phone', $clean );
}

/**
 * Check if email is verified.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function apnastay_is_email_verified( $user_id ) {
	return (bool) get_user_meta( $user_id, 'email_verified', true );
}

/**
 * Check if phone is verified.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function apnastay_is_phone_verified( $user_id ) {
	return (bool) get_user_meta( $user_id, 'phone_verified', true );
}


/**
 * Get canonical Next.js Headless Frontend base URL.
 *
 * @return string Trailing-slashed URL.
 */
function apnastay_get_headless_frontend_url() {
	if ( defined( 'APNASTAY_FRONTEND_URL' ) && ! empty( APNASTAY_FRONTEND_URL ) ) {
		return trailingslashit( APNASTAY_FRONTEND_URL );
	}
	$saved = get_option( 'apnastay_frontend_url' );
	if ( ! empty( $saved ) ) {
		return trailingslashit( $saved );
	}
	return 'http://localhost:3000/';
}


/**
 * Purge all orphaned user data across the entire database.
 * Deletes orphaned usermeta, properties, and postmeta when a user is deleted directly in phpMyAdmin or SQL.
 */
function apnastay_cleanup_orphaned_user_data() {
	global $wpdb;

	// 1. Delete orphaned properties
	$orphaned_properties = $wpdb->get_col( "
		SELECT p.ID FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->users} u ON p.post_author = u.ID
		WHERE p.post_type = 'apnastay_property'
		AND (u.ID IS NULL OR p.post_author NOT IN (SELECT ID FROM {$wpdb->users}))
	" );

	if ( ! empty( $orphaned_properties ) ) {
		foreach ( $orphaned_properties as $pid ) {
			wp_delete_post( (int) $pid, true );
		}
	}

	// 2. Delete orphaned usermeta
	$wpdb->query( "
		DELETE um FROM {$wpdb->usermeta} um
		LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID
		WHERE u.ID IS NULL
	" );

	// 3. Delete orphaned postmeta
	$wpdb->query( "
		DELETE pm FROM {$wpdb->postmeta} pm
		LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
		WHERE p.ID IS NULL
	" );
}
