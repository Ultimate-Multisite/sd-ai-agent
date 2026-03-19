<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package GratisAiAgent
 */

// Load standard Composer autoloader (not Jetpack) - required for PSR interfaces used by compat layer.
// Jetpack Autoloader requires WordPress functions, so we use the standard autoloader for tests.
$plugin_dir = dirname( __DIR__ );
require_once $plugin_dir . '/vendor/autoload.php';

/**
 * WP trunk PSR namespace compatibility shims.
 *
 * WP trunk's bundled php-ai-client scopes its PSR and Nyholm dependencies
 * under WordPress\AiClientDependencies\ (via a custom autoloader in
 * wp-includes/php-ai-client/autoload.php). The Composer-installed
 * wordpress/php-ai-client package uses the global Psr\ and Nyholm\ namespaces.
 *
 * When both are present, Composer's autoloader wins the race for two classes
 * and registers them with global type hints. WP trunk's adapter classes then
 * fail to implement/extend them because they use WordPress\AiClientDependencies\
 * type hints — PHP fatal on every WP trunk test run.
 *
 * Affected classes:
 *
 * 1. WordPress\AiClient\Providers\Http\Contracts\ClientWithOptionsInterface
 *    WP trunk's class-wp-ai-client-http-client.php implements this interface
 *    using WordPress\AiClientDependencies\Psr\ type hints.
 *
 * 2. WordPress\AiClient\Providers\Http\Abstracts\AbstractClientDiscoveryStrategy
 *    WP trunk's class-wp-ai-client-discovery-strategy.php extends this abstract
 *    class using WordPress\AiClientDependencies\Nyholm\ and
 *    WordPress\AiClientDependencies\Psr\ type hints.
 *
 * 3. Psr\SimpleCache\CacheInterface (global)
 *    WP trunk's WP_AI_Client_Cache implements the scoped version
 *    (WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface) but
 *    Composer's AiClient::setCache() expects the global Psr\SimpleCache\CacheInterface.
 *    Fix: use class_alias() to make the global name an alias for the scoped interface.
 *    This makes WP_AI_Client_Cache satisfy the global type hint because PHP's type
 *    system treats the two names as the same interface. Extending the global interface
 *    from the scoped one does NOT work — PHP instanceof requires explicit implementation.
 *
 * Fix: register a prepended autoloader that intercepts the affected classes and either
 * loads interface redefinition shims (strategy A) or registers a class_alias (strategy B).
 * Shims are only loaded when WP trunk's scoped PSR namespace is detectable
 * (interface_exists check), so WP 6.9 tests are unaffected.
 *
 * The prepend=true flag ensures this autoloader runs before Composer's,
 * so the shims win the race for the class/interface definitions.
 */
spl_autoload_register(
	static function ( string $class_name ) use ( $plugin_dir ): void {
		// Only activate shims when WP trunk's scoped PSR autoloader is present.
		// WP trunk registers its autoloader (which handles WordPress\AiClientDependencies\*)
		// in wp-includes/php-ai-client/autoload.php before adapter class files are loaded.
		// On WP 6.9 (no WP trunk), the scoped namespace does not exist and
		// interface_exists() returns false — fall through to Composer's autoloader.
		//
		// Shim strategy depends on the direction of the type mismatch:
		//
		// A. Interface redefinition (ClientWithOptionsInterface, AbstractClientDiscoveryStrategy):
		//    WP trunk's adapter class implements/extends the Composer-defined interface but
		//    uses scoped PSR type hints in its method signatures. Fix: redefine the interface
		//    using scoped type hints so WP trunk's class can implement it without a signature
		//    mismatch. Guard: check for the scoped RequestInterface (loaded by WP trunk's
		//    autoloader when the HTTP client classes are first used).
		//
		// B. Class alias (Psr\SimpleCache\CacheInterface):
		//    WP trunk's WP_AI_Client_Cache implements the scoped CacheInterface. Composer's
		//    AiClient::setCache() expects the global Psr\SimpleCache\CacheInterface. Extending
		//    the scoped interface from the global one does NOT help — PHP's instanceof check
		//    requires the class to explicitly implement the global interface. Fix: alias the
		//    global name to the scoped interface so they are the same type in PHP's type
		//    system. Guard: check for the scoped CacheInterface (already loaded by WP trunk's
		//    autoloader when WP_AI_Client_Cache is instantiated before setCache() is called).

		// Strategy A: interface redefinition shims.
		$shim_map = array(
			'WordPress\\AiClient\\Providers\\Http\\Contracts\\ClientWithOptionsInterface'      => 'wp-trunk-client-with-options-interface.php',
			'WordPress\\AiClient\\Providers\\Http\\Abstracts\\AbstractClientDiscoveryStrategy' => 'wp-trunk-abstract-client-discovery-strategy.php',
		);

		if ( isset( $shim_map[ $class_name ] ) ) {
			if ( ! interface_exists( 'WordPress\\AiClientDependencies\\Psr\\Http\\Message\\RequestInterface' ) ) {
				return;
			}
			require_once $plugin_dir . '/tests/stubs/' . $shim_map[ $class_name ];
			return;
		}

		// Strategy B: class_alias shim for Psr\SimpleCache\CacheInterface.
		// WP trunk's WP_AI_Client_Cache implements the scoped CacheInterface. By the time
		// AiClient::setCache() is called, WP trunk's autoloader has already loaded
		// WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface (it is a dependency
		// of WP_AI_Client_Cache). We alias the global name to the scoped interface so that
		// WP_AI_Client_Cache satisfies the global type hint without any class modification.
		if ( 'Psr\\SimpleCache\\CacheInterface' === $class_name ) {
			if ( ! interface_exists( 'WordPress\\AiClientDependencies\\Psr\\SimpleCache\\CacheInterface' ) ) {
				return;
			}
			class_alias(
				'WordPress\\AiClientDependencies\\Psr\\SimpleCache\\CacheInterface',
				'Psr\\SimpleCache\\CacheInterface'
			);
		}
	},
	true,  // throw on error
	true   // prepend — run before Composer's autoloader
);

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
