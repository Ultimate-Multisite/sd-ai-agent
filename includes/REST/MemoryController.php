<?php

declare(strict_types=1);
/**
 * REST API controller for memories.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\REST;

use GratisAiAgent\Models\Memory;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class MemoryController {

	use PermissionTrait;

	const NAMESPACE = 'gratis-ai-agent/v1';

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		$instance = new self();

		// Memory endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/memory',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $instance, 'handle_list_memory' ),
					'permission_callback' => array( $instance, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $instance, 'handle_create_memory' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'category' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'  => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/memory/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $instance, 'handle_update_memory' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id'       => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'category' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'  => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $instance, 'handle_delete_memory' ),
					'permission_callback' => array( $instance, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Memory forget endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/memory/forget',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $instance, 'handle_forget_memory' ),
				'permission_callback' => array( $instance, 'check_permission' ),
				'args'                => array(
					'topic' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Handle GET /memory — list memories.
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	public function handle_list_memory( WP_REST_Request $request ): WP_REST_Response {
		$category = $request->get_param( 'category' );
		// @phpstan-ignore-next-line
		$memories = Memory::get_all( $category ?: null );

		$list = array_map(
			function ( $m ) {
				return array(
					// @phpstan-ignore-next-line
					'id'         => (int) $m->id,
					// @phpstan-ignore-next-line
					'category'   => $m->category,
					// @phpstan-ignore-next-line
					'content'    => $m->content,
					// @phpstan-ignore-next-line
					'created_at' => $m->created_at,
					// @phpstan-ignore-next-line
					'updated_at' => $m->updated_at,
				);
			},
			$memories
		);

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle POST /memory — create a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_memory( WP_REST_Request $request ) {
		$category = $request->get_param( 'category' );
		$content  = $request->get_param( 'content' );

		// @phpstan-ignore-next-line
		$id = Memory::create( $category, $content );

		if ( false === $id ) {
			return new WP_Error( 'gratis_ai_agent_memory_create_failed', __( 'Failed to create memory.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'id'       => $id,
				'category' => $category,
				'content'  => $content,
			),
			201
		);
	}

	/**
	 * Handle PATCH /memory/{id} — update a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_memory( WP_REST_Request $request ) {
		$id   = self::get_int_param( $request, 'id' );
		$data = array();

		if ( $request->has_param( 'category' ) ) {
			$data['category'] = $request->get_param( 'category' );
		}
		if ( $request->has_param( 'content' ) ) {
			$data['content'] = $request->get_param( 'content' );
		}

		$updated = Memory::update( $id, $data );

		if ( ! $updated ) {
			return new WP_Error( 'gratis_ai_agent_memory_update_failed', __( 'Failed to update memory.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'updated' => true,
				'id'      => $id,
			),
			200
		);
	}

	/**
	 * Handle DELETE /memory/{id} — delete a memory.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_memory( WP_REST_Request $request ) {
		$id      = self::get_int_param( $request, 'id' );
		$deleted = Memory::delete( $id );

		if ( ! $deleted ) {
			return new WP_Error( 'gratis_ai_agent_memory_delete_failed', __( 'Failed to delete memory.', 'gratis-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Handle POST /memory/forget — delete memories matching a topic.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_forget_memory( WP_REST_Request $request ): WP_REST_Response {
		$topic = $request->get_param( 'topic' );
		// @phpstan-ignore-next-line
		$deleted = Memory::forget_by_topic( $topic );

		return new WP_REST_Response(
			array(
				'deleted' => $deleted,
				'topic'   => $topic,
			),
			200
		);
	}
}
