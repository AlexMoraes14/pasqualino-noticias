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
define( 'DB_NAME', 'wp_noticias' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

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
define( 'AUTH_KEY',         'qQt5y?O#[nmUH|X,V2A;W~REW}B0HYjXbhcAl&QM5wuB~/ipf)Aj R>]K57>k8zo' );
define( 'SECURE_AUTH_KEY',  '^m?>S`LS,w;kb$riCjjXynx>P>QGX;--rZrr 2)-60+RkT$(`[#-qkOtstAAL3Ad' );
define( 'LOGGED_IN_KEY',    'DBL0}4#_xT(qD{)EFSlS3[% W}3K+P@dWv*?Hcm.*c*):%zY;#zY*:{F%(&b<:,c' );
define( 'NONCE_KEY',        '@GG-m7WmsYvVaiKT`<z@hy%(V^.=t0K,I-1IX4>z{kZ0qvVQ&iZAO@#t@gBcz^(j' );
define( 'AUTH_SALT',        'frU/!!d@FN$e1G)v/~nlc*G6&l|)%D<?^?$*^]9@tTg4[hHQ1R;gLo~%/.#/,NW}' );
define( 'SECURE_AUTH_SALT', '?>t#u1i*xGR1[HqJo8Y[}f[,$gJ3?({Kyahj41@T0hnGQ)+EL,vBfqObG p``z8d' );
define( 'LOGGED_IN_SALT',   '0*V{6h+ z!2D7@!@z9w9k2[T-6dTCihe5 XhZpUzul]*](|^Tg@-VOjbcfB.4Vwl' );
define( 'NONCE_SALT',       'hJY@5Q}-9phV}tK^zkYzDC3hB!NI]7J3/IGI,?5W}>1MPwkUM74NbMB`1Z1)C*j8' );

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




define('WP_ENVIRONMENT_TYPE', 'development');




/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
