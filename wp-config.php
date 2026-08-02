<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'ownstay_db' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1:8889' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         ')nX=4~(~3w.g Z^w]dG)u.yful~P0Dc C(s.il7C0q<W-!:%;pa1i_#4/XAwjQ2Z' );
define( 'SECURE_AUTH_KEY',  ',ud$B?4mA3Z3V{mV:w5#Wl&*x`NDLY@WSx,).T~;YYYh6%7Q(JF5%fn:]3i<jCPX' );
define( 'LOGGED_IN_KEY',    'N?6QJQ*#XQquz&9T.|2;Z6mB)a@$.<j~i;S||`x~l~sgyev GBmZ4.vY38X<gjB!' );
define( 'NONCE_KEY',        '>]Ekhc?>+f3S2?@Z^4k@J7$4T-Pc67Du|<KYLD=L!^4e]|[0E+NmKSgG1d$3B$.)' );
define( 'AUTH_SALT',        'ZeW4cDmC}hBMx-_z/?*zt/H(qa*mZ$gtejiwhWS/N)i2|LY0/l/vMSw{FNL1hN8u' );
define( 'SECURE_AUTH_SALT', 'zaE^Rj;sA;ha2C!*b#-`X[y[o>*R=HyUC%s,&*2c5`^a<2>ivWF%lGeBz@zOW]}F' );
define( 'LOGGED_IN_SALT',   'Dq|z}J H@[l@Gf,siLQE*Vw0VA}&{Vl;yxg$(fM>y0 AjNT7:b-#(:{=]K#6UAOf' );
define( 'NONCE_SALT',       'dn{XKp75suYjl{*l4$B[LCqeh9*.H](=w>/trcpE;LWSY0aP/0U+(wH~_@$hF!]V' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
