<?php
/**
 * ApnaStay Auth REST API Controller.
 *
 * @package ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_Auth_Controller Class.
 */
class ApnaStay_Auth_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = ApnaStay_API::$namespace;
		$this->rest_base = 'auth';
	}

	/**
	 * Register auth endpoints.
	 */
	public function register_routes() {
		// POST /wp-json/apnastay/v1/auth/login
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/login',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'login' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_login_args(),
				),
			)
		);

		// POST /wp-json/apnastay/v1/auth/logout
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/logout',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'logout' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// GET /wp-json/apnastay/v1/me (Authoritative current-user source for Next.js)
		register_rest_route(
			$this->namespace,
			'/me',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_current_user' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// GET /wp-json/apnastay/v1/auth/me
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_current_user' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// POST /wp-json/apnastay/v1/auth/switch-role
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/switch-role',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'switch_role' ),
					'permission_callback' => array( $this, 'check_switch_role_permission' ),
					'args'                => array(
						'role' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// GET /wp-json/apnastay/v1/auth/capabilities
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/capabilities',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_capabilities' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// POST /wp-json/apnastay/v1/auth/register
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/register',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_user' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_register_args(),
				),
			)
		);

		// POST /wp-json/apnastay/v1/auth/forgot-password
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/forgot-password',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'forgot_password' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_forgot_password_args(),
				),
			)
		);

		// POST /wp-json/apnastay/v1/auth/reset-password
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reset-password',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_password' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_reset_password_args(),
				),
			)
		);

		// POST & GET /wp-json/apnastay/v1/auth/validate-reset-token
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/validate-reset-token',
			array(
				array(
					'methods'             => array( WP_REST_Server::READABLE, WP_REST_Server::CREATABLE ),
					'callback'            => array( $this, 'validate_reset_token' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'key'   => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'login' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Permission check for logged in user.
	 *
	 * @return bool|WP_Error
	 */
	/**
	 * Permission check: Only administrators can switch user roles.
	 *
	 * @return bool|WP_Error
	 */
	public function check_switch_role_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'unauthorized', __( 'You must be logged in.', 'apnastay-core' ), array( 'status' => 401 ) );
		}
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'administrator' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to switch roles.', 'apnastay-core' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function check_user_logged_in() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'unauthorized', __( 'You must be logged in to switch roles.', 'apnastay-core' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Login callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function login( $request ) {
		// 1. Extract identifier and password.
		$identifier = trim( (string) $request->get_param( 'identifier' ) );
		if ( empty( $identifier ) ) {
			$identifier = trim( (string) $request->get_param( 'email' ) );
		}
		if ( empty( $identifier ) ) {
			$identifier = trim( (string) $request->get_param( 'phone' ) );
		}
		$password = (string) $request->get_param( 'password' );

		// Generic error message to prevent user enumeration
		$generic_error_message = __( 'Invalid email/phone or password.', 'apnastay-core' );

		if ( '' === $identifier || '' === $password ) {
			return apnastay_format_error_response(
				$generic_error_message,
				401,
				'INVALID_CREDENTIALS'
			);
		}

		// 2. Determine Email vs Phone and find WordPress user.
		$user = null;
		if ( is_email( $identifier ) ) {
			$user = get_user_by( 'email', $identifier );
			if ( ! $user ) {
				$user = get_user_by( 'login', $identifier );
			}
		} else {
			// Check phone number
			$clean_phone = apnastay_sanitize_phone( $identifier );
			if ( ! empty( $clean_phone ) ) {
				$user = apnastay_get_user_by_phone( $clean_phone );
			}
			// Fallback: check if username
			if ( ! $user ) {
				$user = get_user_by( 'login', $identifier );
			}
		}

		// User not found -> generic error (prevent user enumeration)
		if ( ! $user || ! ( $user instanceof WP_User ) ) {
			return apnastay_format_error_response(
				$generic_error_message,
				401,
				'INVALID_CREDENTIALS'
			);
		}

		// 3. Verify password using native WordPress authentication.
		$auth = wp_authenticate( $user->user_login, $password );
		if ( is_wp_error( $auth ) ) {
			return apnastay_format_error_response(
				$generic_error_message,
				401,
				'INVALID_CREDENTIALS'
			);
		}

		// 4. Check account status (inactive or suspended).
		$account_status = get_user_meta( $user->ID, 'account_status', true );
		if ( empty( $account_status ) ) {
			$account_status = get_user_meta( $user->ID, 'apnastay_account_status', true );
		}
		if ( 'inactive' === strtolower( (string) $account_status ) || 'suspended' === strtolower( (string) $account_status ) || 1 === (int) $user->user_status ) {
			return apnastay_format_error_response(
				__( 'Your account is inactive or suspended. Please contact support.', 'apnastay-core' ),
				403,
				'ACCOUNT_INACTIVE'
			);
		}

		// 5. Restrict allowed application users: only apnastay_tenant and apnastay_owner.
		$roles             = (array) $user->roles;
		$allowed_app_roles = array( 'apnastay_tenant', 'apnastay_owner' );

		$has_allowed_role = false;
		$canonical_role   = '';
		foreach ( $allowed_app_roles as $allowed_role ) {
			if ( in_array( $allowed_role, $roles, true ) ) {
				$has_allowed_role = true;
				$canonical_role   = ( 'apnastay_tenant' === $allowed_role ) ? 'tenant' : 'property_owner';
				break;
			}
		}

		if ( ! $has_allowed_role ) {
			return apnastay_format_error_response(
				__( 'Unauthorized role. This login portal is restricted to Tenants and Property Owners.', 'apnastay-core' ),
				403,
				'UNAUTHORIZED_ROLE'
			);
		}

		// 6. Establish authentication session via secure HttpOnly cookie.
		ApnaStay_Auth::set_session_cookie( $user->ID );

		// 7. Return safe user information (never return password or hash).
		$first_name     = $user->first_name;
		$last_name      = $user->last_name;
		$phone          = apnastay_get_user_phone( $user->ID );
		$email_verified = apnastay_is_email_verified( $user->ID );
		$phone_verified = apnastay_is_phone_verified( $user->ID );

		$response_payload = array(
			'success' => true,
			'message' => __( 'Login successful.', 'apnastay-core' ),
			'user'    => array(
				'id'             => (int) $user->ID,
				'first_name'     => ! empty( $first_name ) ? $first_name : $user->display_name,
				'last_name'      => ! empty( $last_name ) ? $last_name : '',
				'email'          => $user->user_email,
				'phone'          => ! empty( $phone ) ? $phone : null,
				'role'           => $canonical_role,
				'email_verified' => $email_verified,
				'phone_verified' => $phone_verified,
			),
		);

		return new WP_REST_Response( $response_payload, 200 );
	}
	public function logout( $request ) {
		ApnaStay_Auth::clear_session_cookie();
		return apnastay_format_success_response( null, __( 'Logged out successfully.', 'apnastay-core' ) );
	}

	/**
	 * Get current authenticated user.
	 * Preferred endpoint: GET /wp-json/apnastay/v1/auth/me
	 *
	 * Derives the current user strictly from the authenticated session (wp_get_current_user).
	 * Never accepts or trusts a user ID from the frontend request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_current_user( $request ) {
		// Strictly derive current user from the authenticated session. Never accept a user ID from the frontend.
		$user_id = get_current_user_id();

		if ( ! is_user_logged_in() || ! $user_id ) {
			// Check if caller sent invalid/tampered credentials
			$has_auth_header = ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) || ( function_exists( 'apache_request_headers' ) && ! empty( apache_request_headers()['Authorization'] ) );
			$has_session_cookie = isset( $_COOKIE['apnastay_session'] ) && ! empty( $_COOKIE['apnastay_session'] ) && 'deleted' !== $_COOKIE['apnastay_session'];

			if ( $has_auth_header || $has_session_cookie ) {
				ApnaStay_Auth::clear_session_cookie();
				return apnastay_format_error_response(
					__( 'Invalid or expired authentication token.', 'apnastay-core' ),
					401,
					'INVALID_TOKEN'
				);
			}

			// Unauthenticated request
			return new WP_REST_Response(
				array(
					'authenticated' => false,
					'user'          => null,
				),
				200
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! ( $user instanceof WP_User ) ) {
			return new WP_REST_Response(
				array(
					'authenticated' => false,
					'user'          => null,
				),
				200
			);
		}

		// Verify account status is active
		$account_status = get_user_meta( $user_id, 'account_status', true );
		if ( empty( $account_status ) ) {
			$account_status = get_user_meta( $user_id, 'apnastay_account_status', true );
		}
		if ( 'inactive' === strtolower( (string) $account_status ) || 'suspended' === strtolower( (string) $account_status ) || 1 === (int) $user->user_status ) {
			return apnastay_format_error_response(
				__( 'Your account is inactive or suspended.', 'apnastay-core' ),
				403,
				'ACCOUNT_INACTIVE'
			);
		}

		$role_slug      = ApnaStay_Roles::get_user_role( $user_id );
		$canonical_role = ApnaStay_Roles::map_role_to_account_type( $role_slug );

		$first_name = (string) $user->first_name;
		$last_name  = (string) $user->last_name;
		if ( empty( $first_name ) && ! empty( $user->display_name ) ) {
			$parts      = preg_split( '/\s+/', $user->display_name, 2 );
			$first_name = isset( $parts[0] ) ? $parts[0] : $user->display_name;
			if ( empty( $last_name ) && isset( $parts[1] ) ) {
				$last_name = $parts[1];
			}
		}

		$phone          = apnastay_get_user_phone( $user_id );
		$email_verified = apnastay_is_email_verified( $user_id );
		$phone_verified = apnastay_is_phone_verified( $user_id );

		// Expose only safe fields
		$response_payload = array(
			'authenticated' => true,
			'user'          => array(
				'id'             => (int) $user->ID,
				'first_name'     => $first_name,
				'last_name'      => $last_name,
				'email'          => $user->user_email,
				'phone'          => ! empty( $phone ) ? $phone : null,
				'role'           => $canonical_role,
				'email_verified' => $email_verified,
				'phone_verified' => $phone_verified,
			),
		);

		return new WP_REST_Response( $response_payload, 200 );
	}

	/**
	 * Switch current user role.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function switch_role( $request ) {
		$user_id  = get_current_user_id();
		$new_role = $request->get_param( 'role' );

		// Map frontend role names to internal WordPress role slugs (three real database roles only).
		$slug_map = array(
			'TENANT' => 'apnastay_tenant',
			'OWNER'  => 'apnastay_owner',
			'ADMIN'  => 'administrator',
		);
		$target_role = isset( $slug_map[ strtoupper( $new_role ) ] ) ? $slug_map[ strtoupper( $new_role ) ] : $new_role;

		$result = ApnaStay_Auth::switch_user_role( $user_id, $target_role );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		ApnaStay_Auth::set_session_cookie( $user_id );

		$profile = apnastay_get_user_profile( $user_id );

		return apnastay_format_success_response( $profile, __( 'Role switched successfully.', 'apnastay-core' ) );
	}

	/**
	 * Get central permission model capabilities.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_capabilities( $request ) {
		$role = $request->get_param( 'role' );
		if ( ! empty( $role ) ) {
			$caps = ApnaStay_Roles::get_role_capabilities( strtolower( $role ) );
			return apnastay_format_success_response( $caps );
		}

		$all_caps = ApnaStay_Roles::get_capabilities();
		return apnastay_format_success_response( $all_caps );
	}

	/**
	 * Get login arguments schema.
	 *
	 * @return array
	 */
	private function get_login_args() {
		return array(
			'identifier' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'email'      => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'phone'      => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'password'   => array(
				'required' => false,
				'type'     => 'string',
			),
		);
	}
	public function register_user( $request ) {
		// 1. Retrieve and normalize raw input parameters.
		$first_name       = trim( (string) $request->get_param( 'first_name' ) );
		$last_name        = trim( (string) $request->get_param( 'last_name' ) );
		$raw_email        = trim( (string) $request->get_param( 'email' ) );
		$email            = sanitize_email( $raw_email );
		$raw_phone        = trim( (string) $request->get_param( 'phone' ) );
		if ( empty( $raw_phone ) ) {
			$raw_phone = trim( (string) $request->get_param( 'phone_number' ) );
		}
		$password         = (string) $request->get_param( 'password' );
		$confirm_password = (string) $request->get_param( 'confirm_password' );

		// Role can be passed as role or account_type
		$raw_role = trim( (string) $request->get_param( 'role' ) );
		if ( empty( $raw_role ) ) {
			$raw_role = trim( (string) $request->get_param( 'account_type' ) );
		}

		$terms_accepted = $request->get_param( 'terms_accepted' );

		// Fallback parsing if full name was provided
		$full_name = trim( (string) $request->get_param( 'name' ) );
		if ( ! empty( $full_name ) && ( empty( $first_name ) || empty( $last_name ) ) ) {
			$parts      = preg_split( '/\s+/', $full_name, 2 );
			if ( empty( $first_name ) ) {
				$first_name = isset( $parts[0] ) ? $parts[0] : '';
			}
			if ( empty( $last_name ) ) {
				$last_name = isset( $parts[1] ) ? $parts[1] : '';
			}
		}

		// 2. Validate Required Fields -> VALIDATION_ERROR (400)
		if ( '' === $first_name || '' === $last_name || '' === $raw_email || '' === $raw_phone || '' === $password || '' === $confirm_password || '' === $raw_role || null === $terms_accepted ) {
			return apnastay_format_error_response(
				__( 'All fields (first_name, last_name, email, phone, password, confirm_password, role, terms_accepted) are required.', 'apnastay-core' ),
				400,
				'VALIDATION_ERROR'
			);
		}

		// 3. Validate Terms Acceptance -> TERMS_NOT_ACCEPTED (400)
		$is_terms_accepted = ( true === $terms_accepted || 1 === (int) $terms_accepted || 'true' === strtolower( (string) $terms_accepted ) || '1' === (string) $terms_accepted );
		if ( ! $is_terms_accepted ) {
			return apnastay_format_error_response(
				__( 'You must accept the Terms and Privacy Policy to register.', 'apnastay-core' ),
				400,
				'TERMS_NOT_ACCEPTED'
			);
		}

		// 4. Validate Name Format -> VALIDATION_ERROR (422)
		if ( mb_strlen( $first_name ) < 2 || ! preg_match( '/^[\p{L}\s\-\x27]+$/u', $first_name ) ) {
			return apnastay_format_error_response(
				__( 'First name must be at least 2 characters long and contain only valid letters.', 'apnastay-core' ),
				422,
				'VALIDATION_ERROR'
			);
		}
		if ( mb_strlen( $last_name ) < 2 || ! preg_match( '/^[\p{L}\s\-\x27]+$/u', $last_name ) ) {
			return apnastay_format_error_response(
				__( 'Last name must be at least 2 characters long and contain only valid letters.', 'apnastay-core' ),
				422,
				'VALIDATION_ERROR'
			);
		}

		// 5. Validate Email Format -> INVALID_EMAIL (422)
		if ( ! is_email( $email ) ) {
			return apnastay_format_error_response(
				__( 'A valid email address is required.', 'apnastay-core' ),
				422,
				'INVALID_EMAIL'
			);
		}

		// 6. Validate Phone Format -> INVALID_PHONE (422)
		$clean_phone = apnastay_sanitize_phone( $raw_phone );
		$digits_only = preg_replace( '/\D/', '', $clean_phone );
		if ( strlen( $digits_only ) < 10 || strlen( $digits_only ) > 15 ) {
			return apnastay_format_error_response(
				__( 'A valid 10-to-15 digit phone number is required.', 'apnastay-core' ),
				422,
				'INVALID_PHONE'
			);
		}

		// 7. Validate Role -> INVALID_ROLE (422)
		// Strictly allow only "tenant" or "property_owner" (with "owner" alias). Never allow administrator from public registration.
		$canonical_role = ApnaStay_Roles::normalize_account_type( $raw_role );
		if ( false === $canonical_role ) {
			return apnastay_format_error_response(
				__( 'Invalid role. Only "tenant" or "property_owner" are allowed.', 'apnastay-core' ),
				422,
				'INVALID_ROLE'
			);
		}

		// 8. Validate Password Strength -> WEAK_PASSWORD (422)
		if ( strlen( $password ) < 8 ) {
			return apnastay_format_error_response(
				__( 'Password must be at least 8 characters long.', 'apnastay-core' ),
				422,
				'WEAK_PASSWORD'
			);
		}

		// 9. Validate Password Confirmation -> PASSWORD_MISMATCH (400)
		if ( $password !== $confirm_password ) {
			return apnastay_format_error_response(
				__( 'Passwords do not match.', 'apnastay-core' ),
				400,
				'PASSWORD_MISMATCH'
			);
		}

		// 10. Check Email Uniqueness -> EMAIL_ALREADY_EXISTS (409)
		if ( email_exists( $email ) || username_exists( $email ) ) {
			return apnastay_format_error_response(
				__( 'An account with this email already exists.', 'apnastay-core' ),
				409,
				'EMAIL_ALREADY_EXISTS'
			);
		}

		// 11. Check Phone Uniqueness -> PHONE_ALREADY_EXISTS (409)
		if ( apnastay_is_phone_registered( $clean_phone ) ) {
			return apnastay_format_error_response(
				__( 'An account with this phone number already exists.', 'apnastay-core' ),
				409,
				'PHONE_ALREADY_EXISTS'
			);
		}

		// 12. Create WordPress Account (Native password hashing)
		$role_slug    = ApnaStay_Roles::map_account_type_to_role( $canonical_role );
		$display_name = trim( "$first_name $last_name" );

		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $display_name,
				'role'         => $role_slug,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return apnastay_format_error_response(
				$user_id->get_error_message(),
				500,
				'USER_CREATION_FAILED'
			);
		}

		// 13. Store User Metadata
		apnastay_set_user_phone( $user_id, $clean_phone );
		update_user_meta( $user_id, 'email_verified', 0 );
		update_user_meta( $user_id, 'phone_verified', 0 );

		$verification_status = ( 'apnastay_owner' === $role_slug ) ? 'unverified' : 'verified';
		update_user_meta( $user_id, 'owner_verification_status', $verification_status );
		update_user_meta( $user_id, 'apnastay_verification_status', $verification_status );
		update_user_meta( $user_id, 'apnastay_terms_accepted_at', current_time( 'mysql' ) );

		// Establish session cookie so new user is authenticated immediately
		ApnaStay_Auth::set_session_cookie( $user_id );

		// 14. Construct Safe Response Payload (Never return passwords or hashes)
		$response_payload = array(
			'success' => true,
			'message' => __( 'Registration successful.', 'apnastay-core' ),
			'user'    => array(
				'id'             => (int) $user_id,
				'first_name'     => $first_name,
				'last_name'      => $last_name,
				'email'          => $email,
				'phone'          => $clean_phone,
				'role'           => $canonical_role,
				'email_verified' => false,
				'phone_verified' => false,
			),
		);

		return new WP_REST_Response( $response_payload, 201 );
	}
	public function forgot_password( $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );

		if ( is_email( $email ) ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$key = get_password_reset_key( $user );
				if ( ! is_wp_error( $key ) ) {
					$frontend_url = function_exists( 'apnastay_get_headless_frontend_url' ) ? apnastay_get_headless_frontend_url() : trailingslashit( get_site_url() );
					$reset_url    = $frontend_url . 'reset-password?key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login );
					$subject      = __( '[ApnaStay] Password Reset Request', 'apnastay-core' );
					$message      = sprintf(
						__( "Someone has requested a password reset for the following account:\n\nUser: %1\$s\n\nIf this was a mistake, just ignore this email.\n\nTo reset your password, visit the following address:\n%2\$s", 'apnastay-core' ),
						$user->user_login,
						$reset_url
					);
					wp_mail( $user->user_email, $subject, $message );
				}
			}
		}

		return apnastay_format_success_response( null, __( 'If an account exists with that email, a password reset link has been sent.', 'apnastay-core' ) );
	}

	/**
	 * Reset password callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reset_password( $request ) {
		$key              = sanitize_text_field( (string) $request->get_param( 'key' ) );
		$login            = sanitize_text_field( (string) $request->get_param( 'login' ) );
		$password         = (string) $request->get_param( 'password' );
		$confirm_password = (string) $request->get_param( 'confirm_password' );

		if ( empty( $key ) || empty( $login ) ) {
			return apnastay_format_error_response( __( 'Reset key and login identifier are required.', 'apnastay-core' ), 400, 'MISSING_RESET_CREDENTIALS' );
		}

		if ( strlen( $password ) < 8 ) {
			return apnastay_format_error_response( __( 'Password must be at least 8 characters long.', 'apnastay-core' ), 422, 'WEAK_PASSWORD' );
		}

		if ( ! empty( $confirm_password ) && $password !== $confirm_password ) {
			return apnastay_format_error_response( __( 'Passwords do not match.', 'apnastay-core' ), 400, 'PASSWORD_MISMATCH' );
		}

		$user = get_user_by( 'login', $login );
		if ( ! $user ) {
			$user = get_user_by( 'email', $login );
		}

		if ( ! $user ) {
			return apnastay_format_error_response( __( 'Invalid or expired password reset token.', 'apnastay-core' ), 400, 'INVALID_RESET_TOKEN' );
		}

		$check = check_password_reset_key( $key, $user->user_login );
		if ( is_wp_error( $check ) ) {
			return apnastay_format_error_response( __( 'Invalid or expired password reset token.', 'apnastay-core' ), 400, 'INVALID_RESET_TOKEN' );
		}

		reset_password( $user, $password );

		return apnastay_format_success_response( null, __( 'Password reset successful. You can now login.', 'apnastay-core' ) );
	}

	/**
	 * Validate password reset token without changing password.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function validate_reset_token( $request ) {
		$key   = sanitize_text_field( (string) $request->get_param( 'key' ) );
		$login = sanitize_text_field( (string) $request->get_param( 'login' ) );

		if ( empty( $key ) || empty( $login ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'valid'   => false,
					'message' => __( 'Missing reset key or login identifier.', 'apnastay-core' ),
				),
				400
			);
		}

		$user = get_user_by( 'login', $login );
		if ( ! $user ) {
			$user = get_user_by( 'email', $login );
		}

		if ( ! $user ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'valid'   => false,
					'message' => __( 'This password reset link is invalid or has expired.', 'apnastay-core' ),
				),
				400
			);
		}

		$check = check_password_reset_key( $key, $user->user_login );
		if ( is_wp_error( $check ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'valid'   => false,
					'message' => __( 'This password reset link is invalid or has expired.', 'apnastay-core' ),
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'valid'   => true,
				'message' => __( 'Reset link is valid.', 'apnastay-core' ),
			),
			200
		);
	}

	/**
	 * Get register arguments schema.
	 *
	 * @return array
	 */
	private function get_register_args() {
		return array(
			'first_name'       => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'last_name'        => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'email'            => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'phone'            => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'password'         => array(
				'required' => false,
				'type'     => 'string',
			),
			'confirm_password' => array(
				'required' => false,
				'type'     => 'string',
			),
			'role'             => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'terms_accepted'   => array(
				'required' => false,
			),
		);
	}
	private function get_forgot_password_args() {
		return array(
			'email' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
			),
		);
	}

	/**
	 * Get reset password arguments schema.
	 *
	 * @return array
	 */
	private function get_reset_password_args() {
		return array(
			'key'      => array(
				'required' => true,
				'type'     => 'string',
			),
			'login'    => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'password'         => array(
				'required' => true,
				'type'     => 'string',
			),
			'confirm_password' => array(
				'required' => false,
				'type'     => 'string',
			),
		);
	}
}
