<?php
/**
 * ApnaStay Property REST API Controller.
 * Enforces real backend RBAC security boundaries using current_user_can() capability checks.
 *
 * @package ApnaStay_Properties
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_Property_Controller Class.
 */
class ApnaStay_Property_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'apnastay/v1';
		$this->rest_base = 'properties';
	}

	/**
	 * Register property REST routes with strict RBAC permission callbacks.
	 */
	public function register_routes() {
		// GET & POST /wp-json/apnastay/v1/properties
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

		// ====================================================================
		// OWNER DASHBOARD PROPERTY DRAFT & PORTFOLIO ROUTES
		// ====================================================================

		// GET & POST /wp-json/apnastay/v1/owner/properties
		register_rest_route(
			$this->namespace,
			"/owner/properties",
			array(
				array(
					"methods"             => WP_REST_Server::READABLE,
					"callback"            => array( $this, "get_owner_properties" ),
					"permission_callback" => array( $this, "check_owner_authenticated" ),
				),
				array(
					"methods"             => WP_REST_Server::CREATABLE,
					"callback"            => array( $this, "create_owner_draft" ),
					"permission_callback" => array( $this, "check_owner_authenticated" ),
				),
			)
		);

		// GET, PUT/PATCH, DELETE /wp-json/apnastay/v1/owner/properties/<id>
		register_rest_route(
			$this->namespace,
			"/owner/properties/(?P<id>[a-zA-Z0-9_-]+)",
			array(
				array(
					"methods"             => WP_REST_Server::READABLE,
					"callback"            => array( $this, "get_owner_property_by_id" ),
					"permission_callback" => array( $this, "check_owner_authenticated" ),
				),
				array(
					"methods"             => WP_REST_Server::EDITABLE,
					"callback"            => array( $this, "update_owner_property" ),
					"permission_callback" => array( $this, "check_owner_authenticated" ),
				),
				array(
					"methods"             => WP_REST_Server::DELETABLE,
					"callback"            => array( $this, "delete_owner_property" ),
					"permission_callback" => array( $this, "check_owner_authenticated" ),
				),
			)
		);

		// GET, PUT/PATCH, DELETE /wp-json/apnastay/v1/properties/<id>
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
	 * Permission check: User must possess 'apnastay_create_property' capability.
	 * Even if a Tenant manually calls POST /wp-json/apnastay/v1/properties, this returns 403 Forbidden.
	 *
	 * @return bool|WP_Error
	 */
	public function check_create_property_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to list a property.', 'apnastay-properties' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'apnastay_create_property' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to create property listings.', 'apnastay-properties' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission check: User must possess 'apnastay_edit_own_property' or 'apnastay_manage_properties' capability.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_edit_property_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to edit a property.', 'apnastay-properties' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'apnastay_edit_own_property' ) && ! current_user_can( 'apnastay_manage_properties' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to edit this property.', 'apnastay-properties' ),
				array( 'status' => 403 )
			);
		}

		// Enforce resource ownership check (Admin can bypass)
		$ownership = apnastay_verify_resource_ownership( (int) $request['id'] );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		return true;
	}

	/**
	 * Permission check: User must possess 'apnastay_delete_own_property' or 'apnastay_manage_properties' capability.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_delete_property_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to delete a property.', 'apnastay-properties' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'apnastay_delete_own_property' ) && ! current_user_can( 'apnastay_manage_properties' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to delete this property.', 'apnastay-properties' ),
				array( 'status' => 403 )
			);
		}

		// Enforce resource ownership check (Admin can bypass)
		$ownership = apnastay_verify_resource_ownership( (int) $request['id'] );
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
		$business_check = apnastay_validate_property_publication( get_current_user_id(), $property_data );
		if ( is_wp_error( $business_check ) ) {
			return $business_check;
		}

		$post_data = array(
			'post_title'   => $title,
			'post_content' => $description,
			'post_status'  => 'publish',
			'post_type'    => 'apnastay_property',
			'post_author'  => get_current_user_id(),
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'create_failed',
				__( 'Failed to create property listing.', 'apnastay-properties' ),
				array( 'status' => 500 )
			);
		}

		// Store property meta values
		update_post_meta( $post_id, '_apnastay_city', $city );
		update_post_meta( $post_id, '_apnastay_rent', $rent );
		update_post_meta( $post_id, '_apnastay_owner_id', get_current_user_id() );

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
			'post_type'      => 'apnastay_property',
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
				'city'        => get_post_meta( $post->ID, '_apnastay_city', true ),
				'rent'        => (float) get_post_meta( $post->ID, '_apnastay_rent', true ),
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

		if ( ! $post || 'apnastay_property' !== $post->post_type ) {
			return new WP_Error(
				'not_found',
				__( 'Property not found.', 'apnastay-properties' ),
				array( 'status' => 404 )
			);
		}

		$data = array(
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'description' => $post->post_content,
			'city'        => get_post_meta( $post->ID, '_apnastay_city', true ),
			'rent'        => (float) get_post_meta( $post->ID, '_apnastay_rent', true ),
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

		if ( ! $post || 'apnastay_property' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Property not found.', 'apnastay-properties' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params();

		$title       = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : $post->post_title;
		$description = isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : $post->post_content;
		$city        = isset( $params['city'] ) ? sanitize_text_field( $params['city'] ) : get_post_meta( $post_id, '_apnastay_city', true );
		$rent        = isset( $params['rent'] ) ? floatval( $params['rent'] ) : floatval( get_post_meta( $post_id, '_apnastay_rent', true ) );

		$property_data = array(
			'title'       => $title,
			'description' => $description,
			'rent'        => $rent,
			'city'        => $city,
		);

		$business_check = apnastay_validate_property_publication( get_current_user_id(), $property_data );
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
			update_post_meta( $post_id, '_apnastay_city', sanitize_text_field( $params['city'] ) );
		}
		if ( isset( $params['rent'] ) ) {
			update_post_meta( $post_id, '_apnastay_rent', floatval( $params['rent'] ) );
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

		if ( ! $post || 'apnastay_property' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Property not found.', 'apnastay-properties' ), array( 'status' => 404 ) );
		}

		wp_delete_post( $post_id, true );

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $post_id ), 200 );
	}

	/**
	 * Permission check: User must be logged in.
	 */
	public function check_owner_authenticated() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				"unauthorized",
				__( "You must be logged in to manage owner properties.", "apnastay-properties" ),
				array( "status" => 401 )
			);
		}
		return true;
	}

	/**
	 * Create property draft for owner.
	 */
	public function create_owner_draft( $request ) {
		$params = $request->get_json_params();

		$property_type    = isset( $params["propertyType"] ) ? sanitize_text_field( $params["propertyType"] ) : "house";
		$rental_structure = isset( $params["rentalStructure"] ) ? sanitize_text_field( $params["rentalStructure"] ) : "entire_property";
		$custom_type      = isset( $params["customPropertyType"] ) ? sanitize_text_field( $params["customPropertyType"] ) : "";
		$title            = isset( $params["title"] ) ? sanitize_text_field( $params["title"] ) : "New Property Draft";
		$user_id          = get_current_user_id();

		$post_id = wp_insert_post( array(
			"post_title"   => $title,
			"post_content" => "",
			"post_status"  => "draft",
			"post_type"    => "apnastay_property",
			"post_author"  => $user_id,
		), true );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( "create_draft_failed", $post_id->get_error_message(), array( "status" => 500 ) );
		}

		update_post_meta( $post_id, "_apnastay_property_type", $property_type );
		update_post_meta( $post_id, "_apnastay_rental_structure", $rental_structure );
		update_post_meta( $post_id, "_apnastay_custom_property_type", $custom_type );
		update_post_meta( $post_id, "_apnastay_completeness_score", 15 );
		update_post_meta( $post_id, "_apnastay_status", "draft" );

		$property = array(
			"id"                 => "prop-" . $post_id,
			"ownerId"            => $user_id,
			"propertyType"       => $property_type,
			"customPropertyType" => $custom_type,
			"rentalStructure"    => $rental_structure,
			"title"              => $title,
			"description"        => "",
			"status"             => "draft",
			"completenessScore"  => 15,
			"units"              => array(),
			"createdAt"          => current_time( "mysql", true ),
			"updatedAt"          => current_time( "mysql", true ),
		);

		return new WP_REST_Response( array( "success" => true, "data" => $property ), 201 );
	}

	/**
	 * Get owner properties list.
	 */
	public function get_owner_properties( $request ) {
		$user_id = get_current_user_id();
		$args    = array(
			"post_type"      => "apnastay_property",
			"post_status"    => array( "draft", "publish", "private" ),
			"author"         => $user_id,
			"posts_per_page" => 100,
			"orderby"        => "modified",
			"order"          => "DESC",
		);

		$query      = new WP_Query( $args );
		$properties = array();

		foreach ( $query->posts as $post ) {
			$prop_id   = $post->ID;
			$prop_type = get_post_meta( $prop_id, "_apnastay_property_type", true ) ?: "house";
			$structure = get_post_meta( $prop_id, "_apnastay_rental_structure", true ) ?: "entire_property";
			$score     = (int) get_post_meta( $prop_id, "_apnastay_completeness_score", true ) ?: 15;
			$rent       = (float) get_post_meta( $prop_id, "_apnastay_rent", true );
			$city       = get_post_meta( $prop_id, "_apnastay_city", true );
			$addr1      = get_post_meta( $prop_id, "_apnastay_address_line1", true );
			$locality   = get_post_meta( $prop_id, "_apnastay_locality", true );
			$state      = get_post_meta( $prop_id, "_apnastay_state", true );
			$pincode    = get_post_meta( $prop_id, "_apnastay_pincode", true );
			$landmark   = get_post_meta( $prop_id, "_apnastay_landmark", true );
			$lat        = get_post_meta( $prop_id, "_apnastay_latitude", true );
			$lng        = get_post_meta( $prop_id, "_apnastay_longitude", true );
			$hide_exact = get_post_meta( $prop_id, "_apnastay_hide_exact_address", true );
			$avail_raw  = get_post_meta( $prop_id, "_apnastay_availability", true );
			$avail      = is_array( $avail_raw ) ? $avail_raw : json_decode( $avail_raw, true );

			$location = ( $city || $addr1 || $locality || $pincode ) ? array(
				"addressLine1"     => $addr1 ?: "",
				"locality"         => $locality ?: "",
				"city"             => $city ?: "",
				"state"            => $state ?: "",
				"pincode"          => $pincode ?: "",
				"landmark"         => $landmark ?: "",
				"latitude"         => $lat !== "" && $lat !== false ? floatval( $lat ) : null,
				"longitude"        => $lng !== "" && $lng !== false ? floatval( $lng ) : null,
				"hideExactAddress" => (bool) $hide_exact,
			) : null;

			$properties[] = array(
				"id"                 => "prop-" . $prop_id,
				"ownerId"            => (int) $post->post_author,
				"propertyType"       => $prop_type,
				"customPropertyType" => get_post_meta( $prop_id, "_apnastay_custom_property_type", true ),
				"rentalStructure"    => $structure,
				"title"              => $post->post_title,
				"description"        => $post->post_content,
				"status"             => $post->post_status === "publish" ? "published" : "draft",
				"completenessScore"  => $score,
				"pricing"            => array( "monthlyRent" => $rent ),
				"availability"       => $avail ?: array( "type" => "immediate" ),
				"location"           => $location,
				"units"              => array(),
				"createdAt"          => $post->post_date_gmt,
				"updatedAt"          => $post->post_modified_gmt,
			);
		}

		return new WP_REST_Response( array( "success" => true, "data" => $properties ), 200 );
	}

	/**
	 * Get owner property by ID.
	 */
	public function get_owner_property_by_id( $request ) {
		$raw_id  = $request["id"];
		$post_id = (int) str_replace( "prop-", "", $raw_id );
		$post    = get_post( $post_id );

		if ( ! $post || "apnastay_property" !== $post->post_type ) {
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-properties" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-properties" ), array( "status" => 403 ) );
		}

		$prop_type = get_post_meta( $post_id, "_apnastay_property_type", true ) ?: "house";
		$structure = get_post_meta( $post_id, "_apnastay_rental_structure", true ) ?: "entire_property";
		$score     = (int) get_post_meta( $post_id, "_apnastay_completeness_score", true ) ?: 15;
		$rent       = (float) get_post_meta( $post_id, "_apnastay_rent", true );
		$city       = get_post_meta( $post_id, "_apnastay_city", true );
		$addr1      = get_post_meta( $post_id, "_apnastay_address_line1", true );
		$locality   = get_post_meta( $post_id, "_apnastay_locality", true );
		$state      = get_post_meta( $post_id, "_apnastay_state", true );
		$pincode    = get_post_meta( $post_id, "_apnastay_pincode", true );
		$landmark   = get_post_meta( $post_id, "_apnastay_landmark", true );
		$lat        = get_post_meta( $post_id, "_apnastay_latitude", true );
		$lng        = get_post_meta( $post_id, "_apnastay_longitude", true );
		$hide_exact = get_post_meta( $post_id, "_apnastay_hide_exact_address", true );
		$avail_raw  = get_post_meta( $post_id, "_apnastay_availability", true );
		$avail      = is_array( $avail_raw ) ? $avail_raw : json_decode( $avail_raw, true );

		$location = ( $city || $addr1 || $locality || $pincode ) ? array(
			"addressLine1"     => $addr1 ?: "",
			"locality"         => $locality ?: "",
			"city"             => $city ?: "",
			"state"            => $state ?: "",
			"pincode"          => $pincode ?: "",
			"landmark"         => $landmark ?: "",
			"latitude"         => $lat !== "" && $lat !== false ? floatval( $lat ) : null,
			"longitude"        => $lng !== "" && $lng !== false ? floatval( $lng ) : null,
			"hideExactAddress" => (bool) $hide_exact,
		) : null;

		$property = array(
			"id"                 => "prop-" . $post_id,
			"ownerId"            => (int) $post->post_author,
			"propertyType"       => $prop_type,
			"customPropertyType" => get_post_meta( $post_id, "_apnastay_custom_property_type", true ),
			"rentalStructure"    => $structure,
			"title"              => $post->post_title,
			"description"        => $post->post_content,
			"status"             => $post->post_status === "publish" ? "published" : "draft",
			"completenessScore"  => $score,
			"pricing"            => array( "monthlyRent" => $rent ),
			"availability"       => $avail ?: array( "type" => "immediate" ),
			"location"           => $location,
			"units"              => array(),
			"createdAt"          => $post->post_date_gmt,
			"updatedAt"          => $post->post_modified_gmt,
		);

		return new WP_REST_Response( array( "success" => true, "data" => $property ), 200 );
	}

	/**
	 * Update owner property.
	 */
	public function update_owner_property( $request ) {
		$raw_id  = $request["id"];
		$post_id = (int) str_replace( "prop-", "", $raw_id );
		$post    = get_post( $post_id );

		if ( ! $post || "apnastay_property" !== $post->post_type ) {
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-properties" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-properties" ), array( "status" => 403 ) );
		}

		$params = $request->get_json_params();
		$post_update = array( "ID" => $post_id );

		if ( isset( $params["title"] ) ) {
			$post_update["post_title"] = sanitize_text_field( $params["title"] );
		}
		if ( isset( $params["description"] ) ) {
			$post_update["post_content"] = sanitize_textarea_field( $params["description"] );
		}
		if ( isset( $params["status"] ) && in_array( $params["status"], array( "draft", "published", "unpublished", "archived" ), true ) ) {
			$post_update["post_status"] = $params["status"] === "published" ? "publish" : "draft";
		}

		wp_update_post( $post_update );

		if ( isset( $params["propertyType"] ) ) {
			update_post_meta( $post_id, "_apnastay_property_type", sanitize_text_field( $params["propertyType"] ) );
		}
		if ( isset( $params["rentalStructure"] ) ) {
			update_post_meta( $post_id, "_apnastay_rental_structure", sanitize_text_field( $params["rentalStructure"] ) );
		}
		if ( isset( $params["customPropertyType"] ) ) {
			update_post_meta( $post_id, "_apnastay_custom_property_type", sanitize_text_field( $params["customPropertyType"] ) );
		}
		if ( isset( $params["availability"] ) ) {
			update_post_meta( $post_id, "_apnastay_availability", wp_json_encode( $params["availability"] ) );
		}
		if ( isset( $params["pricing"]["monthlyRent"] ) ) {
			update_post_meta( $post_id, "_apnastay_rent", floatval( $params["pricing"]["monthlyRent"] ) );
		}

		if ( isset( $params["location"] ) && is_array( $params["location"] ) ) {
			if ( isset( $params["location"]["addressLine1"] ) ) {
				update_post_meta( $post_id, "_apnastay_address_line1", sanitize_text_field( $params["location"]["addressLine1"] ) );
			}
			if ( isset( $params["location"]["locality"] ) ) {
				update_post_meta( $post_id, "_apnastay_locality", sanitize_text_field( $params["location"]["locality"] ) );
			}
			if ( isset( $params["location"]["city"] ) ) {
				update_post_meta( $post_id, "_apnastay_city", sanitize_text_field( $params["location"]["city"] ) );
			}
			if ( isset( $params["location"]["state"] ) ) {
				update_post_meta( $post_id, "_apnastay_state", sanitize_text_field( $params["location"]["state"] ) );
			}
			if ( isset( $params["location"]["pincode"] ) ) {
				update_post_meta( $post_id, "_apnastay_pincode", sanitize_text_field( $params["location"]["pincode"] ) );
			}
			if ( isset( $params["location"]["landmark"] ) ) {
				update_post_meta( $post_id, "_apnastay_landmark", sanitize_text_field( $params["location"]["landmark"] ) );
			}
			if ( isset( $params["location"]["latitude"] ) ) {
				update_post_meta( $post_id, "_apnastay_latitude", floatval( $params["location"]["latitude"] ) );
			}
			if ( isset( $params["location"]["longitude"] ) ) {
				update_post_meta( $post_id, "_apnastay_longitude", floatval( $params["location"]["longitude"] ) );
			}
			if ( isset( $params["location"]["hideExactAddress"] ) ) {
				update_post_meta( $post_id, "_apnastay_hide_exact_address", ! empty( $params["location"]["hideExactAddress"] ) ? 1 : 0 );
			}
		}

		// Update completeness
		$score = 15;
		$stored_title = get_the_title( $post_id );
		$stored_desc  = get_post_field( 'post_content', $post_id );
		$stored_rent  = get_post_meta( $post_id, '_apnastay_rent', true );
		$stored_avail = get_post_meta( $post_id, '_apnastay_availability', true );
		$stored_city  = get_post_meta( $post_id, '_apnastay_city', true );
		$stored_addr  = get_post_meta( $post_id, '_apnastay_address_line1', true );
		$stored_pin   = get_post_meta( $post_id, '_apnastay_pincode', true );

		if ( ! empty( $stored_title ) && strlen( $stored_title ) >= 3 && ! str_starts_with( $stored_title, 'New ' ) ) {
			$score += 5;
		}
		if ( ! empty( $stored_desc ) && strlen( $stored_desc ) >= 10 ) $score += 5;
		if ( ! empty( $stored_rent ) && floatval( $stored_rent ) > 0 ) $score += 5;
		if ( ! empty( $stored_avail ) ) $score += 5;
		if ( ! empty( $stored_city ) && ! empty( $stored_addr ) && ! empty( $stored_pin ) ) {
			$score += 15;
		} elseif ( ! empty( $stored_city ) ) {
			$score += 8;
		}
		update_post_meta( $post_id, "_apnastay_completeness_score", min( 100, $score ) );

		return $this->get_owner_property_by_id( $request );
	}

	/**
	 * Delete owner property.
	 */
	public function delete_owner_property( $request ) {
		return $this->delete_property( $request );
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
