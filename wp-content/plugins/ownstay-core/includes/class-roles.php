<?php
/**
 * OwnStay RBAC Roles & Permissions Management.
 *
 * @package OwnStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OwnStay_Roles Class.
 */
class OwnStay_Roles {

	/**
	 * Singleton instance.
	 *
	 * @var OwnStay_Roles|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return OwnStay_Roles
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_action( 'init', array( __CLASS__, 'register_roles' ), 1 );
	}

	/**
	 * Centralized permission model defining all capabilities per role.
	 * Never rely only on role === owner for authorization; always check capability.
	 *
	 * @return array Associative array of role slugs and their capability definitions.
	 */
	public static function get_capabilities() {
		return array(
			'ownstay_tenant' => array(
				'read'                     => true,
				'ownstay_manage_wishlist'  => true,
				'ownstay_book_visit'       => true,
				'ownstay_cancel_own_visit' => true,
				'ownstay_request_booking'  => true,
				'ownstay_make_payment'     => true,
				'ownstay_view_agreement'   => true,
				'ownstay_create_review'    => true,
				'ownstay_chat'             => true,
			),
			'ownstay_owner'  => array(
				'read'                          => true,
				'upload_files'                  => true,
				'ownstay_create_property'       => true,
				'ownstay_edit_own_property'     => true,
				'ownstay_delete_own_property'   => true,
				'ownstay_upload_property_media' => true,
				'ownstay_manage_rooms'          => true,
				'ownstay_manage_availability'   => true,
				'ownstay_manage_visits'         => true,
				'ownstay_manage_bookings'       => true,
				'ownstay_view_owner_payments'   => true,
				'ownstay_chat'                  => true,
			),
			'administrator'  => array(
				'ownstay_verify_owner'      => true,
				'ownstay_verify_property'   => true,
				'ownstay_manage_users'      => true,
				'ownstay_manage_properties' => true,
				'ownstay_manage_complaints' => true,
				'ownstay_manage_payments'   => true,
				'ownstay_view_analytics'    => true,
				'ownstay_view_revenue'      => true,
				'ownstay_moderate_reviews'  => true,
			),
		);
	}

	/**
	 * Get a unique flat list of all OwnStay platform capability slugs across all roles.
	 *
	 * @return array Array of unique capability strings.
	 */
	public static function get_all_platform_capabilities() {
		$all_caps = array();
		foreach ( self::get_capabilities() as $role_slug => $role_caps ) {
			foreach ( array_keys( $role_caps ) as $cap ) {
				// Include all ownstay_* capabilities.
				if ( 0 === strpos( $cap, 'ownstay_' ) ) {
					$all_caps[ $cap ] = true;
				}
			}
		}
		return array_keys( $all_caps );
	}

	/**
	 * Get capabilities for a specific role slug.
	 *
	 * @param string $role_slug Role slug (e.g. 'ownstay_tenant', 'ownstay_owner', 'administrator').
	 * @return array
	 */
	public static function get_role_capabilities( $role_slug ) {
		$caps = self::get_capabilities();
		return isset( $caps[ $role_slug ] ) ? $caps[ $role_slug ] : array();
	}

	/**
	 * Register OwnStay RBAC roles and capabilities.
	 * Guest is an unauthenticated visitor, never registered as a WP database role.
	 */
	public static function register_roles() {
		// Ensure guest role is never registered as a database role.
		remove_role( 'ownstay_guest' );
		remove_role( 'guest' );

		$caps = self::get_capabilities();

		// 1. Tenant Role (ownstay_tenant)
		add_role(
			'ownstay_tenant',
			'Tenant',
			$caps['ownstay_tenant']
		);

		// 2. Property Owner Role (ownstay_owner)
		add_role(
			'ownstay_owner',
			'Property Owner',
			$caps['ownstay_owner']
		);

		// 3. Grant ALL OwnStay platform capabilities to WordPress Administrator.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( self::get_all_platform_capabilities() as $cap ) {
				$admin_role->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove custom roles and admin capabilities on plugin uninstall.
	 */
	public static function remove_roles() {
		remove_role( 'ownstay_guest' );
		remove_role( 'ownstay_tenant' );
		remove_role( 'ownstay_owner' );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( self::get_all_platform_capabilities() as $cap ) {
				$admin_role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Get primary OwnStay role for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string Role slug (e.g., 'ownstay_tenant', 'ownstay_owner', 'administrator', or 'guest').
	 */
	public static function get_user_role( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return 'guest';
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->roles ) ) {
			return 'guest';
		}

		// Priority order for OwnStay roles.
		if ( in_array( 'administrator', $user->roles, true ) ) {
			return 'administrator';
		}
		if ( in_array( 'ownstay_owner', $user->roles, true ) ) {
			return 'ownstay_owner';
		}
		if ( in_array( 'ownstay_tenant', $user->roles, true ) ) {
			return 'ownstay_tenant';
		}

		return reset( $user->roles );
	}

	/**
	 * Check if a user has a specific role.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role Role slug to check.
	 * @return bool
	 */
	public static function has_role( $user_id, $role ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return 'guest' === $role || 'ownstay_guest' === $role;
		}
		return in_array( $role, $user->roles, true );
	}

	/**
	 * Check if a user has a specific OwnStay capability.
	 * This is the primary authorization check method.
	 *
	 * @param int    $user_id User ID.
	 * @param string $capability Capability slug.
	 * @return bool
	 */
	public static function user_can( $user_id, $capability ) {
		return user_can( $user_id, $capability );
	}
}
