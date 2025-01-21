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
define('DB_NAME', 'qobolak_ai_chatbot');

/** Database username */
define('DB_USER', 'root');

/** Database password */
define('DB_PASSWORD', '123456789');

/** Database hostname */
define('DB_HOST', 'localhost');

/** Database charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The database collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

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
define('AUTH_KEY', ',l.jNy%8t I#f3ULsnUyD>gX2aKlIW/MDC[]w`)esR&GKd-HKC,a;,yLGu=nspt&');
define('SECURE_AUTH_KEY', 'BUu;HF#e}>2NI8Mp7pu,aUpb~-mHStEa6kKsPlI$J.:01i,U)J32[0~k&S0YZU|E');
define('LOGGED_IN_KEY', '~h^3tsHg8ux)]Q^n^ixu[GMIX6?<~bLR*=a:EZ(c8<Llm}rIpv0iV[L1tj3R!#hy');
define('NONCE_KEY', ']Rf}!,9RilMYHAvJ|XQAf>;mUCfq>b<b91ERtPk&gQI3#T!otNEwv}F5>g`X:y$9');
define('AUTH_SALT', 'vCb1]BtEjmvfY9$XWGGDGjteP1i&U?S.FY=-+.v!?&MM2C_*I9#?R`TwC9pFs<Iv');
define('SECURE_AUTH_SALT', 'Ibkl{+k.roU$eB:]~!Xg&]Tls*<,@3Y}b5]4]hSK4xt31XvlCvA~T:C.[bD7FE2J');
define('LOGGED_IN_SALT', ',x.kDw8pwtbu9E;_sV]Bt)JiB~`& 5=I<=irc>ZV;}Yc53o6SQzxUHmN&$*(a[ek');
define('NONCE_SALT', 'I5(HCI8myG5[Qq4M< n949dw&Jr_DVh}jyFDzP_>jX>ognp?M4zwBpZb6d&R](s1');

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
$table_prefix = 'qo_';

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
define('WP_DEBUG', false);

/* Add any custom values between this line and the "stop editing" line. */

define('FS_METHOD', 'direct');

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
