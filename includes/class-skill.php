<?php
/**
 * Skill model — on-demand instruction guides for the AI agent.
 *
 * @package AiAgent
 */

namespace AiAgent;

class Skill {

	/**
	 * Get the skills table name.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ai_agent_skills';
	}

	/**
	 * Get all skills, optionally filtered by enabled status.
	 *
	 * @param bool|null $enabled Filter by enabled status (null = all).
	 * @return array
	 */
	public static function get_all( ?bool $enabled = null ): array {
		global $wpdb;

		$table = self::table_name();

		if ( null !== $enabled ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE enabled = %d ORDER BY name ASC",
					$table,
					$enabled ? 1 : 0
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i ORDER BY name ASC",
				$table
			)
		);
	}

	/**
	 * Get a single skill by ID.
	 *
	 * @param int $id Skill ID.
	 * @return object|null
	 */
	public static function get( int $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				self::table_name(),
				$id
			)
		);
	}

	/**
	 * Get a single skill by slug.
	 *
	 * @param string $slug Skill slug.
	 * @return object|null
	 */
	public static function get_by_slug( string $slug ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE slug = %s",
				self::table_name(),
				$slug
			)
		);
	}

	/**
	 * Create a new skill.
	 *
	 * @param array $data Skill data: slug, name, description, content, is_builtin, enabled.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$now    = current_time( 'mysql', true );
		$result = $wpdb->insert(
			self::table_name(),
			[
				'slug'        => sanitize_title( $data['slug'] ?? '' ),
				'name'        => sanitize_text_field( $data['name'] ?? '' ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'content'     => wp_kses_post( $data['content'] ?? '' ),
				'is_builtin'  => ! empty( $data['is_builtin'] ) ? 1 : 0,
				'enabled'     => isset( $data['enabled'] ) ? ( $data['enabled'] ? 1 : 0 ) : 1,
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing skill.
	 *
	 * @param int   $id   Skill ID.
	 * @param array $data Fields to update (name, description, content, enabled).
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$allowed = [ 'name', 'description', 'content', 'enabled' ];
		$data    = array_intersect_key( $data, array_flip( $allowed ) );

		if ( isset( $data['name'] ) ) {
			$data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['description'] ) ) {
			$data['description'] = sanitize_textarea_field( $data['description'] );
		}
		if ( isset( $data['content'] ) ) {
			$data['content'] = wp_kses_post( $data['content'] );
		}
		if ( isset( $data['enabled'] ) ) {
			$data['enabled'] = $data['enabled'] ? 1 : 0;
		}

		$data['updated_at'] = current_time( 'mysql', true );

		$formats = [];
		foreach ( $data as $key => $value ) {
			if ( $key === 'enabled' ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		$result = $wpdb->update(
			self::table_name(),
			$data,
			[ 'id' => $id ],
			$formats,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete a skill by ID (refuses built-in skills).
	 *
	 * @param int $id Skill ID.
	 * @return bool|string True on success, error message string if built-in.
	 */
	public static function delete( int $id ) {
		global $wpdb;

		$skill = self::get( $id );

		if ( ! $skill ) {
			return false;
		}

		if ( (int) $skill->is_builtin === 1 ) {
			return 'builtin';
		}

		$result = $wpdb->delete(
			self::table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Reset a built-in skill to its original content.
	 *
	 * @param int $id Skill ID.
	 * @return bool
	 */
	public static function reset_builtin( int $id ): bool {
		$skill = self::get( $id );

		if ( ! $skill || (int) $skill->is_builtin !== 1 ) {
			return false;
		}

		$builtins = self::get_builtin_definitions();

		if ( ! isset( $builtins[ $skill->slug ] ) ) {
			return false;
		}

		$definition = $builtins[ $skill->slug ];

		return self::update( $id, [
			'name'        => $definition['name'],
			'description' => $definition['description'],
			'content'     => $definition['content'],
		] );
	}

	/**
	 * Get a compact skill index for the system prompt (enabled skills only).
	 *
	 * @return string Formatted index or empty string if no skills enabled.
	 */
	public static function get_index_for_prompt(): string {
		$skills = self::get_all( true );

		if ( empty( $skills ) ) {
			return '';
		}

		$lines = [];
		foreach ( $skills as $skill ) {
			$lines[] = "- {$skill->slug}: {$skill->description}";
		}

		return "## Available Skills\n"
			. "You have access to specialized skill guides. When a user's request matches a skill topic,\n"
			. "use the ai-agent/skill-load tool to load the full instructions before proceeding.\n\n"
			. "Available skills:\n"
			. implode( "\n", $lines );
	}

	/**
	 * Idempotent seeding of built-in skills (skips if slug exists).
	 */
	public static function seed_builtins(): void {
		foreach ( self::get_builtin_definitions() as $slug => $definition ) {
			$existing = self::get_by_slug( $slug );

			if ( $existing ) {
				continue;
			}

			self::create( [
				'slug'        => $slug,
				'name'        => $definition['name'],
				'description' => $definition['description'],
				'content'     => $definition['content'],
				'is_builtin'  => true,
				'enabled'     => $definition['enabled'],
			] );
		}
	}

	/**
	 * Return the built-in skill definitions.
	 *
	 * @return array Keyed by slug.
	 */
	public static function get_builtin_definitions(): array {
		return [
			'wordpress-admin' => [
				'name'        => 'WordPress Administration',
				'description' => 'General WordPress administration (settings, updates, users, options)',
				'enabled'     => true,
				'content'     => self::builtin_wordpress_admin(),
			],
			'content-management' => [
				'name'        => 'Content Management',
				'description' => 'Managing posts, pages, media, taxonomies',
				'enabled'     => true,
				'content'     => self::builtin_content_management(),
			],
			'woocommerce' => [
				'name'        => 'WooCommerce Store Management',
				'description' => 'WooCommerce store management (products, orders, coupons)',
				'enabled'     => false,
				'content'     => self::builtin_woocommerce(),
			],
			'site-troubleshooting' => [
				'name'        => 'Site Troubleshooting',
				'description' => 'Debugging errors, site health, performance diagnosis',
				'enabled'     => true,
				'content'     => self::builtin_site_troubleshooting(),
			],
			'multisite-management' => [
				'name'        => 'Multisite Network Management',
				'description' => 'WordPress Multisite network administration',
				'enabled'     => false,
				'content'     => self::builtin_multisite_management(),
			],
		];
	}

	// ─── Built-in skill content ─────────────────────────────────────

	private static function builtin_wordpress_admin(): string {
		return <<<'MD'
# WordPress Administration

## When to Use
Use this skill when the user asks about general WordPress settings, updates, user management, or site configuration.

## Key WP-CLI Commands

### Settings & Options
- `wp option get <key>` — Read any option value
- `wp option update <key> <value>` — Update an option
- `wp option list --search=<pattern>` — Find options by name pattern

### User Management
- `wp user list --fields=ID,user_login,user_email,roles` — List users
- `wp user get <user> --fields=ID,user_login,user_email,roles` — Get user details
- `wp user create <login> <email> --role=<role>` — Create a user
- `wp user update <user> --role=<role>` — Change user role
- `wp user meta get <user> <key>` — Read user meta

### Updates
- `wp core version` — Current WordPress version
- `wp core check-update` — Check for core updates
- `wp plugin list --fields=name,status,version,update_version` — Check plugin updates
- `wp theme list --fields=name,status,version,update_version` — Check theme updates
- `wp plugin update <plugin>` — Update a plugin
- `wp theme update <theme>` — Update a theme

### Site Info
- `wp option get siteurl` — Site URL
- `wp option get home` — Home URL
- `wp option get blogname` — Site title
- `wp option get active_plugins --format=json` — Active plugins

## REST API Patterns
- `GET /wp/v2/settings` — Read site settings
- `POST /wp/v2/settings` — Update site settings
- `GET /wp/v2/users` — List users
- `GET /wp/v2/plugins` — List plugins (requires auth)

## Verification Steps
After making changes, always verify:
1. Read back the updated value with `wp option get` or the REST API
2. Check for errors in the response
3. Confirm the change had the expected effect
MD;
	}

	private static function builtin_content_management(): string {
		return <<<'MD'
# Content Management

## When to Use
Use this skill when the user asks about creating, editing, or managing posts, pages, media, categories, tags, or custom taxonomies.

## Key WP-CLI Commands

### Posts & Pages
- `wp post list --post_type=<type> --fields=ID,post_title,post_status,post_date` — List content
- `wp post get <id> --fields=ID,post_title,post_status,post_content` — Get single post
- `wp post create --post_type=<type> --post_title=<title> --post_status=<status>` — Create content
- `wp post update <id> --post_title=<title>` — Update content
- `wp post meta get <id> <key>` — Read post meta
- `wp post meta update <id> <key> <value>` — Update post meta

### Taxonomies
- `wp term list <taxonomy> --fields=term_id,name,slug,count` — List terms
- `wp term create <taxonomy> <name>` — Create a term
- `wp term update <taxonomy> <term_id> --name=<name>` — Update a term
- `wp post term list <post_id> <taxonomy>` — Terms assigned to a post
- `wp post term add <post_id> <taxonomy> <term>` — Assign term to post

### Media
- `wp media list --fields=ID,title,url,mime_type` — List media
- `wp media import <url>` — Import media from URL

### Search
- `wp post list --s=<query> --fields=ID,post_title,post_type` — Search content

## REST API Patterns
- `GET /wp/v2/posts?search=<query>&per_page=10` — Search posts
- `GET /wp/v2/pages` — List pages
- `POST /wp/v2/posts` — Create a post (requires title, content, status)
- `PUT /wp/v2/posts/<id>` — Update a post
- `GET /wp/v2/categories` — List categories
- `GET /wp/v2/tags` — List tags

## Verification Steps
After creating or updating content:
1. Retrieve the post/page to confirm changes saved
2. Check the post_status is as expected
3. Verify taxonomy assignments if relevant
MD;
	}

	private static function builtin_woocommerce(): string {
		return <<<'MD'
# WooCommerce Store Management

## When to Use
Use this skill when the user asks about WooCommerce products, orders, coupons, customers, or store settings.

## Key WP-CLI Commands

### Products
- `wp wc product list --fields=id,name,status,price,stock_status --user=1` — List products
- `wp wc product get <id> --user=1` — Get product details
- `wp wc product create --name=<name> --regular_price=<price> --user=1` — Create product
- `wp wc product update <id> --regular_price=<price> --user=1` — Update product

### Orders
- `wp wc order list --fields=id,status,total,date_created --user=1` — List orders
- `wp wc order get <id> --user=1` — Get order details
- `wp wc order update <id> --status=<status> --user=1` — Update order status

### Coupons
- `wp wc coupon list --fields=id,code,discount_type,amount --user=1` — List coupons
- `wp wc coupon create --code=<code> --discount_type=<type> --amount=<amount> --user=1` — Create coupon

### Store Settings
- `wp option get woocommerce_currency` — Store currency
- `wp option get woocommerce_store_address` — Store address
- `wp wc setting list general --user=1` — General settings

### Reports
- `wp wc report sales --period=month --user=1` — Sales report

## REST API Patterns
- `GET /wc/v3/products?search=<query>` — Search products
- `POST /wc/v3/products` — Create product
- `PUT /wc/v3/products/<id>` — Update product
- `GET /wc/v3/orders` — List orders
- `PUT /wc/v3/orders/<id>` — Update order
- `GET /wc/v3/coupons` — List coupons
- `POST /wc/v3/coupons` — Create coupon

Note: WooCommerce REST API requires authentication. WP-CLI commands need `--user=1` for admin context.

## Verification Steps
After making changes:
1. Retrieve the object to confirm updates
2. For products, verify price and stock status
3. For orders, confirm the status transition is valid
4. Check that WooCommerce is active before running wc commands
MD;
	}

	private static function builtin_site_troubleshooting(): string {
		return <<<'MD'
# Site Troubleshooting

## When to Use
Use this skill when the user reports errors, performance issues, white screens, or needs help diagnosing site problems.

## Diagnostic Commands

### Error Investigation
- `wp option get siteurl` / `wp option get home` — Check for URL mismatches
- `wp eval "error_reporting(E_ALL); ini_set('display_errors', 1);"` — Check PHP error reporting
- `wp config get WP_DEBUG` — Check debug mode status
- `wp config get WP_DEBUG_LOG` — Check if debug logging is on

### Plugin Conflicts
- `wp plugin list --status=active --fields=name,version` — List active plugins
- `wp plugin deactivate --all` — Deactivate all plugins (for conflict testing)
- `wp plugin activate <plugin>` — Reactivate one at a time

### Theme Issues
- `wp theme list --status=active` — Current active theme
- `wp theme activate twentytwentyfive` — Switch to default theme

### Database
- `wp db check` — Check database tables
- `wp db query "SELECT COUNT(*) FROM wp_options WHERE autoload='yes'"` — Check autoloaded options
- `wp transient delete --all` — Clear transients
- `wp cache flush` — Flush object cache

### Performance
- `wp db query "SELECT option_name, LENGTH(option_value) as size FROM wp_options WHERE autoload='yes' ORDER BY size DESC LIMIT 20"` — Large autoloaded options
- `wp cron event list` — Check scheduled events
- `wp rewrite flush` — Flush rewrite rules

### Site Health
- `wp core verify-checksums` — Verify core file integrity
- `wp plugin verify-checksums --all` — Verify plugin file integrity

## Common Issues & Solutions

### White Screen of Death
1. Enable WP_DEBUG: `wp config set WP_DEBUG true --raw`
2. Check debug.log: `wp eval "echo file_get_contents(WP_CONTENT_DIR . '/debug.log');"`
3. Deactivate plugins to find conflict
4. Switch to default theme

### 500 Internal Server Error
1. Check PHP error logs
2. Verify .htaccess: `wp rewrite flush`
3. Check file permissions
4. Increase PHP memory: `wp config set WP_MEMORY_LIMIT 256M`

### Slow Site
1. Check autoloaded options size
2. Review active plugins count
3. Check for long-running cron jobs
4. Verify object caching

## Verification Steps
After applying a fix:
1. Test the specific scenario that was broken
2. Check debug.log for new errors
3. Verify site loads correctly
4. Confirm no regressions
MD;
	}

	private static function builtin_multisite_management(): string {
		return <<<'MD'
# Multisite Network Management

## When to Use
Use this skill when the user asks about managing a WordPress Multisite network — sites, users across the network, network settings, or super admin tasks.

## Key WP-CLI Commands

### Network Sites
- `wp site list --fields=blog_id,url,registered,last_updated` — List all sites
- `wp site create --slug=<slug> --title=<title>` — Create a new site
- `wp site activate <id>` — Activate a site
- `wp site deactivate <id>` — Deactivate a site
- `wp site archive <id>` — Archive a site

### Super Admins
- `wp super-admin list` — List super admins
- `wp super-admin add <user>` — Grant super admin
- `wp super-admin remove <user>` — Revoke super admin

### Network Plugins & Themes
- `wp plugin list --fields=name,status --url=<site>` — Plugins on specific site
- `wp theme list --fields=name,status --url=<site>` — Themes on specific site
- `wp plugin activate <plugin> --network` — Network activate plugin
- `wp theme enable <theme> --network` — Network enable theme

### Network Options
- `wp network meta get 1 <key>` — Read network option
- `wp network meta update 1 <key> <value>` — Update network option

### Cross-site Operations
- `wp site list --field=url | xargs -I {} wp option get blogname --url={}` — Run command across all sites
- `wp user list --network --fields=ID,user_login,user_email` — Network-wide user list

## REST API Patterns
- `GET /wp/v2/sites` — List network sites (WP 5.9+)
- Site-specific requests need `--url=<site-url>` flag in WP-CLI

## Verification Steps
After network changes:
1. Verify the site is accessible at its URL
2. Check that plugins/themes are correctly activated
3. Confirm user roles across relevant sites
4. Test network admin access
MD;
	}
}
