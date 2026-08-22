<?php
/**
 * ApnaStay User REST API Controller.
 *
 * @package ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_User_Controller Class.
 */
class ApnaStay_User_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = ApnaStay_API::$namespace;
		$this->rest_base = 'users';
	}

	/**
	 * Register user endpoints.
	 */
	public function register_routes() {
		// GET /wp-json/apnastay/v1/users/profile
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/profile',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_profile' ),
					'permission_callback' => array( $this, 'check_user_logged_in' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_profile' ),
					'permission_callback' => array( $this, 'check_user_logged_in' ),
					'args'                => $this->get_update_profile_args(),
				),
			)
		);

		// GET /wp-json/apnastay/v1/users/<id>
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_user_by_id' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// POST /wp-json/apnastay/v1/users/profile/verify
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/profile/verify',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_verification' ),
					'permission_callback' => array( $this, 'check_user_logged_in' ),
				),
			)
		);

		// PUT /wp-json/apnastay/v1/users/<id>/verification
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/verification',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'admin_update_verification' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
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
			return new WP_Error( 'unauthorized', __( 'You must be logged in to view or update your profile.', 'apnastay-core' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Get current user profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_profile( $request ) {
		$user_id = get_current_user_id();
		$profile = apnastay_get_user_profile( $user_id );

		return apnastay_format_success_response( $profile );
	}

	/**
	 * Update current user profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_profile( $request ) {
		$user_id = get_current_user_id();

		$first_name = $request->get_param( 'first_name' );
		$last_name  = $request->get_param( 'last_name' );
		$phone      = $request->get_param( 'phone' );

		$update_args = array(
			'ID' => $user_id,
		);

		if ( null !== $first_name ) {
			$update_args['first_name'] = $first_name;
		}
		if ( null !== $last_name ) {
			$update_args['last_name'] = $last_name;
		}

		$updated = wp_update_user( $update_args );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		if ( null !== $phone ) {
			update_user_meta( $user_id, 'apnastay_phone', $phone );
		}

		$profile = apnastay_get_user_profile( $user_id );

		return apnastay_format_success_response( $profile, __( 'Profile updated successfully.', 'apnastay-core' ) );
	}

	/**
	 * Get user by ID.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_user_by_id( $request ) {
		$user_id = (int) $request->get_param( 'id' );
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return apnastay_format_error_response( __( 'User not found.', 'apnastay-core' ), 404 );
		}

		$profile = apnastay_get_user_profile( $user_id );
		// Filter sensitive fields for public view.
		unset( $profile['email'] );

		return apnastay_format_success_response( $profile );
	}

	/**
	 * Get update profile arguments schema.
	 *
	 * @return array
	 */
	private function get_update_profile_args() {
		return array(
			'first_name' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'last_name'  => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'phone'      => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Permission check for administrator.
	 *
	 * @return bool|WP_Error
	 */
	public function check_admin_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'unauthorized', __( 'You must be logged in.', 'apnastay-core' ), array( 'status' => 401 ) );
		}
		if ( ! current_user_can( 'apnastay_manage_users' ) && ! current_user_can( 'administrator' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to manage user verification.', 'apnastay-core' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Submit owner verification KYC request.
	 * Transitions verification status from unverified/rejected to pending.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_verification( $request ) {
		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return apnastay_format_error_response( __( 'User not found.', 'apnastay-core' ), 404 );
		}

		update_user_meta( $user_id, 'owner_verification_status', 'pending' );
		update_user_meta( $user_id, 'apnastay_verification_status', 'pending' );

		$profile = apnastay_get_user_profile( $user_id );
		return apnastay_format_success_response( $profile, __( 'Verification submitted successfully. Status is now pending.', 'apnastay-core' ) );
	}

	/**
	 * Administrative update of owner verification status.
	 * Can approve (verified) or reject (rejected/suspended).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_update_verification( $request ) {
		$user_id = (int) $request->get_param( 'id' );
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return apnastay_format_error_response( __( 'User not found.', 'apnastay-core' ), 404 );
		}

		$params = $request->get_json_params();
		$status = isset( $params['status'] ) ? strtolower( trim( sanitize_text_field( $params['status'] ) ) ) : '';

		$allowed_statuses = array( 'unverified', 'pending', 'verified', 'rejected', 'suspended' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return apnastay_format_error_response(
				__( 'Invalid verification status. Allowed: unverified, pending, verified, rejected, suspended.', 'apnastay-core' ),
				400,
				'invalid_status'
			);
		}

		update_user_meta( $user_id, 'owner_verification_status', $status );
		update_user_meta( $user_id, 'apnastay_verification_status', $status );

		$profile = apnastay_get_user_profile( $user_id );
		return apnastay_format_success_response( $profile, sprintf( __( 'User verification status updated to %s.', 'apnastay-core' ), $status ) );
	}
}
