<?php
/**
 * Plugin Name: AI Agent for WordPress
 * Plugin URI:  https://developer.wordpress.org/
 * Description: Agentic AI loop for WordPress — chat with an AI that can call WordPress abilities (tools) autonomously.
 * Version:     0.2.0
 * Author:      Dave
 * License:     GPL-2.0-or-later
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Text Domain: ai-agent
 *
 * @package AiAgent
 */

namespace AiAgent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_AGENT_DIR', __DIR__ );
define( 'AI_AGENT_URL', plugin_dir_url( __FILE__ ) );

require_once AI_AGENT_DIR . '/includes/class-database.php';
require_once AI_AGENT_DIR . '/includes/class-settings.php';
require_once AI_AGENT_DIR . '/includes/class-memory.php';
require_once AI_AGENT_DIR . '/includes/class-memory-abilities.php';
require_once AI_AGENT_DIR . '/includes/class-skill.php';
require_once AI_AGENT_DIR . '/includes/class-skill-abilities.php';
require_once AI_AGENT_DIR . '/includes/class-agent-loop.php';
require_once AI_AGENT_DIR . '/includes/class-rest-controller.php';
require_once AI_AGENT_DIR . '/includes/class-admin-page.php';
require_once AI_AGENT_DIR . '/includes/class-floating-widget.php';

register_activation_hook( __FILE__, [ Database::class, 'install' ] );
add_action( 'admin_init', [ Database::class, 'install' ] );

add_action( 'rest_api_init', [ Rest_Controller::class, 'register_routes' ] );
add_action( 'admin_menu', [ Admin_Page::class, 'register' ] );
add_action( 'admin_menu', [ Settings::class, 'register' ] );

// Register ability category.
add_action( 'wp_abilities_api_categories_init', function () {
	if ( function_exists( 'wp_register_ability_category' ) ) {
		wp_register_ability_category( 'ai-agent', [
			'label'       => __( 'AI Agent', 'ai-agent' ),
			'description' => __( 'AI Agent memory and skill abilities.', 'ai-agent' ),
		] );
	}
} );

// Memory abilities.
Memory_Abilities::register();

// Skill abilities.
Skill_Abilities::register();

// Floating widget on all admin pages.
Floating_Widget::register();
