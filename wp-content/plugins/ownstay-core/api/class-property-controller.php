<?php
/**
 * OwnStay Property REST API Controller.
 * Enforces real backend RBAC security boundaries using current_user_can() capability checks.
 *
 * @package OwnStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OwnStay_Property_Controller Class.
 */
class OwnStay_Property_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = OwnStay_API::$namespace;
		$this->rest_base = 'properties';
	}

	/**
	 * Register property REST routes with strict RBAC permission callbacks.
	 */
	public function register_routes() {
		// GET & POST /wp-json/ownstay/v1/properties
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_properties' ),
					'permission_callback' => '__return_true', // Publicly viewable listings
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_property' ),
					'permission_callback' => array( $this, 'check_create_property_permission' ),
					'args'                => $this->get_property_schema_args(),
				),
			)
		);

		// GET, PUT/PATCH, DELETE /wp-json/ownstay/v1/properties/<id>
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_property_by_id' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_property' ),
					'permission_callback' => array( $this, 'check_edit_property_permission' ),
					'args'                => $this->get_property_schema_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_property' ),
					'permission_callback' => array( $this, 'check_delete_property_permission' ),
				),
			)
		);
	}

	/**
	 * Permission check: User must possess 'ownstay_create_property' capability.
	 * Even if a Tenant manually calls POST /wp-json/ownstay/v1/properties, this returns 403 Forbidden.
	 *
	 * @return bool|WP_Error
	 */
	public function check_create_property_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to list a property.', 'ownstay-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'ownstay_create_property' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to create property listings.', 'ownstay-core' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission check: User must possess 'ownstay_edit_own_property' or 'ownstay_manage_properties' capability.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_edit_property_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to edit a property.', 'ownstay-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'ownstay_edit_own_property' ) && ! current_user_can( 'ownstay_manage_properties' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to edit this property.', 'ownstay-core' ),
				array( 'status' => 403 )
			);
		}

		// Enforce resource ownership check (Admin can bypass)
		$ownership = ownstay_verify_resource_ownership( (int) $request['id'] );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		return true;
	}

	/**
	 * Permission check: User must possess 'ownstay_delete_own_property' or 'ownstay_manage_properties' capability.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_delete_property_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to delete a property.', 'ownstay-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'ownstay_delete_own_property' ) && ! current_user_can( 'ownstay_manage_properties' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to delete this property.', 'ownstay-core' ),
				array( 'status' => 403 )
			);
		}

		// Enforce resource ownership check (Admin can bypass)
		$ownership = ownstay_verify_resource_ownership( (int) $request['id'] );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		return true;
	}

	/**
	 * Create a new property listing.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_property( $request ) {
		$params = $request->get_json_params();

		$title       = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
		$description = isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '';
		$city        = isset( $params['city'] ) ? sanitize_text_field( $params['city'] ) : 'Indore';
		$rent        = isset( $params['rent'] ) ? floatval( $params['rent'] ) : 0;

		$property_data = array(
			'title'       => $title,
			'description' => $description,
			'rent'        => $rent,
			'city'        => $city,
		);

		// Enforce Domain Business Rules for property publication:
		// 1. Owner Verified? 2. Property Invariants Valid?
		$business_check = ownstay_validate_property_publication( get_current_user_id(), $property_data );
		if ( is_wp_error( $business_check ) ) {
			return $business_check;
		}

		$post_data = array(
			'post_title'   => $title,
			'post_content' => $description,
			'post_status'  => 'publish',
			'post_type'    => 'ownstay_property',
			'post_author'  => get_current_user_id(),
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'create_failed',
				__( 'Failed to create property listing.', 'ownstay-core' ),
				array( 'status' => 500 )
			);
		}

		// Store property meta values
		update_post_meta( $post_id, '_ownstay_city', $city );
		update_post_meta( $post_id, '_ownstay_rent', $rent );
		update_post_meta( $post_id, '_ownstay_owner_id', get_current_user_id() );

		$response_data = array(
			'id'          => $post_id,
			'title'       => $title,
			'description' => $description,
			'city'        => $city,
			'rent'        => $rent,
			'owner_id'    => get_current_user_id(),
			'status'      => 'publish',
			'created_at'  => current_time( 'mysql', true ),
		);

		return new WP_REST_Response( $response_data, 201 );
	}

	/**
	 * Get list of properties.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_properties( $request ) {
		$args = array(
			'post_type'      => 'ownstay_property',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
		);

		$query      = new WP_Query( $args );
		$properties = array();

		foreach ( $query->posts as $post ) {
			$properties[] = array(
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'description' => $post->post_content,
				'city'        => get_post_meta( $post->ID, '_ownstay_city', true ),
				'rent'        => (float) get_post_meta( $post->ID, '_ownstay_rent', true ),
				'owner_id'    => (int) $post->post_author,
			);
		}

		return new WP_REST_Response( $properties, 200 );
	}

	/**
	 * Get a single property by ID.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_property_by_id( $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post || 'ownstay_property' !== $post->post_type ) {
			return new WP_Error(
				'not_found',
				__( 'Property not found.', 'ownstay-core' ),
				array( 'status' => 404 )
			);
		}

		$data = array(
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'description' => $post->post_content,
			'city'        => get_post_meta( $post->ID, '_ownstay_city', true ),
			'rent'        => (float) get_post_meta( $post->ID, '_ownstay_rent', true ),
			'owner_id'    => (int) $post->post_author,
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Update a property listing.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_property( $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post || 'ownstay_property' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Property not found.', 'ownstay-core' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params();

		$title       = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : $post->post_title;
		$description = isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : $post->post_content;
		$city        = isset( $params['city'] ) ? sanitize_text_field( $params['city'] ) : get_post_meta( $post_id, '_ownstay_city', true );
		$rent        = isset( $params['rent'] ) ? floatval( $params['rent'] ) : floatval( get_post_meta( $post_id, '_ownstay_rent', true ) );

		$property_data = array(
			'title'       => $title,
			'description' => $description,
			'rent'        => $rent,
			'city'        => $city,
		);

		$business_check = ownstay_validate_property_publication( get_current_user_id(), $property_data );
		if ( is_wp_error( $business_check ) ) {
			return $business_check;
		}

		$post_data = array(
			'ID' => $post_id,
		);

		if ( isset( $params['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $params['title'] );
		}
		if ( isset( $params['description'] ) ) {
			$post_data['post_content'] = sanitize_textarea_field( $params['description'] );
		}

		wp_update_post( $post_data );

		if ( isset( $params['city'] ) ) {
			update_post_meta( $post_id, '_ownstay_city', sanitize_text_field( $params['city'] ) );
		}
		if ( isset( $params['rent'] ) ) {
			update_post_meta( $post_id, '_ownstay_rent', floatval( $params['rent'] ) );
		}

		return $this->get_property_by_id( $request );
	}

	/**
	 * Delete a property listing.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_property( $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post || 'ownstay_property' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Property not found.', 'ownstay-core' ), array( 'status' => 404 ) );
		}

		wp_delete_post( $post_id, true );

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $post_id ), 200 );
	}

	/**
	 * Get schema validation args for property endpoint.
	 *
	 * @return array
	 */
	public function get_property_schema_args() {
		return array(
			'title'       => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'city'        => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'rent'        => array(
				'required'          => false,
				'type'              => 'number',
			),
		);
	}
}
