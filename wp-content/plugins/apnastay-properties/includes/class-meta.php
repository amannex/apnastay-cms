<?php
/**
 * ApnaStay Properties Meta Field Registrations.
 *
 * @package ApnaStay_Properties
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_Meta Class.
 */
class ApnaStay_Meta {

	/**
	 * Initialize post meta registrations.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * Register Post Meta Fields for REST API & Storage.
	 */
	public static function register_meta() {
		// Rental Structure
		register_post_meta(
			'apnastay_property',
			'_apnastay_rental_structure',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);

		// Location Data
		register_post_meta(
			'apnastay_property',
			'_apnastay_location_data',
			array(
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'addressLine1' => array( 'type' => 'string' ),
							'addressLine2' => array( 'type' => 'string' ),
							'city'         => array( 'type' => 'string' ),
							'state'        => array( 'type' => 'string' ),
							'pincode'      => array( 'type' => 'string' ),
							'landmark'     => array( 'type' => 'string' ),
							'latitude'     => array( 'type' => 'number' ),
							'longitude'    => array( 'type' => 'number' ),
						),
					),
				),
				'single'       => true,
				'type'         => 'object',
			)
		);

		// Pricing Data
		register_post_meta(
			'apnastay_property',
			'_apnastay_pricing',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'object',
			)
		);

		// Rules Data
		register_post_meta(
			'apnastay_property',
			'_apnastay_rules',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'object',
			)
		);

		// Completeness Score (0-100)
		register_post_meta(
			'apnastay_property',
			'_apnastay_completeness',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'integer',
				'default'      => 0,
			)
		);

		// Property Photos Array (Phase 5)
		register_post_meta(
			'apnastay_property',
			'_apnastay_photos',
			array(
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'id'           => array( 'type' => array( 'string', 'integer' ) ),
								'url'          => array( 'type' => 'string' ),
								'thumbnailUrl' => array( 'type' => 'string' ),
								'category'     => array( 'type' => 'string' ),
								'isCover'      => array( 'type' => 'boolean' ),
								'order'        => array( 'type' => 'integer' ),
								'fileName'     => array( 'type' => 'string' ),
								'fileSize'     => array( 'type' => 'number' ),
								'mimeType'     => array( 'type' => 'string' ),
							),
						),
					),
				),
				'single'       => true,
				'type'         => 'array',
			)
		);

		// Unit Beds Array
		register_post_meta(
			'apnastay_unit',
			'_apnastay_beds',
			array(
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'id'           => array( 'type' => 'string' ),
								'label'        => array( 'type' => 'string' ),
								'bedType'      => array( 'type' => 'string' ),
								'availability' => array( 'type' => 'string' ),
								'monthlyRent'  => array( 'type' => 'number' ),
								'deposit'      => array( 'type' => 'number' ),
							),
						),
					),
				),
				'single'       => true,
				'type'         => 'array',
			)
		);
	}
}
