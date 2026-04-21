<?php

declare(strict_types=1);
/**
 * Image source factory for unified image retrieval.
 *
 * Provides a single entry point for multiple image sources:
 * - openverse: Free CC0 images from WordPress.org (no key required)
 * - pixabay: Free images (API key required, free commercial)
 * - generate: AI-generated via DALL-E (API required, paid)
 *
 * The agent chooses the best source based on availability and cost preferences.
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
 * Image source factory and unified ability.
 *
 * @since 1.5.0
 */
class ImageSourceFactory {

	/**
	 * Registered sources.
	 *
	 * @var array<string, ImageSourceInterface>
	 */
	private static array $sources = [];

	/**
	 * Initialize and register all sources.
	 */
	public static function init(): void {
		// Register sources in priority order.
		self::$sources = [
			'openverse' => new OpenverseImageSource(),
			'pixabay'   => new PixabayImageSource(),
			'generate'  => new AiGenerateSource(),
		];
	}

	/**
	 * Get a source by ID.
	 *
	 * @param string $source_id Source ID.
	 * @return ImageSourceInterface|null Source or null.
	 */
	public static function get( string $source_id ): ?ImageSourceInterface {
		if ( empty( self::$sources ) ) {
			self::init();
		}

		return self::$sources[ $source_id ] ?? null;
	}

	/**
	 * Get all available sources.
	 *
	 * @return array<string, ImageSourceInterface> Available sources.
	 */
	public static function get_available(): array {
		if ( empty( self::$sources ) ) {
			self::init();
		}

		return array_filter(
			self::$sources,
			static fn( ImageSourceInterface $source ): bool => $source->is_available()
		);
	}

	/**
	 * Get source info for agent selection.
	 *
	 * @return array Source info for the agent.
	 */
	public static function get_source_info(): array {
		$sources = self::get_available();

		return array_map(
			static function ( ImageSourceInterface $source ): array {
				return [
					'id'        => $source->get_id(),
					'name'      => $source->get_name(),
					'cost'      => $source->get_cost_type(),
					'available' => $source->is_available(),
				];
			},
			$sources
		);
	}

	/**
	 * Smart source selection for a keyword.
	 *
	 * Chooses the best available source based on preference hierarchy:
	 * 1. User explicitly requested 'generate' → use AI generation
	 * 2. User has paid API config → prefer free sources first, generate as fallback
	 * 3. Free sources only
	 *
	 * @param string $preferred Preferred source ID (optional).
	 * @return ImageSourceInterface|\WP_Error Selected source or error if explicitly requested source is unavailable.
	 */
	public static function select_source( string $preferred = '' ): ImageSourceInterface|\WP_Error {
		// If user explicitly requested a source, use it if available.
		if ( ! empty( $preferred ) ) {
			$source = self::get( $preferred );
			if ( ! $source ) {
				return new WP_Error(
					'unknown_image_source',
					sprintf( 'Unknown image source: %s.', $preferred )
				);
			}
			if ( ! $source->is_available() ) {
				return new WP_Error(
					'image_source_unavailable',
					sprintf( 'Image source "%s" is not available.', $preferred )
				);
			}
			return $source;
		}

		// Use priority: openverse (free) → pixabay (free) → generate (paid).
		$available = self::get_available();

		// Prefer free sources first.
		foreach ( $available as $source ) {
			if ( 'free' === $source->get_cost_type() ) {
				return $source;
			}
		}

		// Fall back to AI generation if available.
		foreach ( $available as $source ) {
			if ( 'api' === $source->get_cost_type() ) {
				return $source;
			}
		}

		// Fall back to first available.
		$first = array_values( $available );
		return $first[0] ?? self::$sources['openverse'];
	}

	/**
	 * Import an image from any source.
	 *
	 * @param string $keyword     Search keyword or generation prompt.
	 * @param string $source_id   Source ID (auto-selected if empty).
	 * @param int    $width     Desired width (0 for original).
	 * @param int    $height    Desired height (0 for original).
	 * @param array  $options   Additional options.
	 * @return array{\attachment_id: int, url: string, alt: string, title: string, source: string}|\WP_Error
	 */
	public static function import_image(
		string $keyword,
		string $source_id = '',
		int $width = 1200,
		int $height = 800,
		array $options = []
	): array|\WP_Error {

		// Auto-select source if not specified.
		$source = self::select_source( $source_id );

		// Handle error from select_source (e.g., explicitly requested source unavailable).
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		// For AI generation, use the download method directly.
		if ( 'generate' === $source->get_id() ) {
			$tmp_file = $source->download( $keyword, $width, $height );

			if ( is_wp_error( $tmp_file ) ) {
				return $tmp_file;
			}

			return self::handle_sideload( $tmp_file, $keyword, $options );
		}

		// For search-based sources, search first then download.
		$search_result = $source->search( $keyword, 1 );

		if ( is_wp_error( $search_result ) ) {
			return $search_result;
		}

		$hits = $search_result['hits'] ?? [];

		if ( empty( $hits ) ) {
			// No results - try AI generation as fallback.
			$generate = self::get( 'generate' );
			if ( $generate && $generate->is_available() ) {
				return self::import_image( $keyword, 'generate', $width, $height, $options );
			}

			return new WP_Error(
				'no_images_found',
				sprintf(
					'No images found for "%s" from %s.',
					$keyword,
					$source->get_name()
				)
			);
		}

		// Get the first hit.
		$hit      = $hits[0];
		$image_id = $hit['id'] ?? '';

		// Download the image.
		$tmp_file = $source->download( $image_id, $width, $height );

		if ( is_wp_error( $tmp_file ) ) {
			// Try next hit or fallback.
			return new WP_Error(
				'download_failed',
				'Failed to download: ' . $tmp_file->get_error_message()
			);
		}

		return self::handle_sideload( $tmp_file, $keyword, $options, $hit );
	}

	/**
	 * Handle WordPress sideload of a temp file.
	 *
	 * @param string $tmp_file Temp file path.
	 * @param string $keyword Original keyword.
	 * @param array  $options Options (site_url, post_id).
	 * @param array  $hit     Original hit data.
	 * @return array|\WP_Error Result array or error.
	 */
	private static function handle_sideload(
		string $tmp_file,
		string $keyword,
		array $options = [],
		array $hit = []
	): array|\WP_Error {

		$site_url = $options['site_url'] ?? '';
		$post_id  = $options['post_id'] ?? 0;

		// Switch to subsite if requested.
		$switched = false;
		if ( ! empty( $site_url ) && is_multisite() ) {
			$blog_id = get_blog_id_from_url(
				wp_parse_url( $site_url, PHP_URL_HOST ),
				wp_parse_url( $site_url, PHP_URL_PATH ) ?: '/'
			);

			// Reject unknown sites.
			if ( ! $blog_id ) {
				return new WP_Error(
					'unknown_site',
					sprintf( 'Could not find a site matching URL: %s.', $site_url )
				);
			}

			if ( (int) $blog_id !== get_current_blog_id() ) {
				switch_to_blog( $blog_id );
				$switched = true;
			}
		}

		// Detect real file extension from the temp file.
		$extension = 'jpg';
		if ( file_exists( $tmp_file ) ) {
			$finfo         = new \finfo( FILEINFO_MIME_TYPE );
			$mime_type     = $finfo->file( $tmp_file );
			$extension_map = [
				'image/jpeg' => 'jpg',
				'image/png'  => 'png',
				'image/gif'  => 'gif',
				'image/webp' => 'webp',
			];
			$extension     = $extension_map[ $mime_type ] ?? 'jpg';
		}

		// Build filename.
		$safe_keyword = sanitize_file_name( $keyword );
		$filename     = $safe_keyword . '-' . time() . '.' . $extension;

		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		];

		$title = ucwords( str_replace( [ '-', '_' ], ' ', $keyword ) );

		// Require media functions.
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = media_handle_sideload( $file_array, $post_id, $title );

		if ( $switched ) {
			restore_current_blog();
		}

		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $tmp_file ) ) {
				unlink( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}

			return $attachment_id;
		}

		// Set alt text from keyword.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );

		$attachment_url = wp_get_attachment_url( $attachment_id );

		return [
			'attachment_id' => $attachment_id,
			'url'           => $attachment_url,
			'alt'           => $title,
			'title'         => $title,
			'source'        => $hit['source'] ?? 'unknown',
			'tip'           => 'Use attachment_id as featured_image_id for create-post.',
		];
	}
}

// Initialize is handled by DI container or lazy initialization.
// The get() and get_available() methods call init() automatically if needed.
