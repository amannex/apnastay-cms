<?php
/**
 * ApnaStay Tenant REST API Controller.
 * Enforces backend RBAC security boundaries using server-side current_user_can() capability checks.
 *
 * @package ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_Tenant_Controller Class.
 */
class ApnaStay_Tenant_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'apnastay/v1';
		$this->rest_base = 'tenant';
	}

	/**
	 * Register tenant REST routes with strict server-side capability permission callbacks.
	 */
	public function register_routes() {
		// GET & POST /wp-json/apnastay/v1/tenant/wishlist
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/wishlist',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_wishlist' ),
					'permission_callback' => array( $this, 'check_tenant_wishlist_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_to_wishlist' ),
					'permission_callback' => array( $this, 'check_tenant_wishlist_permission' ),
					'args'                => array(
						'property_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// DELETE /wp-json/apnastay/v1/tenant/wishlist/(?P<property_id>\d+)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/wishlist/(?P<property_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_from_wishlist' ),
					'permission_callback' => array( $this, 'check_tenant_wishlist_permission' ),
				),
			)
		);

		// GET /wp-json/apnastay/v1/tenant/visits
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/visits',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_visits' ),
					'permission_callback' => array( $this, 'check_tenant_visit_permission' ),
				),
			)
		);
	}

	/**
	 * Permission check: User must possess 'apnastay_manage_wishlist' capability.
	 * Never trusts frontend role, URL query params, or request payload.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_tenant_wishlist_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to manage your wishlist.', 'apnastay-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'apnastay_manage_wishlist' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage wishlist. This operation requires a tenant account.', 'apnastay-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission check: User must possess 'apnastay_book_visit' capability.
	 * Never trusts client parameters.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_tenant_visit_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to view booked visits.', 'apnastay-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'apnastay_book_visit' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view tenant visits.', 'apnastay-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get tenant wishlist.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_wishlist( $request ) {
		$user_id  = get_current_user_id();
		$wishlist = get_user_meta( $user_id, '_apnastay_wishlist', true );

		if ( ! is_array( $wishlist ) ) {
			$wishlist = array();
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'wishlist' => array_values( array_unique( $wishlist ) ),
				'count'    => count( $wishlist ),
			),
			200
		);
	}

	/**
	 * Add property to tenant wishlist.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_to_wishlist( $request ) {
		$user_id     = get_current_user_id();
		$params      = $request->get_json_params();
		$property_id = isset( $params['property_id'] ) ? (int) $params['property_id'] : (int) $request->get_param( 'property_id' );

		if ( ! $property_id ) {
			return new WP_Error(
				'invalid_property',
				__( 'A valid property ID is required.', 'apnastay-core' ),
				array( 'status' => 400 )
			);
		}

		$wishlist = get_user_meta( $user_id, '_apnastay_wishlist', true );
		if ( ! is_array( $wishlist ) ) {
			$wishlist = array();
		}

		if ( ! in_array( $property_id, $wishlist, true ) ) {
			$wishlist[] = $property_id;
			update_user_meta( $user_id, '_apnastay_wishlist', $wishlist );
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'message'  => __( 'Property added to wishlist.', 'apnastay-core' ),
				'wishlist' => array_values( array_unique( $wishlist ) ),
			),
			200
		);
	}

	/**
	 * Remove property from tenant wishlist.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function remove_from_wishlist( $request ) {
		$user_id     = get_current_user_id();
		$property_id = (int) $request['property_id'];

		$wishlist = get_user_meta( $user_id, '_apnastay_wishlist', true );
		if ( ! is_array( $wishlist ) ) {
			$wishlist = array();
		}

		$wishlist = array_values( array_diff( $wishlist, array( $property_id ) ) );
		update_user_meta( $user_id, '_apnastay_wishlist', $wishlist );

		return new WP_REST_Response(
			array(
				'success'  => true,
				'message'  => __( 'Property removed from wishlist.', 'apnastay-core' ),
				'wishlist' => $wishlist,
			),
			200
		);
	}

	/**
	 * Get tenant booked visits.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_visits( $request ) {
		$user_id = get_current_user_id();
		$visits  = get_user_meta( $user_id, '_apnastay_booked_visits', true );

		if ( ! is_array( $visits ) ) {
			$visits = array();
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'visits'  => $visits,
				'count'   => count( $visits ),
			),
			200
		);
	}
}
