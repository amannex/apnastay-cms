<?php
/**
 * OwnStay Auth REST API Controller.
 *
 * @package OwnStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OwnStay_Auth_Controller Class.
 */
class OwnStay_Auth_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = OwnStay_API::$namespace;
		$this->rest_base = 'auth';
	}

	/**
	 * Register auth endpoints.
	 */
	public function register_routes() {
		// POST /wp-json/ownstay/v1/auth/login
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

		// POST /wp-json/ownstay/v1/auth/logout
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

		// GET /wp-json/ownstay/v1/me (Authoritative current-user source for Next.js)
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

		// GET /wp-json/ownstay/v1/auth/me
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

		// POST /wp-json/ownstay/v1/auth/switch-role
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/switch-role',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'switch_role' ),
					'permission_callback' => array( $this, 'check_user_logged_in' ),
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

		// GET /wp-json/ownstay/v1/auth/capabilities
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

		// POST /wp-json/ownstay/v1/auth/register
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

		// POST /wp-json/ownstay/v1/auth/forgot-password
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

		// POST /wp-json/ownstay/v1/auth/reset-password
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
	}

	/**
	 * Permission check for logged in user.
	 *
	 * @return bool|WP_Error
	 */
	public function check_user_logged_in() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'unauthorized', __( 'You must be logged in to switch roles.', 'ownstay-core' ), array( 'status' => 401 ) );
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
		$email_or_login = $request->get_param( 'email' );
		$password       = $request->get_param( 'password' );

		// Check if email is used as login.
		$user = get_user_by( 'email', $email_or_login );
		if ( ! $user ) {
			$user = get_user_by( 'login', $email_or_login );
		}

		if ( ! $user ) {
			return ownstay_format_error_response( __( 'Invalid credentials.', 'ownstay-core' ), 401 );
		}

		$auth = wp_authenticate( $user->user_login, $password );
		if ( is_wp_error( $auth ) ) {
			return ownstay_format_error_response( __( 'Invalid credentials.', 'ownstay-core' ), 401 );
		}

		OwnStay_Auth::set_session_cookie( $user->ID );

		$profile = ownstay_get_user_profile( $user->ID );

		return ownstay_format_success_response( $profile, __( 'Login successful.', 'ownstay-core' ) );
	}

	/**
	 * Logout callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function logout( $request ) {
		OwnStay_Auth::clear_session_cookie();
		return ownstay_format_success_response( null, __( 'Logged out successfully.', 'ownstay-core' ) );
	}

	/**
	 * Get current authenticated user profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_current_user( $request ) {
		$user_id = get_current_user_id();
		$profile = ownstay_get_user_profile( $user_id );

		return new WP_REST_Response( $profile, 200 );
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
			'TENANT' => 'ownstay_tenant',
			'OWNER'  => 'ownstay_owner',
			'ADMIN'  => 'administrator',
		);
		$target_role = isset( $slug_map[ strtoupper( $new_role ) ] ) ? $slug_map[ strtoupper( $new_role ) ] : $new_role;

		$result = OwnStay_Auth::switch_user_role( $user_id, $target_role );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		OwnStay_Auth::set_session_cookie( $user_id );

		$profile = ownstay_get_user_profile( $user_id );

		return ownstay_format_success_response( $profile, __( 'Role switched successfully.', 'ownstay-core' ) );
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
			$caps = OwnStay_Roles::get_role_capabilities( strtolower( $role ) );
			return ownstay_format_success_response( $caps );
		}

		$all_caps = OwnStay_Roles::get_capabilities();
		return ownstay_format_success_response( $all_caps );
	}

	/**
	 * Get login arguments schema.
	 *
	 * @return array
	 */
	private function get_login_args() {
		return array(
			'email'    => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'password' => array(
				'required' => true,
				'type'     => 'string',
			),
		);
	}

	/**
	 * Register callback connecting Next.js users to WordPress users.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function register_user( $request ) {
		// 1. Sanitize input fields.
		$raw_email    = trim( (string) $request->get_param( 'email' ) );
		$email        = sanitize_email( $raw_email );
		$password     = (string) $request->get_param( 'password' );
		$name         = sanitize_text_field( trim( (string) $request->get_param( 'name' ) ) );
		$first_name   = sanitize_text_field( trim( (string) $request->get_param( 'first_name' ) ) );
		$last_name    = sanitize_text_field( trim( (string) $request->get_param( 'last_name' ) ) );

		// Prioritize 'account_type' parameter, fallback to 'role' parameter if not supplied.
		$account_type = strtolower( trim( (string) $request->get_param( 'account_type' ) ) );
		if ( empty( $account_type ) ) {
			$account_type = strtolower( trim( (string) $request->get_param( 'role' ) ) );
		}
		if ( empty( $account_type ) ) {
			$account_type = 'tenant';
		}

		// 2. Validate required fields -> 400 Bad Request.
		if ( '' === $raw_email ) {
			return ownstay_format_error_response( __( 'Email address is required.', 'ownstay-core' ), 400, 'missing_email' );
		}
		if ( '' === $password ) {
			return ownstay_format_error_response( __( 'Password is required.', 'ownstay-core' ), 400, 'missing_password' );
		}

		// 3. Email valid? -> 422 Validation Error.
		if ( ! is_email( $email ) ) {
			return ownstay_format_error_response( __( 'A valid email address is required.', 'ownstay-core' ), 422, 'invalid_email' );
		}

		// 4. Email already exists? -> 409 Email Exists.
		if ( email_exists( $email ) || username_exists( $email ) ) {
			return ownstay_format_error_response( __( 'An account with this email already exists.', 'ownstay-core' ), 409, 'email_exists' );
		}

		// 5. Password acceptable? -> 422 Validation Error.
		if ( strlen( $password ) < 8 ) {
			return ownstay_format_error_response( __( 'Password must be at least 8 characters long.', 'ownstay-core' ), 422, 'weak_password' );
		}

		// 6. Valid account type? -> 422 Validation Error.
		// Strictly allow only "tenant" or "owner". Never allow "administrator" or any other role from public registration.
		$role_map = array(
			'tenant' => 'ownstay_tenant',
			'owner'  => 'ownstay_owner',
		);

		if ( ! isset( $role_map[ $account_type ] ) ) {
			return ownstay_format_error_response( __( 'Invalid account_type. Only "tenant" or "owner" are allowed.', 'ownstay-core' ), 422, 'invalid_account_type' );
		}

		$role_slug = $role_map[ $account_type ];

		// Parse full name into first and last name if individual fields were not provided.
		if ( ! empty( $name ) && empty( $first_name ) && empty( $last_name ) ) {
			$parts      = preg_split( '/\s+/', $name, 2 );
			$first_name = isset( $parts[0] ) ? $parts[0] : '';
			$last_name  = isset( $parts[1] ) ? $parts[1] : '';
		}

		$display_name = ! empty( $name ) ? $name : trim( "$first_name $last_name" );

		// 7. Create account.
		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => ! empty( $display_name ) ? $display_name : $email,
				'role'         => $role_slug,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return ownstay_format_error_response( $user_id->get_error_message(), 500, 'user_creation_failed' );
		}

		// Set initial verification status: Owners start unverified until KYC; Tenants default to verified.
		$status = ( 'ownstay_owner' === $role_slug ) ? 'unverified' : 'verified';
		update_user_meta( $user_id, 'owner_verification_status', $status );
		update_user_meta( $user_id, 'ownstay_verification_status', $status );

		// Establish session automatically via secure HttpOnly cookie.
		OwnStay_Auth::set_session_cookie( $user_id );

		$profile = ownstay_get_user_profile( $user_id );

		// Return 201 Created on success.
		return ownstay_format_success_response( $profile, __( 'Registration successful.', 'ownstay-core' ), 201 );
	}

	/**
	 * Forgot password callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function forgot_password( $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );

		if ( is_email( $email ) ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$key = get_password_reset_key( $user );
				if ( ! is_wp_error( $key ) ) {
					$frontend_url = function_exists( 'ownstay_get_headless_frontend_url' ) ? ownstay_get_headless_frontend_url() : trailingslashit( get_site_url() );
					$reset_url    = $frontend_url . 'auth/reset-password?key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login );
					$subject      = __( '[OwnStay] Password Reset Request', 'ownstay-core' );
					$message      = sprintf(
						__( "Someone has requested a password reset for the following account:\n\nUser: %1\$s\n\nIf this was a mistake, just ignore this email.\n\nTo reset your password, visit the following address:\n%2\$s", 'ownstay-core' ),
						$user->user_login,
						$reset_url
					);
					wp_mail( $user->user_email, $subject, $message );
				}
			}
		}

		return ownstay_format_success_response( null, __( 'If an account exists with that email, a password reset link has been sent.', 'ownstay-core' ) );
	}

	/**
	 * Reset password callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reset_password( $request ) {
		$key      = $request->get_param( 'key' );
		$login    = $request->get_param( 'login' );
		$password = $request->get_param( 'password' );

		if ( empty( $password ) || strlen( $password ) < 6 ) {
			return ownstay_format_error_response( __( 'Password must be at least 6 characters long.', 'ownstay-core' ), 400 );
		}

		$user = get_user_by( 'login', $login );
		if ( ! $user ) {
			$user = get_user_by( 'email', $login );
		}

		if ( ! $user ) {
			return ownstay_format_error_response( __( 'Invalid or expired password reset token.', 'ownstay-core' ), 400 );
		}

		$check = check_password_reset_key( $key, $user->user_login );
		if ( is_wp_error( $check ) ) {
			return ownstay_format_error_response( __( 'Invalid or expired password reset token.', 'ownstay-core' ), 400 );
		}

		reset_password( $user, $password );

		return ownstay_format_success_response( null, __( 'Password reset successful. You can now login.', 'ownstay-core' ) );
	}

	/**
	 * Get register arguments schema.
	 *
	 * @return array
	 */
	private function get_register_args() {
		return array(
			'email'        => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
			),
			'password'     => array(
				'required' => true,
				'type'     => 'string',
			),
			'name'         => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'account_type' => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => 'tenant',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'first_name'   => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'last_name'    => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get forgot password arguments schema.
	 *
	 * @return array
	 */
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
			'password' => array(
				'required' => true,
				'type'     => 'string',
			),
		);
	}
}
