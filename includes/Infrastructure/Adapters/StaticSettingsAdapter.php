<?php

declare(strict_types=1);
/**
 * Transitional adapter: exposes the static Settings class as an injectable
 * instance implementing SettingsProviderInterface.
 *
 * Remove this class once t192 adds instance methods directly to Settings
 * and registers it as a DI singleton — at that point, just update the
 * Plugin::configure() binding to point to Settings::class.
 *
 * @package GratisAiAgent\Infrastructure\Adapters
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Infrastructure\Adapters;

use GratisAiAgent\Contracts\SettingsProviderInterface;
use GratisAiAgent\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper that satisfies SettingsProviderInterface by delegating every
 * call to the existing static Settings methods.
 *
 * This bridge exists so code can depend on the interface (and receive a fake
 * in tests) while the underlying storage layer stays unchanged until t192
 * converts Settings to a proper DI singleton.
 */
class StaticSettingsAdapter implements SettingsProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function get( ?string $key = null ): mixed {
		return Settings::get( $key );
	}

	/**
	 * {@inheritdoc}
	 */
	public function update( array $data ): bool {
		return Settings::update( $data );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_defaults(): array {
		return Settings::get_defaults();
	}
}
