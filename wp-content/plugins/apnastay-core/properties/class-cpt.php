<?php
/**
 * ApnaStay Custom Post Types & Taxonomies.
 *
 * @package ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_CPT Class.
 */
class ApnaStay_CPT {

	/**
	 * Initialize custom post types and taxonomies.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
	}

	/**
	 * Register Custom Post Types: apnastay_property & apnastay_unit.
	 */
	public static function register_post_types() {
		// 1. apnastay_property
		register_post_type(
			'apnastay_property',
			array(
				'labels'          => array(
					'name'               => __( 'Properties', 'apnastay-core' ),
					'singular_name'      => __( 'Property', 'apnastay-core' ),
					'add_new'            => __( 'Add New Property', 'apnastay-core' ),
					'add_new_item'       => __( 'Add New Property', 'apnastay-core' ),
					'edit_item'          => __( 'Edit Property', 'apnastay-core' ),
					'new_item'           => __( 'New Property', 'apnastay-core' ),
					'view_item'          => __( 'View Property', 'apnastay-core' ),
					'search_items'       => __( 'Search Properties', 'apnastay-core' ),
					'not_found'          => __( 'No properties found', 'apnastay-core' ),
					'not_found_in_trash' => __( 'No properties found in Trash', 'apnastay-core' ),
				),
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-building',
				'supports'        => array( 'title', 'editor', 'author', 'thumbnail', 'custom-fields' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'has_archive'     => 'properties',
				'rewrite'         => array(
					'slug'       => 'properties',
					'with_front' => false,
				),
			)
		);

		// 2. apnastay_unit (Relational Unit/Room entity)
		register_post_type(
			'apnastay_unit',
			array(
				'labels'          => array(
					'name'          => __( 'Units & Rooms', 'apnastay-core' ),
					'singular_name' => __( 'Unit / Room', 'apnastay-core' ),
					'add_new_item'  => __( 'Add New Unit', 'apnastay-core' ),
					'edit_item'     => __( 'Edit Unit', 'apnastay-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => true,
				'show_in_menu'    => 'edit.php?post_type=apnastay_property',
				'supports'        => array( 'title', 'author', 'custom-fields' ),
				'hierarchical'    => true,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Register Custom Taxonomies.
	 */
	public static function register_taxonomies() {
		// Category Taxonomy
		register_taxonomy(
			'property_category',
			array( 'apnastay_property' ),
			array(
				'hierarchical' => false,
				'label'        => __( 'Property Categories', 'apnastay-core' ),
				'show_in_rest' => true,
				'show_ui'      => true,
				'rewrite'      => array( 'slug' => 'property-category' ),
			)
		);

		// Amenity Taxonomy
		register_taxonomy(
			'property_amenity',
			array( 'apnastay_property' ),
			array(
				'hierarchical' => false,
				'label'        => __( 'Amenities', 'apnastay-core' ),
				'show_in_rest' => true,
				'show_ui'      => true,
				'rewrite'      => array( 'slug' => 'property-amenity' ),
			)
		);
	}
}
