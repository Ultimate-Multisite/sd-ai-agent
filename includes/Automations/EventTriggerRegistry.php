<?php

declare(strict_types=1);
/**
 * Event Trigger Registry — catalog of available WordPress hooks with metadata.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\Automations;

class EventTriggerRegistry {

	/**
	 * Get all available triggers grouped by category.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function get_all(): array {
		$triggers = array_merge(
			self::get_wordpress_triggers(),
			self::get_woocommerce_triggers(),
			self::get_form_triggers()
		);

		/**
		 * Filter available event triggers.
		 *
		 * @param array $triggers Array of trigger definitions.
		 */
		/** @var list<array<string, mixed>> $filtered */
		$filtered = apply_filters( 'sd_ai_agent_event_triggers', $triggers );
		return $filtered;
	}

	/**
	 * Get triggers grouped by category for the UI.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_grouped(): array {
		$all = self::get_all();
		/** @var array<string, array{label: string, triggers: list<array<string, mixed>>}> $grouped */
		$grouped = [];

		foreach ( $all as $trigger ) {
			if ( ! is_array( $trigger ) ) {
				continue;
			}
			$cat = isset( $trigger['category'] ) && is_string( $trigger['category'] ) ? $trigger['category'] : 'other';
			if ( ! isset( $grouped[ $cat ] ) ) {
				$grouped[ $cat ] = [
					'label'    => self::get_category_label( $cat ),
					'triggers' => [],
				];
			}
			$grouped[ $cat ]['triggers'][] = $trigger;
		}

		return $grouped;
	}

	/**
	 * Get a trigger definition by hook name.
	 *
	 * @param string $hook_name WordPress hook name.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $hook_name ): ?array {
		foreach ( self::get_all() as $trigger ) {
			if ( is_array( $trigger ) && isset( $trigger['hook_name'] ) && $trigger['hook_name'] === $hook_name ) {
				return $trigger;
			}
		}
		return null;
	}

	/**
	 * WordPress core triggers.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function get_wordpress_triggers(): array {
		return [
			[
				'hook_name'    => 'transition_post_status',
				'label'        => __( 'Post Status Changed', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a post status transitions (e.g. draft to publish).', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'new_status', 'old_status', 'post' ],
				'placeholders' => [
					'new_status'   => __( 'New post status', 'superdav-ai-agent' ),
					'old_status'   => __( 'Previous post status', 'superdav-ai-agent' ),
					'post.ID'      => __( 'Post ID', 'superdav-ai-agent' ),
					'post.title'   => __( 'Post title', 'superdav-ai-agent' ),
					'post.type'    => __( 'Post type', 'superdav-ai-agent' ),
					'post.author'  => __( 'Post author ID', 'superdav-ai-agent' ),
					'post.content' => __( 'Post content (excerpt)', 'superdav-ai-agent' ),
				],
				'conditions'   => [
					'post_type'  => __( 'Post type equals', 'superdav-ai-agent' ),
					'new_status' => __( 'New status equals', 'superdav-ai-agent' ),
					'old_status' => __( 'Old status equals', 'superdav-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'user_register',
				'label'        => __( 'New User Registered', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a new user account is created.', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'user_id' ],
				'placeholders' => [
					'user.id'           => __( 'User ID', 'superdav-ai-agent' ),
					'user.login'        => __( 'Username', 'superdav-ai-agent' ),
					'user.email'        => __( 'User email', 'superdav-ai-agent' ),
					'user.display_name' => __( 'Display name', 'superdav-ai-agent' ),
					'user.role'         => __( 'User role', 'superdav-ai-agent' ),
				],
				'conditions'   => [
					'role' => __( 'User role equals', 'superdav-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'wp_login',
				'label'        => __( 'User Login', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a user successfully logs in.', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'user_login', 'user' ],
				'placeholders' => [
					'user.login'        => __( 'Username', 'superdav-ai-agent' ),
					'user.email'        => __( 'User email', 'superdav-ai-agent' ),
					'user.display_name' => __( 'Display name', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'comment_post',
				'label'        => __( 'New Comment', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a new comment is posted.', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'comment_id', 'comment_approved' ],
				'placeholders' => [
					'comment.id'           => __( 'Comment ID', 'superdav-ai-agent' ),
					'comment.author'       => __( 'Comment author name', 'superdav-ai-agent' ),
					'comment.author_email' => __( 'Comment author email', 'superdav-ai-agent' ),
					'comment.content'      => __( 'Comment text', 'superdav-ai-agent' ),
					'comment.post_id'      => __( 'Post ID', 'superdav-ai-agent' ),
					'comment.approved'     => __( 'Approval status', 'superdav-ai-agent' ),
				],
				'conditions'   => [
					'approved' => __( 'Approval status equals', 'superdav-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'delete_post',
				'label'        => __( 'Post Deleted', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a post is permanently deleted.', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'post_id' ],
				'placeholders' => [
					'post.ID'    => __( 'Post ID', 'superdav-ai-agent' ),
					'post.title' => __( 'Post title', 'superdav-ai-agent' ),
					'post.type'  => __( 'Post type', 'superdav-ai-agent' ),
				],
				'conditions'   => [
					'post_type' => __( 'Post type equals', 'superdav-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'activated_plugin',
				'label'        => __( 'Plugin Activated', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a plugin is activated.', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'plugin' ],
				'placeholders' => [
					'plugin' => __( 'Plugin file path', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'deactivated_plugin',
				'label'        => __( 'Plugin Deactivated', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a plugin is deactivated.', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'plugin' ],
				'placeholders' => [
					'plugin' => __( 'Plugin file path', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'switch_theme',
				'label'        => __( 'Theme Switched', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when the active theme is changed.', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'new_name', 'new_theme' ],
				'placeholders' => [
					'new_name' => __( 'New theme name', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'profile_update',
				'label'        => __( 'User Profile Updated', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a user profile is updated.', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'user_id', 'old_user_data' ],
				'placeholders' => [
					'user.id'           => __( 'User ID', 'superdav-ai-agent' ),
					'user.email'        => __( 'User email', 'superdav-ai-agent' ),
					'user.display_name' => __( 'Display name', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'wp_login_failed',
				'label'        => __( 'Failed Login Attempt', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a login attempt fails.', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'username' ],
				'placeholders' => [
					'username' => __( 'Attempted username', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'added_option',
				'label'        => __( 'Option Added', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a new option is added to the database.', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'option_name', 'option_value' ],
				'placeholders' => [
					'option_name' => __( 'Option name', 'superdav-ai-agent' ),
				],
				'conditions'   => [
					'option_name' => __( 'Option name equals', 'superdav-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'updated_option',
				'label'        => __( 'Option Updated', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when an existing option is updated.', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'option_name', 'old_value', 'new_value' ],
				'placeholders' => [
					'option_name' => __( 'Option name', 'superdav-ai-agent' ),
				],
				'conditions'   => [
					'option_name' => __( 'Option name equals', 'superdav-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'add_attachment',
				'label'        => __( 'Media Uploaded', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a new media file is uploaded.', 'superdav-ai-agent' ),
				'category'     => 'wordpress',
				'args'         => [ 'post_id' ],
				'placeholders' => [
					'post.ID'    => __( 'Attachment ID', 'superdav-ai-agent' ),
					'post.title' => __( 'Attachment title', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			],
		];
	}

	/**
	 * WooCommerce triggers.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function get_woocommerce_triggers(): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return [];
		}

		return [
			[
				'hook_name'    => 'woocommerce_new_order',
				'label'        => __( 'New Order Created', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a new WooCommerce order is created.', 'superdav-ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'order_id' ],
				'placeholders' => [
					'order.id'     => __( 'Order ID', 'superdav-ai-agent' ),
					'order.total'  => __( 'Order total', 'superdav-ai-agent' ),
					'order.status' => __( 'Order status', 'superdav-ai-agent' ),
					'order.email'  => __( 'Customer email', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'woocommerce_order_status_changed',
				'label'        => __( 'Order Status Changed', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when an order status changes.', 'superdav-ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'order_id', 'old_status', 'new_status' ],
				'placeholders' => [
					'order.id'   => __( 'Order ID', 'superdav-ai-agent' ),
					'old_status' => __( 'Previous status', 'superdav-ai-agent' ),
					'new_status' => __( 'New status', 'superdav-ai-agent' ),
				],
				'conditions'   => [
					'new_status' => __( 'New status equals', 'superdav-ai-agent' ),
					'old_status' => __( 'Old status equals', 'superdav-ai-agent' ),
				],
			],
			[
				'hook_name'    => 'woocommerce_low_stock',
				'label'        => __( 'Product Low Stock', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a product reaches low stock threshold.', 'superdav-ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'product' ],
				'placeholders' => [
					'product.id'    => __( 'Product ID', 'superdav-ai-agent' ),
					'product.name'  => __( 'Product name', 'superdav-ai-agent' ),
					'product.stock' => __( 'Stock quantity', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'woocommerce_payment_complete',
				'label'        => __( 'Payment Complete', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a payment is completed.', 'superdav-ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'order_id' ],
				'placeholders' => [
					'order.id'    => __( 'Order ID', 'superdav-ai-agent' ),
					'order.total' => __( 'Order total', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'woocommerce_product_on_backorder',
				'label'        => __( 'Product On Backorder', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a product goes on backorder.', 'superdav-ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'item' ],
				'placeholders' => [
					'product.name' => __( 'Product name', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			],
			[
				'hook_name'    => 'woocommerce_refund_created',
				'label'        => __( 'Refund Created', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a refund is created.', 'superdav-ai-agent' ),
				'category'     => 'woocommerce',
				'args'         => [ 'refund_id', 'args' ],
				'placeholders' => [
					'refund_id' => __( 'Refund ID', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			],
		];
	}

	/**
	 * Form plugin triggers.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function get_form_triggers(): array {
		$triggers = [];

		// Contact Form 7.
		if ( defined( 'WPCF7_VERSION' ) ) {
			$triggers[] = [
				'hook_name'    => 'wpcf7_mail_sent',
				'label'        => __( 'CF7 Form Submitted', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a Contact Form 7 submission email is sent.', 'superdav-ai-agent' ),
				'category'     => 'forms',
				'args'         => [ 'contact_form' ],
				'placeholders' => [
					'form.title' => __( 'Form title', 'superdav-ai-agent' ),
					'form.id'    => __( 'Form ID', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			];
		}

		// Gravity Forms.
		if ( class_exists( 'GFForms' ) ) {
			$triggers[] = [
				'hook_name'    => 'gform_after_submission',
				'label'        => __( 'Gravity Form Submitted', 'superdav-ai-agent' ),
				'description'  => __( 'Fires after a Gravity Forms entry is created.', 'superdav-ai-agent' ),
				'category'     => 'forms',
				'args'         => [ 'entry', 'form' ],
				'placeholders' => [
					'form.title' => __( 'Form title', 'superdav-ai-agent' ),
					'form.id'    => __( 'Form ID', 'superdav-ai-agent' ),
					'entry.id'   => __( 'Entry ID', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			];
		}

		// WPForms.
		if ( defined( 'WPFORMS_VERSION' ) ) {
			$triggers[] = [
				'hook_name'    => 'wpforms_process_complete',
				'label'        => __( 'WPForms Form Submitted', 'superdav-ai-agent' ),
				'description'  => __( 'Fires when a WPForms entry is processed.', 'superdav-ai-agent' ),
				'category'     => 'forms',
				'args'         => [ 'fields', 'entry', 'form_data', 'entry_id' ],
				'placeholders' => [
					'form.title' => __( 'Form title', 'superdav-ai-agent' ),
					'entry_id'   => __( 'Entry ID', 'superdav-ai-agent' ),
				],
				'conditions'   => [],
			];
		}

		return $triggers;
	}

	/**
	 * Get a human-readable category label.
	 *
	 * @param string $category Category slug.
	 * @return string
	 */
	private static function get_category_label( string $category ): string {
		$labels = [
			'wordpress'   => __( 'WordPress', 'superdav-ai-agent' ),
			'woocommerce' => __( 'WooCommerce', 'superdav-ai-agent' ),
			'forms'       => __( 'Forms', 'superdav-ai-agent' ),
			'other'       => __( 'Other', 'superdav-ai-agent' ),
		];

		return $labels[ $category ] ?? ucfirst( $category );
	}
}
