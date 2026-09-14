<?php
/**
 * ApnaStay Property REST API Controller.
 * Enforces real backend RBAC security boundaries using current_user_can() capability checks.
 *
 * @package ApnaStay_Core
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

		// POST /wp-json/apnastay/v1/owner/properties/<id>/photos (Phase 5)
		register_rest_route(
			$this->namespace,
			"/owner/properties/(?P<id>[a-zA-Z0-9_-]+)/photos",
			array(
				array(
					"methods"             => WP_REST_Server::CREATABLE,
					"callback"            => array( $this, "upload_owner_photo" ),
					"permission_callback" => array( $this, "check_owner_authenticated" ),
				),
			)
		);

		// PUT /wp-json/apnastay/v1/owner/properties/<id>/photos/reorder (Phase 5)
		register_rest_route(
			$this->namespace,
			"/owner/properties/(?P<id>[a-zA-Z0-9_-]+)/photos/reorder",
			array(
				array(
					"methods"             => WP_REST_Server::EDITABLE,
					"callback"            => array( $this, "reorder_owner_photos" ),
					"permission_callback" => array( $this, "check_owner_authenticated" ),
				),
			)
		);

		// PUT & DELETE /wp-json/apnastay/v1/owner/properties/<id>/photos/<photo_id> (Phase 5)
		register_rest_route(
			$this->namespace,
			"/owner/properties/(?P<id>[a-zA-Z0-9_-]+)/photos/(?P<photo_id>[a-zA-Z0-9_-]+)",
			array(
				array(
					"methods"             => WP_REST_Server::EDITABLE,
					"callback"            => array( $this, "update_owner_photo" ),
					"permission_callback" => array( $this, "check_owner_authenticated" ),
				),
				array(
					"methods"             => WP_REST_Server::DELETABLE,
					"callback"            => array( $this, "delete_owner_photo" ),
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

		// POST /wp-json/apnastay/v1/owner/properties/<id>/publish
		register_rest_route(
			$this->namespace,
			"/owner/properties/(?P<id>[a-zA-Z0-9_-]+)/publish",
			array(
				array(
					"methods"             => WP_REST_Server::CREATABLE,
					"callback"            => array( $this, "publish_owner_property" ),
					"permission_callback" => array( $this, "check_owner_authenticated" ),
				),
			)
		);

		// POST /wp-json/apnastay/v1/owner/properties/<id>/unpublish
		register_rest_route(
			$this->namespace,
			"/owner/properties/(?P<id>[a-zA-Z0-9_-]+)/unpublish",
			array(
				array(
					"methods"             => WP_REST_Server::CREATABLE,
					"callback"            => array( $this, "unpublish_owner_property" ),
					"permission_callback" => array( $this, "check_owner_authenticated" ),
				),
			)
		);

		// POST /wp-json/apnastay/v1/owner/properties/<id>/archive
		register_rest_route(
			$this->namespace,
			"/owner/properties/(?P<id>[a-zA-Z0-9_-]+)/archive",
			array(
				array(
					"methods"             => WP_REST_Server::CREATABLE,
					"callback"            => array( $this, "archive_owner_property" ),
					"permission_callback" => array( $this, "check_owner_authenticated" ),
				),
			)
		);

		// POST /wp-json/apnastay/v1/owner/properties/<id>/restore
		register_rest_route(
			$this->namespace,
			"/owner/properties/(?P<id>[a-zA-Z0-9_-]+)/restore",
			array(
				array(
					"methods"             => WP_REST_Server::CREATABLE,
					"callback"            => array( $this, "restore_owner_property" ),
					"permission_callback" => array( $this, "check_owner_authenticated" ),
				),
			)
		);

		// POST /wp-json/apnastay/v1/owner/properties/<id>/duplicate
		register_rest_route(
			$this->namespace,
			"/owner/properties/(?P<id>[a-zA-Z0-9_-]+)/duplicate",
			array(
				array(
					"methods"             => WP_REST_Server::CREATABLE,
					"callback"            => array( $this, "duplicate_owner_property" ),
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
				__( 'You must be logged in to list a property.', 'apnastay-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'apnastay_create_property' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to create property listings.', 'apnastay-core' ),
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
				__( 'You must be logged in to edit a property.', 'apnastay-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'apnastay_edit_own_property' ) && ! current_user_can( 'apnastay_manage_properties' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to edit this property.', 'apnastay-core' ),
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
				__( 'You must be logged in to delete a property.', 'apnastay-core' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'apnastay_delete_own_property' ) && ! current_user_can( 'apnastay_manage_properties' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to delete this property.', 'apnastay-core' ),
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
				__( 'Failed to create property listing.', 'apnastay-core' ),
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
		$city_param = $request->get_param( 'city' );
		$limit      = $request->get_param( 'limit' ) ? (int) $request->get_param( 'limit' ) : 50;

		$args = array(
			'post_type'      => 'apnastay_property',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $city_param ) && 'all' !== strtolower( $city_param ) ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_apnastay_city',
					'value'   => sanitize_text_field( $city_param ),
					'compare' => 'LIKE',
				),
			);
		}

		$query      = new WP_Query( $args );
		$properties = array();

		foreach ( $query->posts as $post ) {
			$prop_id   = $post->ID;
			$prop_type = get_post_meta( $prop_id, '_apnastay_property_type', true ) ?: 'house';
			$structure = get_post_meta( $prop_id, '_apnastay_rental_structure', true ) ?: 'entire_property';
			$rent      = (float) get_post_meta( $prop_id, '_apnastay_rent', true );
			$city      = get_post_meta( $prop_id, '_apnastay_city', true ) ?: '';
			$addr1     = get_post_meta( $prop_id, '_apnastay_address_line1', true );
			$locality  = get_post_meta( $prop_id, '_apnastay_locality', true );
			$state     = get_post_meta( $prop_id, '_apnastay_state', true );
			$pincode   = get_post_meta( $prop_id, '_apnastay_pincode', true );
			$p_raw     = get_post_meta( $prop_id, '_apnastay_photos', true );
			$photos    = $this->normalize_property_photos( is_array( $p_raw ) ? $p_raw : ( json_decode( $p_raw, true ) ?: array() ) );
			$am_raw    = get_post_meta( $prop_id, '_apnastay_amenities', true );
			$amenities = is_array( $am_raw ) ? $am_raw : ( json_decode( $am_raw, true ) ?: array() );
			$cm_raw    = get_post_meta( $prop_id, '_apnastay_custom_amenities', true );
			$custom_am = is_array( $cm_raw ) ? $cm_raw : ( json_decode( $cm_raw, true ) ?: array() );
			$all_am    = array_merge( $amenities, $custom_am );

			$cover_photo = '';
			foreach ( $photos as $p ) {
				if ( ! empty( $p['isCover'] ) && ! empty( $p['url'] ) ) {
					$cover_photo = $p['url'];
					break;
				}
			}
			if ( empty( $cover_photo ) && ! empty( $photos[0]['url'] ) ) {
				$cover_photo = $photos[0]['url'];
			}

			$properties[] = array(
				'id'                 => 'prop-' . $prop_id,
				'numericId'          => $prop_id,
				'title'              => $post->post_title,
				'description'        => $post->post_content,
				'status'             => 'published',
				'propertyType'       => $prop_type,
				'rentalStructure'    => $structure,
				'city'               => $city,
				'rent'               => $rent,
				'price'              => $rent,
				'pricing'            => array( 'monthlyRent' => $rent ),
				'location'           => array(
					'city'         => $city,
					'locality'     => $locality ?: '',
					'addressLine1' => $addr1 ?: '',
					'state'        => $state ?: '',
					'pincode'      => $pincode ?: '',
				),
				'coverPhotoUrl'      => $cover_photo,
				'photos'             => $photos,
				'amenities'          => $all_am,
				'owner_id'           => (int) $post->post_author,
				'publishedAt'        => get_post_meta( $prop_id, '_apnastay_published_at', true ) ?: $post->post_date_gmt,
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
				__( 'Property not found.', 'apnastay-core' ),
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
			return new WP_Error( 'not_found', __( 'Property not found.', 'apnastay-core' ), array( 'status' => 404 ) );
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
			return new WP_Error( 'not_found', __( 'Property not found.', 'apnastay-core' ), array( 'status' => 404 ) );
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
				__( "You must be logged in to manage owner properties.", "apnastay-core" ),
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
			"photos"             => array(),
			"units"              => array(),
			"createdAt"          => current_time( "mysql", true ),
			"updatedAt"          => current_time( "mysql", true ),
		);

		return new WP_REST_Response( array( "success" => true, "data" => $property ), 201 );
	}

	/**
	 * Get owner properties list.
	 */
	
	/**
	 * Normalize property photos so that attachment URLs always resolve dynamically
	 * to the current active environment (avoiding hardcoded production or localhost domains).
	 */
	private function normalize_property_photos( $photos ) {
		if ( empty( $photos ) || ! is_array( $photos ) ) {
			return array();
		}

		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'];
		$normalized = array();

		foreach ( $photos as $photo ) {
			if ( ! is_array( $photo ) ) {
				continue;
			}

			// If photo has an attachment ID, dynamically resolve its URL for current environment
			if ( ! empty( $photo['id'] ) && is_numeric( $photo['id'] ) ) {
				$att_id = (int) $photo['id'];
				$url = wp_get_attachment_url( $att_id );
				if ( $url ) {
					$photo['url'] = $url;
					$thumb_data = wp_get_attachment_image_src( $att_id, 'medium' );
					$photo['thumbnailUrl'] = $thumb_data ? $thumb_data[0] : $url;
				}
			} else {
				// If URL contains cms.apnastay.in but we are currently running on localhost,
				// rewrite the base URL so the browser can load the local asset.
				if ( ! empty( $photo['url'] ) && strpos( $photo['url'], 'https://cms.apnastay.in/wp-content/uploads' ) !== false ) {
					$photo['url'] = str_replace( 'https://cms.apnastay.in/wp-content/uploads', $base_url, $photo['url'] );
				}
				if ( ! empty( $photo['thumbnailUrl'] ) && strpos( $photo['thumbnailUrl'], 'https://cms.apnastay.in/wp-content/uploads' ) !== false ) {
					$photo['thumbnailUrl'] = str_replace( 'https://cms.apnastay.in/wp-content/uploads', $base_url, $photo['thumbnailUrl'] );
				}
			}

			$normalized[] = $photo;
		}

		return $normalized;
	}

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
				"photos"             => $this->normalize_property_photos( is_array( $p_raw = get_post_meta( $prop_id, "_apnastay_photos", true ) ) ? $p_raw : ( json_decode( $p_raw, true ) ?: array() ) ),
				"amenities"          => is_array( $am_raw = get_post_meta( $prop_id, "_apnastay_amenities", true ) ) ? $am_raw : ( json_decode( $am_raw, true ) ?: array() ),
				"customAmenities"    => is_array( $cm_raw = get_post_meta( $prop_id, "_apnastay_custom_amenities", true ) ) ? $cm_raw : ( json_decode( $cm_raw, true ) ?: array() ),
				"units"              => is_array( $u_raw = get_post_meta( $prop_id, "_apnastay_units", true ) ) ? $u_raw : ( json_decode( $u_raw, true ) ?: array() ),
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
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-core" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-core" ), array( "status" => 403 ) );
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
		$photos_raw = get_post_meta( $post_id, "_apnastay_photos", true );
		$photos     = $this->normalize_property_photos( is_array( $photos_raw ) ? $photos_raw : ( json_decode( $photos_raw, true ) ?: array() ) );
		$amenities_raw = get_post_meta( $post_id, "_apnastay_amenities", true );
		$amenities     = is_array( $amenities_raw ) ? $amenities_raw : ( json_decode( $amenities_raw, true ) ?: array() );
		$custom_raw    = get_post_meta( $post_id, "_apnastay_custom_amenities", true );
		$custom_amenities = is_array( $custom_raw ) ? $custom_raw : ( json_decode( $custom_raw, true ) ?: array() );

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
			"photos"             => $photos,
			"amenities"          => $amenities,
			"customAmenities"    => $custom_amenities,
			"units"              => is_array( $u_raw = get_post_meta( $post_id, "_apnastay_units", true ) ) ? $u_raw : ( json_decode( $u_raw, true ) ?: array() ),
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
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-core" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-core" ), array( "status" => 403 ) );
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
		if ( isset( $params["photos"] ) && is_array( $params["photos"] ) ) {
			update_post_meta( $post_id, "_apnastay_photos", $params["photos"] );
		}

		if ( isset( $params["amenities"] ) && is_array( $params["amenities"] ) ) {
			update_post_meta( $post_id, "_apnastay_amenities", array_values( array_unique( $params["amenities"] ) ) );
		}

		if ( isset( $params["customAmenities"] ) && is_array( $params["customAmenities"] ) ) {
			update_post_meta( $post_id, "_apnastay_custom_amenities", array_values( array_unique( $params["customAmenities"] ) ) );
		}

		if ( isset( $params["units"] ) && is_array( $params["units"] ) ) {
			update_post_meta( $post_id, "_apnastay_units", $params["units"] );
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
		$stored_photos = get_post_meta( $post_id, "_apnastay_photos", true );
		if ( ! empty( $stored_photos ) && is_array( $stored_photos ) && count( $stored_photos ) > 0 ) {
			$score += 20;
		}
		$stored_am = get_post_meta( $post_id, "_apnastay_amenities", true );
		$stored_cm = get_post_meta( $post_id, "_apnastay_custom_amenities", true );
		if ( ( ! empty( $stored_am ) && is_array( $stored_am ) && count( $stored_am ) > 0 ) ||
		     ( ! empty( $stored_cm ) && is_array( $stored_cm ) && count( $stored_cm ) > 0 ) ) {
			$score += 10;
		}
		$stored_units = get_post_meta( $post_id, "_apnastay_units", true );
		if ( ! empty( $stored_units ) && is_array( $stored_units ) && count( $stored_units ) > 0 ) {
			$score += 23;
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

	/**
	 * Upload photo to owner property.
	 */
	public function upload_owner_photo( $request ) {
		$raw_id  = $request["id"];
		$post_id = (int) str_replace( "prop-", "", $raw_id );
		$post    = get_post( $post_id );

		if ( ! $post || "apnastay_property" !== $post->post_type ) {
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-core" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-core" ), array( "status" => 403 ) );
		}

		$photos_raw = get_post_meta( $post_id, "_apnastay_photos", true );
		$photos     = is_array( $photos_raw ) ? $photos_raw : ( json_decode( $photos_raw, true ) ?: array() );

		$files = $request->get_file_params();
		$params = $request->get_params();

		$photo_id = "photo_" . time() . "_" . wp_generate_password( 6, false );
		$url = "";
		$thumbnail_url = "";
		$filename = "";
		$filesize = 0;
		$mime_type = "image/jpeg";

		if ( ! empty( $files["file"] ) ) {
			require_once ABSPATH . "wp-admin/includes/file.php";
			require_once ABSPATH . "wp-admin/includes/image.php";
			require_once ABSPATH . "wp-admin/includes/media.php";

			$attachment_id = media_handle_upload( "file", $post_id );
			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}
			$photo_id = $attachment_id;
			$url = wp_get_attachment_url( $attachment_id );
			$thumb_data = wp_get_attachment_image_src( $attachment_id, "medium" );
			$thumbnail_url = $thumb_data ? $thumb_data[0] : $url;
			$filename = basename( get_attached_file( $attachment_id ) );
			$filesize = filesize( get_attached_file( $attachment_id ) );
			$mime_type = get_post_mime_type( $attachment_id );
		} elseif ( ! empty( $params["dataUrl"] ) ) {
			$url = sanitize_text_field( $params["dataUrl"] );
			$thumbnail_url = $url;
			$filename = sanitize_text_field( $params["fileName"] ?? "photo.jpg" );
			$filesize = intval( $params["fileSize"] ?? 0 );
			$mime_type = sanitize_text_field( $params["mimeType"] ?? "image/jpeg" );
		}

		$is_first = empty( $photos );
		$is_cover = isset( $params["isCover"] ) ? ( $params["isCover"] === true || $params["isCover"] === "true" ) : $is_first;

		if ( $is_cover ) {
			foreach ( $photos as &$p ) {
				$p["isCover"] = false;
			}
		}

		$new_photo = array(
			"id"           => $photo_id,
			"url"          => $url,
			"thumbnailUrl" => $thumbnail_url ?: $url,
			"category"     => sanitize_text_field( $params["category"] ?? "" ),
			"isCover"      => $is_cover,
			"order"        => count( $photos ),
			"fileName"     => $filename,
			"fileSize"     => $filesize,
			"mimeType"     => $mime_type,
			"uploadedAt"   => gmdate( "Y-m-d\TH:i:s\Z" ),
		);

		$photos[] = $new_photo;
		update_post_meta( $post_id, "_apnastay_photos", $photos );

		// Update completeness score
		$score = (int) get_post_meta( $post_id, "_apnastay_completeness_score", true ) ?: 15;
		update_post_meta( $post_id, "_apnastay_completeness_score", min( 100, $score + 20 ) );

		$norm_new = $this->normalize_property_photos( array( $new_photo ) );
		return new WP_REST_Response( array( "success" => true, "data" => $norm_new[0] ), 201 );
	}

	/**
	 * Delete photo from owner property.
	 */
	public function delete_owner_photo( $request ) {
		$raw_id   = $request["id"];
		$photo_id = $request["photo_id"];
		$post_id  = (int) str_replace( "prop-", "", $raw_id );
		$post     = get_post( $post_id );

		if ( ! $post || "apnastay_property" !== $post->post_type ) {
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-core" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-core" ), array( "status" => 403 ) );
		}

		$photos_raw = get_post_meta( $post_id, "_apnastay_photos", true );
		$photos     = is_array( $photos_raw ) ? $photos_raw : ( json_decode( $photos_raw, true ) ?: array() );

		$found_index = -1;
		$deleted_is_cover = false;
		foreach ( $photos as $idx => $p ) {
			if ( (string) $p["id"] === (string) $photo_id ) {
				$found_index = $idx;
				$deleted_is_cover = ! empty( $p["isCover"] );
				break;
			}
		}

		if ( $found_index === -1 ) {
			return new WP_Error( "not_found", __( "Photo not found.", "apnastay-core" ), array( "status" => 404 ) );
		}

		array_splice( $photos, $found_index, 1 );

		// Reorder & cover fallback
		foreach ( $photos as $idx => &$p ) {
			$p["order"] = $idx;
		}
		if ( $deleted_is_cover && count( $photos ) > 0 ) {
			$photos[0]["isCover"] = true;
		}

		update_post_meta( $post_id, "_apnastay_photos", $photos );

		return new WP_REST_Response( array( "success" => true, "data" => array( "deletedPhotoId" => $photo_id, "remainingPhotos" => $this->normalize_property_photos( $photos ) ) ), 200 );
	}

	/**
	 * Reorder owner property photos.
	 */
	public function reorder_owner_photos( $request ) {
		$raw_id  = $request["id"];
		$post_id = (int) str_replace( "prop-", "", $raw_id );
		$post    = get_post( $post_id );

		if ( ! $post || "apnastay_property" !== $post->post_type ) {
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-core" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-core" ), array( "status" => 403 ) );
		}

		$params = $request->get_json_params();
		$ordered_ids = $params["photoIds"] ?? array();

		$photos_raw = get_post_meta( $post_id, "_apnastay_photos", true );
		$photos     = is_array( $photos_raw ) ? $photos_raw : ( json_decode( $photos_raw, true ) ?: array() );

		$photo_map = array();
		foreach ( $photos as $p ) {
			$photo_map[(string) $p["id"]] = $p;
		}

		$reordered = array();
		foreach ( $ordered_ids as $idx => $pid ) {
			if ( isset( $photo_map[(string) $pid] ) ) {
				$item = $photo_map[(string) $pid];
				$item["order"] = $idx;
				$reordered[] = $item;
				unset( $photo_map[(string) $pid] );
			}
		}

		foreach ( $photo_map as $p ) {
			$p["order"] = count( $reordered );
			$reordered[] = $p;
		}

		update_post_meta( $post_id, "_apnastay_photos", $reordered );

		return new WP_REST_Response( array( "success" => true, "data" => $this->normalize_property_photos( $reordered ) ), 200 );
	}

	/**
	 * Update single photo (category or isCover).
	 */
	public function update_owner_photo( $request ) {
		$raw_id   = $request["id"];
		$photo_id = $request["photo_id"];
		$post_id  = (int) str_replace( "prop-", "", $raw_id );
		$post     = get_post( $post_id );

		if ( ! $post || "apnastay_property" !== $post->post_type ) {
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-core" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-core" ), array( "status" => 403 ) );
		}

		$params = $request->get_json_params();
		$photos_raw = get_post_meta( $post_id, "_apnastay_photos", true );
		$photos     = is_array( $photos_raw ) ? $photos_raw : ( json_decode( $photos_raw, true ) ?: array() );

		$updated_photo = null;
		if ( ! empty( $params["isCover"] ) ) {
			foreach ( $photos as &$p ) {
				$p["isCover"] = ( (string) $p["id"] === (string) $photo_id );
				if ( (string) $p["id"] === (string) $photo_id ) {
					$updated_photo = $p;
				}
			}
		}

		if ( isset( $params["category"] ) ) {
			foreach ( $photos as &$p ) {
				if ( (string) $p["id"] === (string) $photo_id ) {
					$p["category"] = sanitize_text_field( $params["category"] );
					$updated_photo = $p;
				}
			}
		}

		update_post_meta( $post_id, "_apnastay_photos", $photos );

		$norm_up = $updated_photo ? $this->normalize_property_photos( array( $updated_photo ) ) : null;
		return new WP_REST_Response( array( "success" => true, "data" => $norm_up ? $norm_up[0] : null ), 200 );
	}

	/**
	 * Publish owner property.
	 */
	public function publish_owner_property( $request ) {
		$raw_id  = $request["id"];
		$post_id = (int) str_replace( "prop-", "", $raw_id );
		$post    = get_post( $post_id );

		if ( ! $post || "apnastay_property" !== $post->post_type ) {
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-core" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-core" ), array( "status" => 403 ) );
		}

		wp_update_post( array(
			"ID"          => $post_id,
			"post_status" => "publish",
		) );

		$now = current_time( "mysql", true );
		update_post_meta( $post_id, "_apnastay_published_at", $now );
		delete_post_meta( $post_id, "_apnastay_is_unpublished" );
		delete_post_meta( $post_id, "_apnastay_is_archived" );

		return $this->get_owner_property_by_id( $request );
	}

	/**
	 * Unpublish owner property.
	 */
	public function unpublish_owner_property( $request ) {
		$raw_id  = $request["id"];
		$post_id = (int) str_replace( "prop-", "", $raw_id );
		$post    = get_post( $post_id );

		if ( ! $post || "apnastay_property" !== $post->post_type ) {
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-core" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-core" ), array( "status" => 403 ) );
		}

		wp_update_post( array(
			"ID"          => $post_id,
			"post_status" => "draft",
		) );
		update_post_meta( $post_id, "_apnastay_is_unpublished", 1 );

		return $this->get_owner_property_by_id( $request );
	}

	/**
	 * Archive owner property.
	 */
	public function archive_owner_property( $request ) {
		$raw_id  = $request["id"];
		$post_id = (int) str_replace( "prop-", "", $raw_id );
		$post    = get_post( $post_id );

		if ( ! $post || "apnastay_property" !== $post->post_type ) {
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-core" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-core" ), array( "status" => 403 ) );
		}

		wp_update_post( array(
			"ID"          => $post_id,
			"post_status" => "trash",
		) );
		update_post_meta( $post_id, "_apnastay_is_archived", 1 );

		return $this->get_owner_property_by_id( $request );
	}

	/**
	 * Restore owner property.
	 */
	public function restore_owner_property( $request ) {
		$raw_id  = $request["id"];
		$post_id = (int) str_replace( "prop-", "", $raw_id );
		$post    = get_post( $post_id );

		if ( ! $post || "apnastay_property" !== $post->post_type ) {
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-core" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-core" ), array( "status" => 403 ) );
		}

		wp_update_post( array(
			"ID"          => $post_id,
			"post_status" => "draft",
		) );
		delete_post_meta( $post_id, "_apnastay_is_archived" );
		delete_post_meta( $post_id, "_apnastay_is_unpublished" );

		return $this->get_owner_property_by_id( $request );
	}

	/**
	 * Duplicate owner property.
	 */
	public function duplicate_owner_property( $request ) {
		$raw_id  = $request["id"];
		$post_id = (int) str_replace( "prop-", "", $raw_id );
		$post    = get_post( $post_id );

		if ( ! $post || "apnastay_property" !== $post->post_type ) {
			return new WP_Error( "not_found", __( "Property not found.", "apnastay-core" ), array( "status" => 404 ) );
		}

		if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( "administrator" ) ) {
			return new WP_Error( "forbidden", __( "You do not own this property.", "apnastay-core" ), array( "status" => 403 ) );
		}

		$new_post_id = wp_insert_post( array(
			"post_title"   => $post->post_title . " (Copy)",
			"post_content" => $post->post_content,
			"post_status"  => "draft",
			"post_type"    => "apnastay_property",
			"post_author"  => get_current_user_id(),
		) );

		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		$meta_keys = array(
			"_apnastay_property_type",
			"_apnastay_custom_property_type",
			"_apnastay_rental_structure",
			"_apnastay_rent",
			"_apnastay_city",
			"_apnastay_address_line1",
			"_apnastay_locality",
			"_apnastay_state",
			"_apnastay_pincode",
			"_apnastay_landmark",
			"_apnastay_latitude",
			"_apnastay_longitude",
			"_apnastay_hide_exact_address",
			"_apnastay_availability",
			"_apnastay_photos",
			"_apnastay_amenities",
			"_apnastay_custom_amenities",
			"_apnastay_units",
			"_apnastay_rules",
			"_apnastay_completeness_score",
		);

		foreach ( $meta_keys as $mk ) {
			$val = get_post_meta( $post_id, $mk, true );
			if ( ! empty( $val ) ) {
				update_post_meta( $new_post_id, $mk, $val );
			}
		}

		$req = new WP_REST_Request( "GET", "/apnastay/v1/owner/properties/prop-" . $new_post_id );
		$req->set_param( "id", "prop-" . $new_post_id );
		return $this->get_owner_property_by_id( $req );
	}
}
