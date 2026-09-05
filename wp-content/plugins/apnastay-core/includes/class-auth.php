<?php
/**
 * ApnaStay Authentication & Role-Switching Layer.
 *
 * @package ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_Auth Class.
 */
class ApnaStay_Auth {

	/**
	 * Singleton instance.
	 *
	 * @var ApnaStay_Auth|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return ApnaStay_Auth
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
		add_filter( 'rest_authentication_errors', array( $this, 'authenticate_rest_request' ), 10 );
		add_action( 'admin_init', array( __CLASS__, 'restrict_admin_access' ), 1 );
		add_filter( 'login_redirect', array( __CLASS__, 'custom_login_redirect' ), 10, 3 );
		add_action( 'after_setup_theme', array( __CLASS__, 'disable_admin_bar' ) );
		add_action( 'deleted_user', array( __CLASS__, 'on_user_deleted' ), 10, 3 );
	}

	/**
	 * REST API Authentication filter.
	 * Validates apnastay_session HttpOnly cookie or Authorization Bearer header without exposing secrets to localStorage.
	 *
	 * @param WP_Error|null|bool $result Error from previous auth handler, null, or bool.
	 * @return WP_Error|null|bool
	 */
	public function authenticate_rest_request( $result ) {
		if ( ! empty( $result ) ) {
			return $result;
		}

		// 1. Check for apnastay_session HttpOnly cookie.
		if ( isset( $_COOKIE['apnastay_session'] ) ) {
			$user_id = self::validate_session_token( wp_unslash( $_COOKIE['apnastay_session'] ) );
			if ( $user_id ) {
				wp_set_current_user( $user_id );
				return true;
			}
		}

		// 2. Check Authorization Bearer header (for Next.js SSR / proxy requests).
		$auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) : '';
		if ( empty( $auth_header ) && function_exists( 'apache_request_headers' ) ) {
			$headers     = apache_request_headers();
			$auth_header = isset( $headers['Authorization'] ) ? $headers['Authorization'] : '';
		}

		if ( ! empty( $auth_header ) && 0 === stripos( $auth_header, 'Bearer ' ) ) {
			$token   = substr( $auth_header, 7 );
			$user_id = self::validate_session_token( $token );
			if ( $user_id ) {
				wp_set_current_user( $user_id );
				return true;
			}
		}

		// Allow WordPress default authentication to proceed.
		return $result;
	}

	/**
	 * Generate an HMAC-signed session token for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string Base64-encoded token string.
	 */
	public static function generate_session_token( $user_id ) {
		$expiration = time() + ( 14 * DAY_IN_SECONDS );
		$data       = $user_id . '|' . $expiration;
		$hmac       = hash_hmac( 'sha256', 'apnastay_session|' . $data, wp_salt( 'auth' ) );
		return base64_encode( $data . '|' . $hmac );
	}

	/**
	 * Validate an HMAC-signed session token string and return the user ID if valid.
	 *
	 * @param string $token Base64-encoded token string.
	 * @return int|false User ID on success, false on failure.
	 */
	public static function validate_session_token( $token ) {
		if ( empty( $token ) ) {
			return false;
		}

		$decoded = base64_decode( $token, true );
		if ( ! $decoded ) {
			return false;
		}

		$parts = explode( '|', $decoded );
		if ( count( $parts ) !== 3 ) {
			return false;
		}

		list( $user_id, $expiration, $hmac ) = $parts;
		$user_id    = (int) $user_id;
		$expiration = (int) $expiration;

		if ( $expiration < time() ) {
			return false;
		}

		$expected_hmac = hash_hmac( 'sha256', "apnastay_session|{$user_id}|{$expiration}", wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected_hmac, $hmac ) ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		return $user_id;
	}

	/**
	 * Set HttpOnly, Secure, SameSite session cookie for ApnaStay.
	 *
	 * @param int $user_id User ID.
	 * @return string The generated token.
	 */
	public static function set_session_cookie( $user_id ) {
		$token    = self::generate_session_token( $user_id );
		$expires  = time() + ( 14 * DAY_IN_SECONDS );
		$secure   = is_ssl();
		$samesite = $secure ? 'None' : 'Lax';

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, $secure );

		if ( ! headers_sent() ) {
			if ( PHP_VERSION_ID >= 70300 ) {
				setcookie(
					'apnastay_session',
					$token,
					array(
						'expires'  => $expires,
						'path'     => '/',
						'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
						'secure'   => $secure,
						'httponly' => true,
						'samesite' => $samesite,
					)
				);
			} else {
				$cookie_header = sprintf(
					'Set-Cookie: apnastay_session=%s; expires=%s; path=/; HttpOnly; SameSite=%s%s',
					urlencode( $token ),
					gmdate( 'D, d-M-Y H:i:s \\G\\M\\T', $expires ),
					$samesite,
					$secure ? '; Secure' : ''
				);
				header( $cookie_header, false );
			}
		}

		return $token;
	}

	/**
	 * Clear HttpOnly session cookie on logout.
	 */
	public static function clear_session_cookie() {
		wp_logout();

		$secure   = is_ssl();
		$samesite = $secure ? 'None' : 'Lax';

		if ( ! headers_sent() ) {
			if ( PHP_VERSION_ID >= 70300 ) {
				setcookie(
					'apnastay_session',
					'',
					array(
						'expires'  => time() - 3600,
						'path'     => '/',
						'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
						'secure'   => $secure,
						'httponly' => true,
						'samesite' => $samesite,
					)
				);
			} else {
				$cookie_header = sprintf(
					'Set-Cookie: apnastay_session=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; HttpOnly; SameSite=%s%s',
					$samesite,
					$secure ? '; Secure' : ''
				);
				header( $cookie_header, false );
			}
		}
	}

	/**
	 * Switch user role safely within allowed ApnaStay RBAC roles.
	 * Only the three real roles are permitted in the database: apnastay_tenant, apnastay_owner, administrator.
	 *
	 * @param int    $user_id User ID.
	 * @param string $new_role Target role slug.
	 * @return bool|WP_Error
	 */
	public static function switch_user_role( $user_id, $new_role ) {
		$allowed_roles = array( 'apnastay_tenant', 'apnastay_owner', 'administrator' );
		if ( ! in_array( $new_role, $allowed_roles, true ) ) {
			return new WP_Error( 'invalid_role', __( 'The requested role is not allowed.', 'apnastay-core' ), array( 'status' => 400 ) );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'invalid_user', __( 'User not found.', 'apnastay-core' ), array( 'status' => 404 ) );
		}

		// Set primary role.
		$user->set_role( $new_role );

		return true;
	}

	/**
	 * Get formatted profile data for a user including ApnaStay RBAC metadata.
	 *
	 * @param int $user_id User ID.
	 * @return array|WP_Error
	 */
	public static function get_user_profile( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return array(
				'id'                        => 0,
				'name'                      => 'Guest',
				'email'                     => null,
				'role'                      => 'guest',
				'account_type'              => 'guest',
				'email_verified'            => false,
				'phone_verified'            => false,
				'owner_verification_status' => 'unverified',
				'verification_status'       => 'unverified',
				'capabilities'              => array(),
				'profile'                   => array(
					'avatar'                    => null,
					'phone'                     => null,
					'first_name'                => 'Guest',
					'last_name'                 => '',
					'account_type'              => 'guest',
					'email_verified'            => false,
					'phone_verified'            => false,
					'owner_verification_status' => 'unverified',
					'verification_status'       => 'unverified',
				),
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'User profile not found.', 'apnastay-core' ), array( 'status' => 404 ) );
		}

		$role_slug = ApnaStay_Roles::get_user_role( $user_id );

		$all_platform_caps = ApnaStay_Roles::get_all_platform_capabilities();
		$user_caps         = array();
		foreach ( $all_platform_caps as $cap ) {
			if ( user_can( $user_id, $cap ) ) {
				$user_caps[] = $cap;
			}
		}

		$verification_status = get_user_meta( $user_id, 'owner_verification_status', true );
		if ( empty( $verification_status ) ) {
			$verification_status = get_user_meta( $user_id, 'apnastay_verification_status', true );
		}
		if ( empty( $verification_status ) ) {
			if ( 'apnastay_owner' === $role_slug || 'owner' === $role_slug ) {
				$verification_status = 'unverified';
			} elseif ( 'administrator' === $role_slug || 'admin' === $role_slug || 'apnastay_tenant' === $role_slug || 'tenant' === $role_slug ) {
				$verification_status = 'verified';
			} else {
				$verification_status = 'unverified';
			}
		}
		$verification_status = strtolower( trim( $verification_status ) );

		$name = $user->display_name ? $user->display_name : trim( $user->first_name . ' ' . $user->last_name );
		if ( empty( $name ) ) {
			$name = $user->user_login;
		}

		$avatar         = get_user_meta( $user_id, 'apnastay_avatar', true );
		$phone          = get_user_meta( $user_id, 'apnastay_phone', true );
		$account_type   = ApnaStay_Roles::map_role_to_account_type( $role_slug );
		$email_verified = (bool) get_user_meta( $user_id, 'email_verified', true );
		$phone_verified = (bool) get_user_meta( $user_id, 'phone_verified', true );

		return array(
			'id'                        => (int) $user->ID,
			'name'                      => $name,
			'email'                     => $user->user_email,
			'role'                      => $role_slug,
			'account_type'              => $account_type,
			'email_verified'            => $email_verified,
			'phone_verified'            => $phone_verified,
			'owner_verification_status' => $verification_status,
			'verification_status'       => $verification_status,
			'capabilities'              => $user_caps,
			'profile'                   => array(
				'avatar'                    => ! empty( $avatar ) ? $avatar : null,
				'phone'                     => ! empty( $phone ) ? $phone : null,
				'first_name'                => $user->first_name,
				'last_name'                 => $user->last_name,
				'account_type'              => $account_type,
				'email_verified'            => $email_verified,
				'phone_verified'            => $phone_verified,
				'owner_verification_status' => $verification_status,
				'verification_status'       => $verification_status,
			),
		);
	}

	/**
	 * Check REST API capability permission callback.
	 *
	 * @param string $capability Capability slug required.
	 * @return callable
	 */
	public static function require_capability( $capability ) {
		return function () use ( $capability ) {
			if ( ! is_user_logged_in() && 'read' !== $capability ) {
				return new WP_Error( 'unauthorized', __( 'You must be logged in to perform this action.', 'apnastay-core' ), array( 'status' => 401 ) );
			}
			if ( ! current_user_can( $capability ) && ! current_user_can( 'administrator' ) ) {
				return new WP_Error( 'forbidden', __( 'You do not have permission to access this resource.', 'apnastay-core' ), array( 'status' => 403 ) );
			}
			return true;
		};
	}

	/**
	 * Restrict access to WordPress Admin (/wp-admin/) for Tenants and Owners.
	 * Only internal staff/admin (manage_options or administrator) can access /wp-admin/.
	 * All others are redirected to their authoritative Next.js portal.
	 */
	public static function restrict_admin_access() {
		// Do not block AJAX, CRON, CLI, or REST API requests.
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
			( defined( 'DOING_CRON' ) && DOING_CRON ) ||
			( defined( 'WP_CLI' ) && WP_CLI ) ||
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) && ! current_user_can( 'administrator' ) ) {
			$user_id   = get_current_user_id();
			$role_slug = ApnaStay_Roles::get_user_role( $user_id );

			$frontend_url = defined( 'APNASTAY_FRONTEND_URL' ) ? rtrim( APNASTAY_FRONTEND_URL, '/' ) : 'http://localhost:3000';

			if ( 'apnastay_owner' === $role_slug ) {
				$redirect_url = $frontend_url . '/owner/dashboard?wp_admin_blocked=1';
			} else {
				$redirect_url = $frontend_url . '/dashboard?wp_admin_blocked=1';
			}

			wp_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Filter WordPress login redirect to send Tenants and Owners to their Next.js portals.
	 *
	 * @param string  $redirect_to Redirect destination URL.
	 * @param string  $requested_redirect_to Requested redirect destination URL.
	 * @param WP_User $user User object.
	 * @return string
	 */
	public static function custom_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( ! is_wp_error( $user ) && $user instanceof WP_User ) {
			if ( ! $user->has_cap( 'manage_options' ) && ! in_array( 'administrator', $user->roles, true ) ) {
				$role_slug    = ApnaStay_Roles::get_user_role( $user->ID );
				$frontend_url = defined( 'APNASTAY_FRONTEND_URL' ) ? rtrim( APNASTAY_FRONTEND_URL, '/' ) : 'http://localhost:3000';

				if ( 'apnastay_owner' === $role_slug ) {
					return $frontend_url . '/owner/dashboard';
				}
				return $frontend_url . '/dashboard';
			}
		}
		return $redirect_to;
	}

	/**
	 * Disable the WordPress admin bar on frontend pages for non-administrator roles.
	 */
	public static function disable_admin_bar() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'administrator' ) ) {
			show_admin_bar( false );
		}
	}

	/**
	 * Cascade delete all user-related application data when a user is deleted via WordPress API.
	 *
	 * @param int      $user_id      ID of the deleted user.
	 * @param int|null $reassign     Reassigned user ID if any.
	 * @param WP_User  $user         User object.
	 */
	public static function on_user_deleted( $user_id, $reassign = null, $user = null ) {
		global $wpdb;

		// 1. Delete all properties authored by this user
		$properties = get_posts( array(
			'post_type'   => 'apnastay_property',
			'author'      => $user_id,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		) );

		if ( ! empty( $properties ) ) {
			foreach ( $properties as $pid ) {
				wp_delete_post( (int) $pid, true );
			}
		}

		// 2. Delete all properties owned by this user via _apnastay_owner_id meta
		$owner_properties = get_posts( array(
			'post_type'   => 'apnastay_property',
			'meta_key'    => '_apnastay_owner_id',
			'meta_value'  => $user_id,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		) );

		if ( ! empty( $owner_properties ) ) {
			foreach ( $owner_properties as $pid ) {
				wp_delete_post( (int) $pid, true );
			}
		}

		// 3. Clean up any remaining usermeta
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d", $user_id ) );

		// 4. Clean up any orphaned postmeta
		$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL" );
	}

}
