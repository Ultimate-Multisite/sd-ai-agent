<?php
/**
 * Admin page for the AI Agent chat UI.
 *
 * Renders a full-page React app (two-column layout with session sidebar).
 *
 * @package AiAgent
 */

namespace AiAgent;

class Admin_Page {

	const SLUG = 'ai-agent';

	/**
	 * Register the admin menu page under Tools.
	 */
	public static function register(): void {
		$hook = add_management_page(
			__( 'AI Agent', 'ai-agent' ),
			__( 'AI Agent', 'ai-agent' ),
			'manage_options',
			self::SLUG,
			[ __CLASS__, 'render' ]
		);

		if ( $hook ) {
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		}
	}

	/**
	 * Enqueue the built React app only on our page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'tools_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}

		$asset_file = AI_AGENT_DIR . '/build/admin-page.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_style(
			'ai-agent-admin-page',
			AI_AGENT_URL . 'build/style-admin-page.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_enqueue_script(
			'ai-agent-admin-page',
			AI_AGENT_URL . 'build/admin-page.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/**
	 * Render the admin page — just a mount point for React.
	 */
	public static function render(): void {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'AI Agent', 'ai-agent' ) . '</h1>';
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'The WordPress AI Client is not available. Please install WordPress 7.0 or the AI Experiments plugin.', 'ai-agent' );
			echo '</p></div></div>';
			return;
		}

		?>
		<div class="wrap ai-agent-admin-wrap">
			<h1><?php esc_html_e( 'AI Agent', 'ai-agent' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Chat with an AI assistant that can interact with your WordPress site using registered abilities.', 'ai-agent' ); ?></p>
			<div id="ai-agent-root"></div>
		</div>
		<?php
	}
}
