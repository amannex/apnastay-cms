<?php
/**
 * ApnaStay RBAC Roles & Permissions Management.
 *
 * @package ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_Roles Class.
 */
class ApnaStay_Roles {

	/**
	 * Singleton instance.
	 *
	 * @var ApnaStay_Roles|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return ApnaStay_Roles
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
			'apnastay_tenant' => array(
				'read'                     => true,
				'apnastay_manage_wishlist'  => true,
				'apnastay_book_visit'       => true,
				'apnastay_cancel_own_visit' => true,
				'apnastay_request_booking'  => true,
				'apnastay_make_payment'     => true,
				'apnastay_view_agreement'   => true,
				'apnastay_create_review'    => true,
				'apnastay_chat'             => true,
			),
			'apnastay_owner'  => array(
				'read'                          => true,
				'upload_files'                  => true,
				'apnastay_create_property'       => true,
				'apnastay_edit_own_property'     => true,
				'apnastay_delete_own_property'   => true,
				'apnastay_upload_property_media' => true,
				'apnastay_manage_rooms'          => true,
				'apnastay_manage_availability'   => true,
				'apnastay_manage_visits'         => true,
				'apnastay_manage_bookings'       => true,
				'apnastay_view_owner_payments'   => true,
				'apnastay_chat'                  => true,
			),
			'administrator'  => array(
				'apnastay_verify_owner'      => true,
				'apnastay_verify_property'   => true,
				'apnastay_manage_users'      => true,
				'apnastay_manage_properties' => true,
				'apnastay_manage_complaints' => true,
				'apnastay_manage_payments'   => true,
				'apnastay_view_analytics'    => true,
				'apnastay_view_revenue'      => true,
				'apnastay_moderate_reviews'  => true,
			),
		);
	}

	/**
	 * Get a unique flat list of all ApnaStay platform capability slugs across all roles.
	 *
	 * @return array Array of unique capability strings.
	 */
	public static function get_all_platform_capabilities() {
		$all_caps = array();
		foreach ( self::get_capabilities() as $role_slug => $role_caps ) {
			foreach ( array_keys( $role_caps ) as $cap ) {
				// Include all apnastay_* capabilities.
				if ( 0 === strpos( $cap, 'apnastay_' ) ) {
					$all_caps[ $cap ] = true;
				}
			}
		}
		return array_keys( $all_caps );
	}

	/**
	 * Get capabilities for a specific role slug.
	 *
	 * @param string $role_slug Role slug (e.g. 'apnastay_tenant', 'apnastay_owner', 'administrator').
	 * @return array
	 */
	public static function get_role_capabilities( $role_slug ) {
		$caps = self::get_capabilities();
		return isset( $caps[ $role_slug ] ) ? $caps[ $role_slug ] : array();
	}

	/**
	 * Register ApnaStay RBAC roles and capabilities.
	 * Guest is an unauthenticated visitor, never registered as a WP database role.
	 */
	public static function register_roles() {
		// Ensure guest role is never registered as a database role.
		remove_role( 'apnastay_guest' );
		remove_role( 'guest' );

		$caps = self::get_capabilities();

		// 1. Tenant Role (apnastay_tenant)
		add_role(
			'apnastay_tenant',
			'Tenant',
			$caps['apnastay_tenant']
		);

		// 2. Property Owner Role (apnastay_owner)
		add_role(
			'apnastay_owner',
			'Property Owner',
			$caps['apnastay_owner']
		);

		// 3. Grant ALL ApnaStay platform capabilities to WordPress Administrator.
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
		remove_role( 'apnastay_guest' );
		remove_role( 'apnastay_tenant' );
		remove_role( 'apnastay_owner' );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( self::get_all_platform_capabilities() as $cap ) {
				$admin_role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Get primary ApnaStay role for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string Role slug (e.g., 'apnastay_tenant', 'apnastay_owner', 'administrator', or 'guest').
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

		// Priority order for ApnaStay roles.
		if ( in_array( 'administrator', $user->roles, true ) ) {
			return 'administrator';
		}
		if ( in_array( 'apnastay_owner', $user->roles, true ) ) {
			return 'apnastay_owner';
		}
		if ( in_array( 'apnastay_tenant', $user->roles, true ) ) {
			return 'apnastay_tenant';
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
			return 'guest' === $role || 'apnastay_guest' === $role;
		}
		return in_array( $role, $user->roles, true );
	}

	/**
	 * Check if a user has a specific ApnaStay capability.
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
