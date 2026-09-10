<?php
/**
 * PHPStan analysis bootstrap.
 *
 * Defines plugin constants that are normally created at runtime by the
 * main plugin file (users-dlx-plus.php). PHPStan analyzes the
 * codebase statically without executing anything, so it never sees the
 * `define()` calls there. Without these stubs, every reference to
 * `USERS_DLX_PLUS_DIR` and friends produces "Constant not found".
 *
 * This file is referenced from phpstan.neon's `bootstrapFiles:` list.
 * It is excluded from the wp.org deploy via .distignore. It is NOT
 * loaded at plugin runtime — only by PHPStan during analysis.
 *
 * @package UsersDlxPlus
 */

if ( ! defined( 'USERS_DLX_PLUS_VERSION' ) ) {
	define( 'USERS_DLX_PLUS_VERSION', '0.0.0-phpstan-stub' );
}
if ( ! defined( 'USERS_DLX_PLUS_DIR' ) ) {
	define( 'USERS_DLX_PLUS_DIR', __DIR__ . '/' );
}
if ( ! defined( 'USERS_DLX_PLUS_URL' ) ) {
	define( 'USERS_DLX_PLUS_URL', 'https://example.test/wp-content/plugins/users-dlx-plus/' );
}
if ( ! defined( 'USERS_DLX_PLUS_FILE' ) ) {
	define( 'USERS_DLX_PLUS_FILE', __DIR__ . '/users-dlx-plus.php' );
}

// Constantes que WordPress define en tiempo de ejecución y que el análisis
// estático no ve porque salen de wp-includes/default-constants.php.
if ( ! defined( 'COOKIEHASH' ) ) {
	define( 'COOKIEHASH', 'phpstan' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
