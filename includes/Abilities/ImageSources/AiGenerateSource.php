<?php

declare(strict_types=1);
/**
 * AI Image Generation source using WordPress AI SDK.
 *
 * Uses the WordPress AI SDK (wp-ai-client) to support any configured
 * image generation provider - OpenAI DALL-E, Stability AI, or self-hosted.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Abilities\ImageSources;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Image Generation source.
 *
 * Uses the WordPress AI SDK for provider-agnostic image generation.
 *
 * @since 1.5.0
 */
class AiGenerateSource implements ImageSourceInterface {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'generate';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'AI Generate';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		// Check if AI client is configured by attempting a simple prompt.
		// The wp_ai_client_prompt function will return WP_Error if not configured.
		$test = wp_ai_client_prompt( 'test' );
		return ! is_wp_error( $test );
	}

	/**
	 * {@inheritdoc}
	 *
	 * For AI generation, search returns a single "synthetic" result
	 * that can be used to trigger generation.
	 */
	public function search( string $keyword, int $per_page = 10 ): array|\WP_Error {
		// AI generation doesn't search - it generates.
		// Return a synthetic hit that represents the generation intent.
		return [
			'hits'   => [
				[
					'id'      => 'generate:' . rawurlencode( $keyword ),
					'preview' => '', // No preview - generated on demand.
					'prompt'  => $keyword,
					'source'  => 'generate',
				],
			],
			'total'  => 1,
			'source' => 'generate',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_image( string $image_id ): array|\WP_Error {
		// Not applicable for generation.
		return new WP_Error( 'not_applicable', 'Use download() method for AI generation.' );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Generates an image using the WordPress AI SDK via wp_ai_client_prompt.
	 */
	public function download( string $prompt, int $width = 0, int $height = 0 ): string|\WP_Error {
		// Strip the generate: prefix if present.
		$prompt = str_starts_with( $prompt, 'generate:' )
			? substr( $prompt, 9 )
			: $prompt;

		if ( empty( $prompt ) ) {
			return new WP_Error( 'missing_prompt', 'Prompt is required for image generation.' );
		}

		// Add size hints to the prompt.
		$full_prompt = $prompt;
		if ( $width > 0 && $height > 0 ) {
			$full_prompt = sprintf(
				'%s. Create an image that is %d pixels wide by %d pixels tall.',
				$prompt,
				$width,
				$height
			);
		}

		try {
			$result = wp_ai_client_prompt( $full_prompt );

			if ( is_wp_error( $result ) ) {
				return new WP_Error(
					'generation_failed',
					$result->get_error_message()
				);
			}

			// Try to get image from result - check for different result types.
			$base64_image = '';

			// Check if result has image generation method.
			if ( method_exists( $result, 'generate_image' ) ) {
				$image_result = $result->generate_image( $full_prompt );
				if ( is_wp_error( $image_result ) ) {
					return new WP_Error(
						'generation_failed',
						$image_result->get_error_message()
					);
				}
				if ( method_exists( $image_result, 'to_base64' ) ) {
					$base64_image = $image_result->to_base64();
				}
			}

			// If no image from fluent API, try to get it from the result directly.
			// The result might be an array with image data or a direct string.
			if ( empty( $base64_image ) ) {
				if ( is_array( $result ) ) {
					$base64_image = $result['image'] ?? $result['b64_json'] ?? $result['base64'] ?? '';
				} elseif ( is_string( $result ) ) {
					$base64_image = $result;
				}
			}

			if ( empty( $base64_image ) ) {
				return new WP_Error( 'generation_failed', 'No image data returned.' );
			}

			// Write the base64 image to a temp file with strict mode.
			$image_data = base64_decode( $base64_image, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $image_data ) {
				return new WP_Error( 'generation_failed', 'Failed to decode base64 image.' );
			}

			$tmp_dir  = get_temp_dir();
			$tmp_file = $tmp_dir . 'gratis-ai-' . uniqid() . '.png';

			$written = file_put_contents( $tmp_file, $image_data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_put_contents,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === $written ) {
				return new WP_Error( 'generation_failed', 'Failed to write temp image file.' );
			}

			return $tmp_file;

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'generation_error',
				$e->getMessage()
			);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_cost_type(): string {
		return 'api';
	}
}
