<?php
/**
 * ApnaStay REST API Router & Initialization.
 *
 * @package ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_API Class.
 */
class ApnaStay_API {

	/**
	 * Singleton instance.
	 *
	 * @var ApnaStay_API|null
	 */
	private static $instance = null;

	/**
	 * API Namespace.
	 *
	 * @var string
	 */
	public static $namespace = 'apnastay/v1';

	/**
	 * Get singleton instance.
	 *
	 * @return ApnaStay_API
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
		$auth_controller = new ApnaStay_Auth_Controller();
		$auth_controller->register_routes();

		// Register User Controller.
		$user_controller = new ApnaStay_User_Controller();
		$user_controller->register_routes();

		// Register Permission Matrix Test Controller (Phase 24).
		$permission_controller = new ApnaStay_Permission_Controller();
		$permission_controller->register_routes();
	}
}
