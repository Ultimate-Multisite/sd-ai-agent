<?php

declare(strict_types=1);
/**
 * Functional benchmark suite definitions.
 *
 * Each question drives the full AI agent loop against a live WordPress
 * environment.  Scoring is based entirely on what the agent actually did —
 * did it create a working plugin, register the right endpoint, create the
 * right table — not on keyword matching against text output.
 *
 * Question shape:
 *   id          — unique identifier (e.g. fn-001)
 *   category    — grouping label for reporting
 *   prompt      — the user message sent verbatim to the agent
 *   max_turns   — agent iteration cap for this question
 *   assertions  — list of AssertionEngine checks run after the agent finishes
 *
 * @package SdAiAgent\Benchmark
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Benchmark;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BenchmarkSuite {

	/**
	 * List all available suites.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_suites(): array {
		return array(
			array(
				'slug'           => 'content-v1',
				'name'           => 'Agent Content v1',
				'description'    => 'Tests the agent\'s ability to create real WordPress content — pages, posts, WooCommerce products, and content analysis — verified by querying live WordPress state.',
				'question_count' => count( self::get_content_questions() ),
			),
			array(
				'slug'           => 'functional-v1',
				'name'           => 'Functional Agent v1',
				'description'    => 'End-to-end agent loop tests. The agent must produce working WordPress plugins, endpoints, and features — verified against live WordPress state, not keyword matching.',
				'question_count' => count( self::get_functional_questions() ),
			),
		);
	}

	/**
	 * Get a suite by slug with its full question list.
	 *
	 * @param string $slug Suite slug.
	 * @return array<string, mixed>|null
	 */
	public static function get_suite( string $slug ): ?array {
		switch ( $slug ) {
			case 'content-v1':
				return array(
					'slug'        => 'content-v1',
					'name'        => 'Agent Content v1',
					'description' => 'Tests the agent\'s ability to create real WordPress content verified against live WordPress state.',
					'questions'   => self::get_content_questions(),
				);

			case 'functional-v1':
				return array(
					'slug'        => 'functional-v1',
					'name'        => 'Functional Agent v1',
					'description' => 'End-to-end agent loop tests verified against live WordPress state.',
					'questions'   => self::get_functional_questions(),
				);

			default:
				return null;
		}
	}

	/**
	 * Get questions for a suite by slug.
	 *
	 * @param string $slug Suite slug.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_questions( string $slug ): array {
		$suite = self::get_suite( $slug );
		return $suite ? (array) $suite['questions'] : array();
	}

	/**
	 * Content v1 questions.
	 *
	 * Each question asks the agent to create real WordPress content using its
	 * built-in tools (create-post, woo-create-product, content-analyze, etc.).
	 * Assertions query live WordPress state — post titles, WooCommerce products,
	 * published pages — rather than inspecting the agent's text output.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_content_questions(): array {
		return array(

			// ── Pages ─────────────────────────────────────────────────────────

			array(
				'id'         => 'ct-001',
				'category'   => 'pages',
				'max_turns'  => 12,
				'prompt'     => 'Create a landing page for our new product "CloudSync Pro" — a cloud file synchronization tool for teams. Include a hero section with headline and subheadline, a features section with 3 key features (real-time sync, end-to-end encryption, team collaboration), a pricing section with 3 tiers (Free, Pro at $9/mo, Enterprise at $29/mo), and a call-to-action. Publish it immediately.',
				'assertions' => array(
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'post list --post_type=page --post_status=publish --fields=post_title --format=csv --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'CloudSync Pro',
						'expected_exit_code'      => 0,
						'description'             => 'Page "CloudSync Pro" is published',
					),
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'post list --post_type=page --post_status=publish --fields=post_title,post_content --format=json --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'Free|Pro|\$9|pricing|feature',
						'expected_exit_code'      => 0,
						'description'             => 'Page content includes pricing and features sections',
					),
				),
			),

			array(
				'id'         => 'ct-002',
				'category'   => 'pages',
				'max_turns'  => 12,
				'prompt'     => 'Create an About Us page for "Acme Digital Solutions", a web development agency founded in 2020. We have 15 team members, are based in Austin TX, and specialize in WordPress and React development. Include our mission statement, a team overview section, and a contact information section.',
				'assertions' => array(
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'post list --post_type=page --fields=post_title,post_status --format=csv --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'Acme|About',
						'expected_exit_code'      => 0,
						'description'             => 'About Us page exists',
					),
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'post list --post_type=page --fields=post_title,post_content --format=json --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'Austin|2020|mission|team|contact',
						'expected_exit_code'      => 0,
						'description'             => 'Page content includes company details, mission, team and contact sections',
					),
				),
			),

			// ── Blog Posts ────────────────────────────────────────────────────

			array(
				'id'         => 'ct-003',
				'category'   => 'posts',
				'max_turns'  => 12,
				'prompt'     => 'Write an SEO-optimized blog post about "10 Best Practices for Remote Team Management in 2026". Optimize it for the keyword "remote team management". Include proper headings, a meta description in the excerpt field, and assign it to the "Management" category with tags "remote work", "team management", and "productivity". Save it as a draft.',
				'assertions' => array(
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'post list --post_type=post --post_status=draft --fields=post_title --format=csv --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'Remote Team|remote team|Best Practices',
						'expected_exit_code'      => 0,
						'description'             => 'Blog post draft exists with correct title',
					),
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'post list --post_type=post --post_status=draft --fields=post_title,post_excerpt --format=json --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'remote.team|management',
						'expected_exit_code'      => 0,
						'description'             => 'Post has an excerpt (meta description)',
					),
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'term list post_tag --fields=name --format=csv --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'remote work|team management|productivity',
						'expected_exit_code'      => 0,
						'description'             => 'Tags "remote work", "team management", "productivity" exist',
					),
				),
			),

			array(
				'id'         => 'ct-004',
				'category'   => 'posts',
				'max_turns'  => 15,
				'prompt'     => 'Create a blog post about "The Future of AI in Healthcare" and find a relevant stock image of medical technology to use as the featured image. The post should cover three topics: AI in diagnostics, AI in drug discovery, and AI in patient care. Save it as a draft.',
				'assertions' => array(
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'post list --post_type=post --post_status=draft --fields=post_title --format=csv --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'AI.*Healthcare|Healthcare.*AI|Future of AI',
						'expected_exit_code'      => 0,
						'description'             => 'Blog post draft about AI in Healthcare exists',
					),
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'post list --post_type=post --post_status=draft --fields=post_title,post_content --format=json --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'diagnostic|drug.discovery|patient.care',
						'expected_exit_code'      => 0,
						'description'             => 'Post content covers all three required topics',
					),
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'post list --post_type=post --post_status=draft --fields=post_title,_thumbnail_id --format=json --url=wp-multisite-waas.test',
						'expected_output_pattern' => '_thumbnail_id|thumbnail',
						'expected_exit_code'      => 0,
						'description'             => 'Post has a featured image set',
					),
				),
			),

			// ── WooCommerce ───────────────────────────────────────────────────

			array(
				'id'         => 'ct-005',
				'category'   => 'woocommerce',
				'max_turns'  => 12,
				'prompt'     => 'Create a new WooCommerce product: "Ergonomic Standing Desk" priced at $599.99 (on sale for $449.99). SKU: DESK-ERG-001. Stock: 50 units. Category: "Office Furniture". Write a compelling product description highlighting the adjustable height range (28-48 inches), bamboo desktop surface, integrated cable management, and 10-year warranty. Set it as published.',
				'assertions' => array(
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'wc product list --field=name --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'Ergonomic Standing Desk',
						'expected_exit_code'      => 0,
						'description'             => 'WooCommerce product "Ergonomic Standing Desk" exists',
					),
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'wc product list --fields=name,sku,regular_price,sale_price,stock_quantity --format=json --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'DESK-ERG-001',
						'expected_exit_code'      => 0,
						'description'             => 'Product has correct SKU DESK-ERG-001',
					),
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'wc product list --fields=name,regular_price,sale_price --format=json --url=wp-multisite-waas.test',
						'expected_output_pattern' => '599|449',
						'expected_exit_code'      => 0,
						'description'             => 'Product has correct regular ($599.99) and sale ($449.99) prices',
					),
				),
			),

			// ── Content Analysis ──────────────────────────────────────────────

			array(
				'id'         => 'ct-006',
				'category'   => 'analysis',
				'max_turns'  => 10,
				'prompt'     => 'Analyze our content strategy. Check what posts we have published recently, look at the content distribution across categories, and identify any content gaps. Give me a structured summary with three sections: Recent Publishing Activity, Category Distribution, and Content Gaps & Recommendations.',
				'assertions' => array(
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'post list --post_status=publish --fields=post_title,post_date --format=csv --url=wp-multisite-waas.test',
						'expected_output_pattern' => '.',
						'expected_exit_code'      => 0,
						'description'             => 'Agent successfully queried the WordPress post list',
					),
				),
				// This question scores on whether the agent actually used content-analysis
				// tools and produced a structured response — the single assertion just
				// verifies WordPress is reachable; the log file captures the full agent output.
			),

			// ── Plugin Management ─────────────────────────────────────────────

			array(
				'id'         => 'ct-007',
				'category'   => 'plugins',
				'max_turns'  => 12,
				'prompt'     => 'Check what plugins are currently installed on this site, then install the "contact-form-7" plugin from WordPress.org and activate it.',
				'assertions' => array(
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'plugin list --field=name --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'contact-form-7',
						'expected_exit_code'      => 0,
						'description'             => 'contact-form-7 plugin appears in the plugin list',
					),
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'plugin list --fields=name,status --format=csv --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'contact-form-7,active',
						'expected_exit_code'      => 0,
						'description'             => 'contact-form-7 plugin is active',
					),
				),
			),

		);
	}

	/**
	 * Functional v1 questions.
	 *
	 * Each question is sent to the real agent loop with all tools available.
	 * The agent is expected to build, write, and activate working code.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_functional_questions(): array {
		return array(

			// ── REST API ──────────────────────────────────────────────────────

			array(
				'id'         => 'fn-001',
				'category'   => 'rest_api',
				'max_turns'  => 20,
				'prompt'     => 'Build me a WordPress plugin called "event-manager" that adds a REST API endpoint at POST /wp-json/event-manager/v1/events. The endpoint should accept "title" (required, string) and "date" (required, string) fields, check that the current user has the publish_posts capability, sanitize both fields, create a new WordPress post of type "event" with the provided title, store the date in post meta as "_event_date", and return JSON with the new post ID. Include proper error handling with WP_Error for missing fields and unauthorized access. Activate the plugin when done.',
				'assertions' => array(
					array(
						'type'        => 'plugin_no_php_errors',
						'description' => 'Plugin PHP files have no syntax errors',
					),
					array(
						'type'        => 'plugin_activates',
						'description' => 'Plugin activates without fatal errors',
					),
					array(
						'type'        => 'post_type_registered',
						'post_type'   => 'event',
						'description' => 'Custom post type "event" is registered',
					),
					array(
						'type'        => 'rest_endpoint_registered',
						'method'      => 'POST',
						'path'        => '/event-manager/v1/events',
						'description' => 'REST endpoint POST /event-manager/v1/events is registered',
					),
					array(
						'type'            => 'rest_endpoint_response',
						'method'          => 'POST',
						'path'            => '/event-manager/v1/events',
						'body'            => array(
							'title' => 'Test Event',
							'date'  => '2026-06-15',
						),
						'expected_status' => 403,
						'description'     => 'Endpoint returns 403 for unauthenticated requests',
					),
				),
			),

			// ── Database ──────────────────────────────────────────────────────

			array(
				'id'         => 'fn-002',
				'category'   => 'database',
				'max_turns'  => 20,
				'prompt'     => 'Build a WordPress plugin called "api-logger" that creates a custom database table called "api_logs" (with the WordPress table prefix) on plugin activation. The table must have these columns: id (bigint, auto-increment primary key), user_id (bigint), endpoint (varchar 255), method (varchar 10), status_code (int), response_time_ms (int), created_at (datetime). Use dbDelta() for the table creation and store the installed version in a WordPress option. On deactivation, do NOT drop the table — only clear any scheduled events. Activate the plugin when done.',
				'assertions' => array(
					array(
						'type'        => 'plugin_no_php_errors',
						'description' => 'Plugin PHP files have no syntax errors',
					),
					array(
						'type'        => 'plugin_activates',
						'description' => 'Plugin activates without fatal errors',
					),
					array(
						'type'        => 'db_table_exists',
						'table'       => 'api_logs',
						'description' => 'Table {prefix}api_logs exists after activation',
					),
					array(
						'type'        => 'db_table_has_columns',
						'table'       => 'api_logs',
						'columns'     => array( 'id', 'user_id', 'endpoint', 'method', 'status_code', 'response_time_ms', 'created_at' ),
						'description' => 'Table has all 7 required columns',
					),
					array(
						'type'        => 'option_exists',
						'option'      => 'api_logger_version',
						'description' => 'Plugin version stored in WordPress options',
					),
				),
			),

			// ── Shortcode ─────────────────────────────────────────────────────

			array(
				'id'         => 'fn-003',
				'category'   => 'shortcode',
				'max_turns'  => 20,
				'prompt'     => 'Build a WordPress plugin called "post-grid" that registers a shortcode [post_grid]. The shortcode should accept these attributes: category (default empty), columns (default 3, must be 1-4), limit (default 9, max 20). It should query published posts filtered by category slug if provided, render them as a <div class="post-grid post-grid-columns-{N}"> containing article elements each with the post title as an <h2> wrapped in a permalink, and the excerpt. All output must be properly escaped. The plugin must use output buffering to return the shortcode content. Activate the plugin when done.',
				'assertions' => array(
					array(
						'type'        => 'plugin_no_php_errors',
						'description' => 'Plugin PHP files have no syntax errors',
					),
					array(
						'type'        => 'plugin_activates',
						'description' => 'Plugin activates without fatal errors',
					),
					array(
						'type'        => 'shortcode_registered',
						'tag'         => 'post_grid',
						'description' => 'Shortcode [post_grid] is registered',
					),
					array(
						'type'        => 'file_contains',
						'file'        => 'post-grid.php',
						'pattern'     => 'ob_start',
						'description' => 'Plugin uses output buffering',
					),
					array(
						'type'        => 'file_contains',
						'file'        => 'post-grid.php',
						'pattern'     => 'esc_html|esc_attr|esc_url',
						'description' => 'Plugin escapes output',
					),
				),
			),

			// ── WP-CLI ────────────────────────────────────────────────────────

			array(
				'id'         => 'fn-004',
				'category'   => 'wp_cli',
				'max_turns'  => 25,
				'prompt'     => 'Build a WordPress plugin called "meta-migrator" that provides a WP-CLI command "wp meta-migrator run". The command should: accept a --dry-run flag that previews changes without writing them, find all posts that have a meta key "_legacy_price" and copy its value to "_price", process posts in batches of 50 to avoid memory issues, output progress as "Processed X/Y posts", and at the end print a summary "Migration complete: X posts updated". Register the WP-CLI command only when WP_CLI is defined. Activate the plugin when done.',
				'assertions' => array(
					array(
						'type'        => 'plugin_no_php_errors',
						'description' => 'Plugin PHP files have no syntax errors',
					),
					array(
						'type'        => 'plugin_activates',
						'description' => 'Plugin activates without fatal errors',
					),
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'meta-migrator run --dry-run --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'dry.run|Migration complete|Processed',
						'expected_exit_code'      => 0,
						'description'             => 'WP-CLI command runs successfully in dry-run mode',
					),
					array(
						'type'        => 'file_contains',
						'file'        => 'meta-migrator.php',
						'pattern'     => 'WP_CLI',
						'description' => 'Plugin checks WP_CLI before registering command',
					),
				),
			),

			// ── Hooks & Filters ───────────────────────────────────────────────

			array(
				'id'         => 'fn-005',
				'category'   => 'hooks',
				'max_turns'  => 20,
				'prompt'     => 'Build a WordPress plugin called "content-tracker" that tracks post views. It should: hook into "wp" to detect single post views and increment a view count stored in post meta as "_view_count", add a filter on "the_content" to append a view count badge at the bottom of single post content (e.g. "👁 42 views"), register a shortcode [view_count post_id=123] that returns the view count for any post, and store the last-viewed timestamp in post meta as "_last_viewed". Make sure it only counts views on the frontend (not in wp-admin). Activate the plugin when done.',
				'assertions' => array(
					array(
						'type'        => 'plugin_no_php_errors',
						'description' => 'Plugin PHP files have no syntax errors',
					),
					array(
						'type'        => 'plugin_activates',
						'description' => 'Plugin activates without fatal errors',
					),
					array(
						'type'             => 'hook_registered',
						'hook'             => 'wp',
						'callback_pattern' => 'view|track|count',
						'description'      => 'Hook on "wp" registered for view tracking',
					),
					array(
						'type'             => 'hook_registered',
						'hook'             => 'the_content',
						'callback_pattern' => 'view|badge|content|append',
						'description'      => 'Filter on "the_content" registered for badge injection',
					),
					array(
						'type'        => 'shortcode_registered',
						'tag'         => 'view_count',
						'description' => 'Shortcode [view_count] is registered',
					),
				),
			),

			// ── Settings & Options ────────────────────────────────────────────

			array(
				'id'         => 'fn-006',
				'category'   => 'settings',
				'max_turns'  => 20,
				'prompt'     => 'Build a WordPress plugin called "site-announcements" that lets admins create site-wide announcement banners. It should: add a Settings > Site Announcements admin page where admins can set a banner message (text), banner color (hex color, default #0073aa), and whether the banner is active (checkbox). Store settings under the option key "site_announcements_settings" as an array. Hook into "wp_footer" to output the banner HTML when active (a <div id="site-announcement-banner"> with inline background-color style and the message). Register a settings section and fields using the WordPress Settings API. Activate the plugin when done.',
				'assertions' => array(
					array(
						'type'        => 'plugin_no_php_errors',
						'description' => 'Plugin PHP files have no syntax errors',
					),
					array(
						'type'        => 'plugin_activates',
						'description' => 'Plugin activates without fatal errors',
					),
					array(
						'type'             => 'hook_registered',
						'hook'             => 'wp_footer',
						'callback_pattern' => 'banner|announcement|output|render',
						'description'      => 'Hook on "wp_footer" for banner output',
					),
					array(
						'type'        => 'file_contains',
						'file'        => 'site-announcements.php',
						'pattern'     => 'register_setting|add_settings',
						'description' => 'Plugin uses WordPress Settings API',
					),
					array(
						'type'        => 'file_contains',
						'file'        => 'site-announcements.php',
						'pattern'     => 'site_announcements_settings',
						'description' => 'Plugin uses the correct option key',
					),
				),
			),

			// ── WooCommerce ───────────────────────────────────────────────────

			array(
				'id'         => 'fn-007',
				'category'   => 'woocommerce',
				'max_turns'  => 20,
				'prompt'     => 'Create a new WooCommerce product called "Ergonomic Standing Desk" with a regular price of $599.99, sale price of $449.99, SKU "DESK-ERG-001", stock quantity of 50, category "Office Furniture". Write a compelling description that mentions the adjustable height range (28-48 inches), bamboo desktop surface, integrated cable management, and 10-year warranty. Set the product status to published.',
				'assertions' => array(
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'wc product list --field=name --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'Ergonomic Standing Desk',
						'expected_exit_code'      => 0,
						'description'             => 'Product "Ergonomic Standing Desk" exists in WooCommerce',
					),
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'wc product list --field=sku --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'DESK-ERG-001',
						'expected_exit_code'      => 0,
						'description'             => 'Product has SKU DESK-ERG-001',
					),
				),
			),

			// ── Content Creation ──────────────────────────────────────────────

			array(
				'id'         => 'fn-008',
				'category'   => 'content',
				'max_turns'  => 15,
				'prompt'     => 'Create a published WordPress page called "CloudSync Pro" as a product landing page. It must include: a hero section with the headline "Sync Your Team\'s Files, Instantly" and a subheadline about cloud synchronization for teams, a features section covering real-time sync, end-to-end encryption, and team collaboration (each as its own heading), a pricing section with three tiers — Free ($0/mo), Pro ($9/mo), and Enterprise ($29/mo) — each with a description, and a call-to-action section with a "Get Started Free" button linking to "#signup". The page should use proper heading hierarchy.',
				'assertions' => array(
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'post list --post_type=page --post_status=publish --field=post_title --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'CloudSync Pro',
						'expected_exit_code'      => 0,
						'description'             => 'Page "CloudSync Pro" is published',
					),
					array(
						'type'                    => 'wp_cli_command',
						'command'                 => 'post list --post_type=page --post_status=publish --fields=post_title,post_content --format=json --url=wp-multisite-waas.test',
						'expected_output_pattern' => 'CloudSync|pricing|feature|Free|Pro|Enterprise',
						'expected_exit_code'      => 0,
						'description'             => 'Page content includes required sections',
					),
				),
			),

		);
	}
}
