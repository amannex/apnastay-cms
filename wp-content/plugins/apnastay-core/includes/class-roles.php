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
	 * Canonical role slugs.
	 */
	const ROLE_TENANT         = 'apnastay_tenant';
	const ROLE_PROPERTY_OWNER = 'apnastay_owner';
	const ROLE_ADMINISTRATOR  = 'administrator';
	const ROLE_GUEST          = 'guest';

	/**
	 * Canonical MVP account types.
	 */
	const ACCOUNT_TYPE_TENANT         = 'tenant';
	const ACCOUNT_TYPE_PROPERTY_OWNER = 'property_owner';

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
		add_action( 'init', array( __CLASS__, 'maybe_register_roles' ), 1 );
		add_action( 'init', array( __CLASS__, 'maybe_migrate_legacy_users' ), 2 );
	}

	/**
	 * Run role registration once if not yet registered.
	 */
	public static function maybe_register_roles() {
		if ( ! get_option( 'apnastay_roles_registered_v1' ) || ! get_role( self::ROLE_TENANT ) ) {
			self::register_roles();
		}
	}

	/**
	 * Normalize account type string.
	 * Maps 'tenant' -> 'tenant', 'property_owner' -> 'property_owner', 'owner' -> 'property_owner' (alias).
	 *
	 * @param string $account_type Input account type.
	 * @return string|false Canonical account type or false if invalid.
	 */
	public static function normalize_account_type( $account_type ) {
		$clean = strtolower( trim( (string) $account_type ) );
		if ( 'tenant' === $clean ) {
			return self::ACCOUNT_TYPE_TENANT;
		}
		if ( 'property_owner' === $clean || 'owner' === $clean ) {
			return self::ACCOUNT_TYPE_PROPERTY_OWNER;
		}
		return false;
	}

	/**
	 * Validate if account type is one of allowed MVP account types.
	 *
	 * @param string $account_type Input account type.
	 * @return bool
	 */
	public static function is_valid_account_type( $account_type ) {
		return false !== self::normalize_account_type( $account_type );
	}

	/**
	 * Map account type to WordPress internal role slug.
	 *
	 * @param string $account_type Input account type ('tenant' or 'property_owner'/'owner').
	 * @return string|false Role slug (e.g., 'apnastay_tenant' or 'apnastay_owner') or false if invalid.
	 */
	public static function map_account_type_to_role( $account_type ) {
		$normalized = self::normalize_account_type( $account_type );
		if ( self::ACCOUNT_TYPE_TENANT === $normalized ) {
			return self::ROLE_TENANT;
		}
		if ( self::ACCOUNT_TYPE_PROPERTY_OWNER === $normalized ) {
			return self::ROLE_PROPERTY_OWNER;
		}
		return false;
	}

	/**
	 * Map internal WordPress role slug to public account type.
	 *
	 * @param string $role Internal role slug.
	 * @return string Public account type ('tenant', 'property_owner', 'admin', 'guest').
	 */
	public static function map_role_to_account_type( $role ) {
		$clean = strtolower( trim( (string) $role ) );
		switch ( $clean ) {
			case 'apnastay_tenant':
			case 'tenant':
				return self::ACCOUNT_TYPE_TENANT;
			case 'apnastay_owner':
			case 'apnastay_property_owner':
			case 'property_owner':
			case 'owner':
				return self::ACCOUNT_TYPE_PROPERTY_OWNER;
			case 'administrator':
			case 'admin':
				return 'admin';
			default:
				return self::ROLE_GUEST;
		}
	}

	/**
	 * Centralized permission model defining all capabilities per role.
	 * Never rely only on role === owner for authorization; always check capability.
	 *
	 * @return array Associative array of role slugs and their capability definitions.
	 */
	public static function get_capabilities() {
		return array(
			self::ROLE_TENANT         => array(
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
			self::ROLE_PROPERTY_OWNER => array(
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
			self::ROLE_ADMINISTRATOR  => array(
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
	 * Removes obsolete and guest roles.
	 */
	public static function register_roles() {
		// Ensure guest role is never registered as a database role.
		remove_role( 'apnastay_guest' );
		remove_role( 'guest' );

		// Remove legacy roles.
		remove_role( 'ownstay_tenant' );
		remove_role( 'ownstay_owner' );

		$caps = self::get_capabilities();

		// 1. Tenant Role (apnastay_tenant)
		add_role(
			self::ROLE_TENANT,
			'ApnaStay Tenant',
			$caps[ self::ROLE_TENANT ]
		);

		// 2. Property Owner Role (apnastay_owner)
		add_role(
			self::ROLE_PROPERTY_OWNER,
			'ApnaStay Property Owner',
			$caps[ self::ROLE_PROPERTY_OWNER ]
		);

		// 3. Grant ALL ApnaStay platform capabilities to WordPress Administrator.
		$admin_role = get_role( self::ROLE_ADMINISTRATOR );
		if ( $admin_role ) {
			foreach ( self::get_all_platform_capabilities() as $cap ) {
				$admin_role->add_cap( $cap );
			}
		}

		update_option( 'apnastay_roles_registered_v1', time() );
	}

	/**
	 * Remove custom roles and admin capabilities on plugin uninstall.
	 */
	public static function remove_roles() {
		remove_role( 'apnastay_guest' );
		remove_role( 'guest' );
		remove_role( 'ownstay_tenant' );
		remove_role( 'ownstay_owner' );
		remove_role( self::ROLE_TENANT );
		remove_role( self::ROLE_PROPERTY_OWNER );

		$admin_role = get_role( self::ROLE_ADMINISTRATOR );
		if ( $admin_role ) {
			foreach ( self::get_all_platform_capabilities() as $cap ) {
				$admin_role->remove_cap( $cap );
			}
		}

		delete_option( 'apnastay_roles_registered_v1' );
	}

	/**
	 * Migrate legacy test users from ownstay_* to apnastay_* roles and sanitize usermeta.
	 *
	 * @return array Migration summary statistics.
	 */
	public static function migrate_legacy_users() {
		$migrated_count = 0;
		$users          = get_users( array( 'fields' => 'all' ) );

		foreach ( $users as $user ) {
			$needs_update = false;
			$roles        = (array) $user->roles;
			$caps         = (array) $user->caps;

			// Tenant migration
			if ( in_array( 'ownstay_tenant', $roles, true ) || isset( $caps['ownstay_tenant'] ) || ( 'tenant' === $user->user_login && empty( $roles ) ) ) {
				unset( $user->caps['ownstay_tenant'], $user->caps['ownstay_owner'] );
				update_user_meta( $user->ID, $GLOBALS['wpdb']->prefix . 'capabilities', array( self::ROLE_TENANT => true ) );
				$user->set_role( self::ROLE_TENANT );
				$needs_update = true;
			}

			// Owner migration
			if ( in_array( 'ownstay_owner', $roles, true ) || isset( $caps['ownstay_owner'] ) || ( 'owner' === $user->user_login && empty( $roles ) ) ) {
				unset( $user->caps['ownstay_tenant'], $user->caps['ownstay_owner'] );
				update_user_meta( $user->ID, $GLOBALS['wpdb']->prefix . 'capabilities', array( self::ROLE_PROPERTY_OWNER => true ) );
				$user->set_role( self::ROLE_PROPERTY_OWNER );
				$needs_update = true;
			}

			// Meta migration: ownstay_verification_status -> owner_verification_status & apnastay_verification_status
			$legacy_verification = get_user_meta( $user->ID, 'ownstay_verification_status', true );
			if ( ! empty( $legacy_verification ) ) {
				update_user_meta( $user->ID, 'owner_verification_status', sanitize_text_field( $legacy_verification ) );
				update_user_meta( $user->ID, 'apnastay_verification_status', sanitize_text_field( $legacy_verification ) );
				delete_user_meta( $user->ID, 'ownstay_verification_status' );
			}

			// Future-proofing fields initialization if missing
			if ( '' === get_user_meta( $user->ID, 'email_verified', true ) ) {
				// Admin users default to verified; others 0
				$is_admin = in_array( 'administrator', $user->roles, true );
				update_user_meta( $user->ID, 'email_verified', $is_admin ? 1 : 0 );
			}
			if ( '' === get_user_meta( $user->ID, 'phone_verified', true ) ) {
				$is_admin = in_array( 'administrator', $user->roles, true );
				update_user_meta( $user->ID, 'phone_verified', $is_admin ? 1 : 0 );
			}

			if ( $needs_update ) {
				$migrated_count++;
			}
		}

		update_option( 'apnastay_legacy_users_migrated_v1', time() );

		return array(
			'migrated_count' => $migrated_count,
		);
	}

	/**
	 * Run migration once if not yet executed.
	 */
	public static function maybe_migrate_legacy_users() {
		if ( ! get_option( 'apnastay_legacy_users_migrated_v1' ) ) {
			self::migrate_legacy_users();
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
			return self::ROLE_GUEST;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->roles ) ) {
			return self::ROLE_GUEST;
		}

		// Priority order for ApnaStay roles.
		if ( in_array( self::ROLE_ADMINISTRATOR, $user->roles, true ) ) {
			return self::ROLE_ADMINISTRATOR;
		}
		if ( in_array( self::ROLE_PROPERTY_OWNER, $user->roles, true ) ) {
			return self::ROLE_PROPERTY_OWNER;
		}
		if ( in_array( self::ROLE_TENANT, $user->roles, true ) ) {
			return self::ROLE_TENANT;
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
