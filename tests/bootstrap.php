<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

// Load standard Composer autoloader (not Jetpack).
// Jetpack Autoloader requires WordPress functions, so we use the standard autoloader for tests.
$plugin_dir = dirname( __DIR__ );
require_once $plugin_dir . '/vendor/autoload.php';

/*
 * Force XWP_Context to CTX_REST so the x-wp/di container loads REST handlers.
 *
 * Problem: the WP test bootstrap defines WP_ADMIN=true before loading
 * wp-settings.php. XWP_Context::get() uses a match(true) that checks
 * admin() BEFORE rest(), so the context resolves to Admin (2) — silently
 * skipping every CTX_REST (16) handler and producing 404 on all REST route
 * tests. Setting $_SERVER['REQUEST_URI'] to /wp-json/... doesn't help because
 * the admin() branch short-circuits before rest() is evaluated.
 *
 * Fix: pre-set the private static XWP_Context::$current via reflection BEFORE
 * WordPress boots. The ??= assignment in get() preserves our value, so the
 * match expression is never reached. This runs at file-scope before the WP
 * test bootstrap is even loaded — no hook ordering issues.
 */
( static function (): void {
	$refl = new ReflectionProperty( XWP_Context::class, 'current' );
	$refl->setValue( null, XWP_Context::REST );

	// Debug: verify the override sticks.
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug.
	error_log( 'BOOTSTRAP: XWP_Context pre-set to ' . XWP_Context::get() . ' (expected ' . XWP_Context::REST . ')' );
} )();

$_tests_dir = getenv('WP_TESTS_DIR');
if ( ! $_tests_dir ) {
	// wp-env places the test suite at /wordpress-phpunit.
	if ( file_exists( '/wordpress-phpunit/includes/functions.php' ) ) {
		$_tests_dir = '/wordpress-phpunit';
	} else {
		$_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
	}
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
// Auto-detect from Composer vendor directory if not set via env var.
$_phpunit_polyfills_path = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if ( false === $_phpunit_polyfills_path ) {
	$_phpunit_polyfills_path = $plugin_dir . '/vendor/yoast/phpunit-polyfills';
}
if ( is_dir( $_phpunit_polyfills_path ) ) {
	define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path);
}

if ( ! file_exists("{$_tests_dir}/includes/functions.php") ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit(1);
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname(__DIR__) . '/gratis-ai-agent.php';

	// Install database tables (normally done on activation).
	// Database::install() includes KnowledgeDatabase schema.
	GratisAiAgent\Core\Database::install();
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// Debug: verify context survived WP bootstrap and check DI handler state.
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Temporary debug.
error_log( 'POST-BOOT: XWP_Context::get() = ' . XWP_Context::get() . ', validate(REST) = ' . ( XWP_Context::validate( XWP_Context::REST ) ? 'true' : 'false' ) );

// Check if the DI container and handlers are set up.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
if ( function_exists( 'xwp_has' ) ) {
	error_log( 'POST-BOOT: xwp_has(gratis-ai-agent) = ' . ( xwp_has( 'gratis-ai-agent' ) ? 'true' : 'false' ) );

	if ( xwp_has( 'gratis-ai-agent' ) ) {
		$invoker = \XWP\DI\Invoker::instance();
		$handlers = $invoker->all_handlers();
		error_log( 'POST-BOOT: Registered handlers = ' . count( $handlers ) );
		foreach ( $handlers as $classname => $handler ) {
			$hookable = $handler->is_hookable() ? 'hookable' : 'NOT hookable';
			$loaded   = $handler->loaded ? 'loaded' : 'NOT loaded';
			error_log( "  HANDLER: {$classname} ({$hookable}, {$loaded})" );
		}

		// Fire rest_api_init and check routes.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
		$routes = $wp_rest_server->get_routes();
		$our_routes = array_filter(
			array_keys( $routes ),
			static fn( $r ) => str_starts_with( $r, '/gratis-ai-agent/' )
		);
		error_log( 'POST-BOOT: REST routes = ' . count( $our_routes ) . ' (' . implode( ', ', array_slice( $our_routes, 0, 5 ) ) . '...)' );
		$wp_rest_server = null;
	}
}
// phpcs:enable
