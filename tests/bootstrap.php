<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Loads WordPress stubs so plugin code can be exercised without booting
 * WordPress. Nothing here touches the database or the running dev stack.
 */

// Composer autoload (PHPUnit, Brain Monkey, Mockery)
require_once __DIR__ . '/../vendor/autoload.php';

// Define WordPress constants that plugins expect
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../');
}

// Constantes de tiempo de WordPress. Las usa cualquier código que calcule un
// vencimiento, y no dependen de que WordPress esté cargado.
foreach ([
	'MINUTE_IN_SECONDS' => 60,
	'HOUR_IN_SECONDS'   => 3600,
	'DAY_IN_SECONDS'    => 86400,
	'WEEK_IN_SECONDS'   => 604800,
] as $cst_const => $cst_valor) {
	if (!defined($cst_const)) {
		define($cst_const, $cst_valor);
	}
}

if (!defined('UPFW_DIR')) {
	define('UPFW_DIR', __DIR__ . '/../');
}

if (!defined('UPFW_URL')) {
	define('UPFW_URL', 'https://example.test/wp-content/plugins/users-plus-for-wordpress/');
}

if (!defined('UPFW_VERSION')) {
	define('UPFW_VERSION', '0.0.0-test');
}

// Load WordPress function stubs
require_once __DIR__ . '/stubs/wordpress-stubs.php';
