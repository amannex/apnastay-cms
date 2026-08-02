<?php
/**
 * OwnStay REST API Router & Initialization.
 *
 * @package OwnStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OwnStay_API Class.
 */
class OwnStay_API {

	/**
	 * Singleton instance.
	 *
	 * @var OwnStay_API|null
	 */
	private static $instance = null;

	/**
	 * API Namespace.
	 *
	 * @var string
	 */
	public static $namespace = 'ownstay/v1';

	/**
	 * Get singleton instance.
	 *
	 * @return OwnStay_API
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize API routes.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Register Auth Controller.
		$auth_controller = new OwnStay_Auth_Controller();
		$auth_controller->register_routes();

		// Register User Controller.
		$user_controller = new OwnStay_User_Controller();
		$user_controller->register_routes();

		// Register Property Controller with RBAC permission callbacks.
		$property_controller = new OwnStay_Property_Controller();
		$property_controller->register_routes();

		// Register Permission Matrix Test Controller (Phase 24).
		$permission_controller = new OwnStay_Permission_Controller();
		$permission_controller->register_routes();
	}
}
