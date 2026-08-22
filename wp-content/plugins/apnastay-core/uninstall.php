<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package ApnaStay_Core
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-roles.php';

// Remove custom ApnaStay RBAC roles and administrative capabilities.
ApnaStay_Roles::remove_roles();
