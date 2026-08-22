<?php
/**
 * Fired during plugin activation.
 *
 * @package ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay Activator Class.
 */
class ApnaStay_Activator {

	/**
	 * Plugin activation routine.
	 * Registers custom RBAC roles and default capabilities, and flushes rewrite rules.
	 */
	public static function activate() {
		require_once APNASTAY_CORE_PATH . 'includes/class-roles.php';
		
		// Register default ApnaStay RBAC roles.
		ApnaStay_Roles::register_roles();

		// Flush rewrite rules after custom routes or post types are registered.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation routine.
	 */
	public static function deactivate() {
		// Flush rewrite rules on deactivation.
		flush_rewrite_rules();
	}
}
