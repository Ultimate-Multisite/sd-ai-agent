<?php
/**
 * PHPStan stubs for WooCommerce internal Abilities classes.
 *
 * These classes exist at runtime in WooCommerce 9.x+ but are not included in
 * the woocommerce-stubs package. Stubs are required so that PHPStan can narrow
 * the class-string type after class_exists() checks.
 *
 * @package SdAiAgent
 */

namespace Automattic\WooCommerce\Internal\Abilities;

class AbilitiesRestBridge {
	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_configurations(): array {}
}

namespace Automattic\WooCommerce\Internal\Abilities\REST;

class RestAbilityFactory {
	/**
	 * @param array<string, mixed> $config
	 */
	public static function register_controller_abilities( array $config ): void {}
}
