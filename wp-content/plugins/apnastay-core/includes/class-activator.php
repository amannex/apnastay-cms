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
	 * Registers custom RBAC roles and default capabilities, migrates legacy users, and flushes rewrite rules.
	 */
	public static function activate() {
		require_once APNASTAY_CORE_PATH . 'includes/class-roles.php';
		
		// Register default ApnaStay RBAC roles.
		ApnaStay_Roles::register_roles();

		// Migrate legacy users and ensure role consistency.
		ApnaStay_Roles::migrate_legacy_users();

		// Setup database triggers for cascading user data cleanup.
		self::setup_database_triggers();

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

	/**
	 * Setup MySQL cascading triggers so that raw database deletions (e.g. via phpMyAdmin)
	 * automatically delete all user metadata, properties, and postmeta across the database.
	 */
	public static function setup_database_triggers() {
		global $wpdb;

		$users_table    = $wpdb->users;
		$usermeta_table = $wpdb->usermeta;
		$posts_table    = $wpdb->posts;
		$postmeta_table = $wpdb->postmeta;

		$wpdb->query( "DROP TRIGGER IF EXISTS apnastay_cascade_user_delete" );

		$trigger_sql = "
CREATE TRIGGER apnastay_cascade_user_delete
AFTER DELETE ON {$users_table}
FOR EACH ROW
BEGIN
    -- 1. Automatically delete all user metadata
    DELETE FROM {$usermeta_table} WHERE user_id = OLD.ID;

    -- 2. Automatically delete all postmeta for properties created by this user
    DELETE pm FROM {$postmeta_table} pm
    INNER JOIN {$posts_table} p ON pm.post_id = p.ID
    WHERE p.post_author = OLD.ID AND p.post_type = 'apnastay_property';

    -- 3. Delete postmeta where _apnastay_owner_id is this user
    DELETE FROM {$postmeta_table}
    WHERE meta_key = '_apnastay_owner_id' AND meta_value = CAST(OLD.ID AS CHAR);

    -- 4. Automatically delete all property posts created by this user
    DELETE FROM {$posts_table} WHERE post_author = OLD.ID AND post_type = 'apnastay_property';

    -- 5. Reassign non-property posts to admin
    UPDATE {$posts_table} SET post_author = 1 WHERE post_author = OLD.ID AND post_type != 'apnastay_property';
END
";
		$wpdb->query( $trigger_sql );
	}

}
