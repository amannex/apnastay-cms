<?php
/**
 * ApnaStay Properties Helper & Business Rules Functions.
 *
 * @package ApnaStay_Properties
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'apnastay_verify_resource_ownership' ) ) {
	/**
	 * Verify resource ownership for a property or post.
	 * Enforces: Authenticated -> Has Capability -> Owns Resource -> Allowed.
	 * Admin can bypass ownership where appropriate.
	 *
	 * @param int $post_id Post ID to check ownership against.
	 * @param int $user_id Optional user ID (defaults to current logged-in user).
	 * @return bool|WP_Error True if user owns the resource or is admin, WP_Error otherwise.
	 */
	function apnastay_verify_resource_ownership( $post_id, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to modify this resource.', 'apnastay-properties' ),
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
				__( 'Resource not found.', 'apnastay-properties' ),
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
				__( 'You cannot edit this property as you are not the owner.', 'apnastay-properties' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}

if ( ! function_exists( 'apnastay_validate_property_publication' ) ) {
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
			if ( 'verified' !== strtolower( trim( (string) $verification_status ) ) ) {
				return new WP_Error(
					'owner_not_verified',
					__( 'Business Rule Violation: Owner account must be KYC-verified before publishing a property listing.', 'apnastay-properties' ),
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
				__( 'Business Rule Violation: Property title must be at least 5 characters long.', 'apnastay-properties' ),
				array( 'status' => 422 )
			);
		}

		if ( strlen( $description ) < 20 ) {
			return new WP_Error(
				'invalid_property_description',
				__( 'Business Rule Violation: Property description must be at least 20 characters long to provide adequate information for tenants.', 'apnastay-properties' ),
				array( 'status' => 422 )
			);
		}

		if ( $rent < 1000 ) {
			return new WP_Error(
				'invalid_property_rent',
				__( 'Business Rule Violation: Minimum monthly rent must be at least ₹1,000 to publish a verified listing.', 'apnastay-properties' ),
				array( 'status' => 422 )
			);
		}

		if ( empty( $city ) ) {
			return new WP_Error(
				'invalid_property_city',
				__( 'Business Rule Violation: Property city is required.', 'apnastay-properties' ),
				array( 'status' => 422 )
			);
		}

		return true;
	}
}
