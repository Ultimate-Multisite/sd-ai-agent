<?php

declare(strict_types=1);
/**
 * Integration tests for RestController endpoints.
 *
 * Uses the WordPress REST API test infrastructure (WP_REST_Server) to dispatch
 * real HTTP-style requests through the registered routes. Each test group covers:
 *   - Unauthenticated access is rejected (401/403).
 *   - Authenticated admin access succeeds (2xx).
 *   - Core CRUD behaviour for data-bearing endpoints.
 *
 * The /run and /process endpoints are tested for job creation and status
 * polling only — the background AgentLoop is not exercised here (that belongs
 * in AgentLoopTest, t014).
 *
 * @package AiAgent
 * @subpackage Tests\REST
 */

namespace AiAgent\Tests\REST;

use AiAgent\Core\Database;
use AiAgent\Models\Memory;
use AiAgent\Models\Skill;
use AiAgent\REST\RestController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Integration tests for RestController.
 */
class RestControllerTest extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected WP_REST_Server $server;

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected int $admin_id;

	/**
	 * Subscriber user ID (no manage_options).
	 *
	 * @var int
	 */
	protected int $subscriber_id;

	/**
	 * Set up REST server and test users before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init' );

		$this->admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
	}

	/**
	 * Tear down REST server after each test.
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	// ─── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Dispatch a REST request and return the response data.
	 *
	 * @param string $method  HTTP method.
	 * @param string $route   Route path (e.g. '/ai-agent/v1/memory').
	 * @param array  $params  Request parameters.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function dispatch( string $method, string $route, array $params = [] ) {
		$request = new WP_REST_Request( $method, $route );

		if ( in_array( $method, [ 'POST', 'PATCH', 'PUT' ], true ) ) {
			$request->set_body_params( $params );
		} else {
			$request->set_query_params( $params );
		}

		return $this->server->dispatch( $request );
	}

	/**
	 * Assert a response has the expected HTTP status code.
	 *
	 * @param int                        $expected Expected status code.
	 * @param \WP_REST_Response|\WP_Error $response Response to check.
	 */
	private function assertStatus( int $expected, $response ): void {
		if ( is_wp_error( $response ) ) {
			$data   = $response->get_error_data();
			$status = is_array( $data ) ? ( $data['status'] ?? 0 ) : 0;
		} else {
			$status = $response->get_status();
		}
		$this->assertSame( $expected, $status, "Expected HTTP {$expected}, got {$status}." );
	}

	// ─── Route Registration ───────────────────────────────────────────────────

	/**
	 * Test that all expected routes are registered.
	 */
	public function test_routes_are_registered(): void {
		$routes = $this->server->get_routes();

		$expected_routes = [
			'/ai-agent/v1/run',
			'/ai-agent/v1/job/(?P<id>[a-f0-9-]+)',
			'/ai-agent/v1/process',
			'/ai-agent/v1/abilities',
			'/ai-agent/v1/providers',
			'/ai-agent/v1/settings',
			'/ai-agent/v1/memory',
			'/ai-agent/v1/memory/(?P<id>\d+)',
			'/ai-agent/v1/memory/forget',
			'/ai-agent/v1/skills',
			'/ai-agent/v1/skills/(?P<id>\d+)',
			'/ai-agent/v1/sessions',
			'/ai-agent/v1/sessions/(?P<id>\d+)',
			'/ai-agent/v1/sessions/folders',
			'/ai-agent/v1/sessions/bulk',
			'/ai-agent/v1/sessions/trash',
			'/ai-agent/v1/usage',
			'/ai-agent/v1/custom-tools',
			'/ai-agent/v1/custom-tools/(?P<id>\d+)',
			'/ai-agent/v1/tool-profiles',
			'/ai-agent/v1/automations',
			'/ai-agent/v1/automations/(?P<id>\d+)',
			'/ai-agent/v1/event-automations',
			'/ai-agent/v1/event-automations/(?P<id>\d+)',
			'/ai-agent/v1/event-triggers',
			'/ai-agent/v1/automation-logs',
		];

		foreach ( $expected_routes as $route ) {
			$this->assertArrayHasKey( $route, $routes, "Route {$route} should be registered." );
		}
	}

	// ─── Permission: check_permission ────────────────────────────────────────

	/**
	 * Test unauthenticated request to /abilities is rejected.
	 */
	public function test_abilities_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/abilities' );
		$this->assertStatus( 401, $response );
	}

	/**
	 * Test subscriber (no manage_options) is rejected.
	 */
	public function test_abilities_requires_manage_options(): void {
		wp_set_current_user( $this->subscriber_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/abilities' );
		$this->assertStatus( 403, $response );
	}

	/**
	 * Test admin can access /abilities.
	 */
	public function test_abilities_admin_access(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/abilities' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	// ─── /providers ──────────────────────────────────────────────────────────

	/**
	 * Test unauthenticated request to /providers is rejected.
	 */
	public function test_providers_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/providers' );
		$this->assertStatus( 401, $response );
	}

	/**
	 * Test admin can access /providers.
	 */
	public function test_providers_admin_access(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/providers' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	// ─── /settings ───────────────────────────────────────────────────────────

	/**
	 * Test GET /settings returns settings array.
	 */
	public function test_get_settings(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/settings' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test POST /settings updates a setting.
	 */
	public function test_update_settings(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/settings', [
			'max_iterations' => 5,
		] );

		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertIsArray( $data );
	}

	/**
	 * Test unauthenticated access to /settings is rejected.
	 */
	public function test_settings_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/settings' );
		$this->assertStatus( 401, $response );
	}

	// ─── /memory ─────────────────────────────────────────────────────────────

	/**
	 * Test GET /memory returns list.
	 */
	public function test_list_memory(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/memory' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test POST /memory creates a memory entry.
	 */
	public function test_create_memory(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/memory', [
			'category' => 'general',
			'content'  => 'REST test memory content',
		] );

		$this->assertStatus( 201, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertGreaterThan( 0, $data['id'] );
	}

	/**
	 * Test POST /memory requires category.
	 */
	public function test_create_memory_missing_category(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/memory', [
			'content' => 'No category provided',
		] );

		$this->assertStatus( 400, $response );
	}

	/**
	 * Test POST /memory requires content.
	 */
	public function test_create_memory_missing_content(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/memory', [
			'category' => 'general',
		] );

		$this->assertStatus( 400, $response );
	}

	/**
	 * Test PATCH /memory/{id} updates a memory entry.
	 */
	public function test_update_memory(): void {
		wp_set_current_user( $this->admin_id );

		// Create via model directly.
		$memory_id = Memory::create( 'general', 'Original content' );

		$request = new WP_REST_Request( 'PATCH', "/ai-agent/v1/memory/{$memory_id}" );
		$request->set_body_params( [ 'content' => 'Updated via REST' ] );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertSame( 'Updated via REST', $data['content'] );
	}

	/**
	 * Test DELETE /memory/{id} removes a memory entry.
	 */
	public function test_delete_memory(): void {
		wp_set_current_user( $this->admin_id );

		$memory_id = Memory::create( 'general', 'To be deleted via REST' );

		$request  = new WP_REST_Request( 'DELETE', "/ai-agent/v1/memory/{$memory_id}" );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
	}

	/**
	 * Test PATCH /memory/{id} with non-existent ID returns 404.
	 */
	public function test_update_memory_not_found(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'PATCH', '/ai-agent/v1/memory/999999' );
		$request->set_body_params( [ 'content' => 'Ghost update' ] );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 404, $response );
	}

	/**
	 * Test POST /memory/forget requires topic.
	 */
	public function test_forget_memory_missing_topic(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/memory/forget', [] );
		$this->assertStatus( 400, $response );
	}

	/**
	 * Test POST /memory/forget with a topic returns success.
	 */
	public function test_forget_memory(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/memory/forget', [
			'topic' => 'nonexistent_topic_xyz',
		] );

		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'deleted', $data );
	}

	/**
	 * Test unauthenticated access to /memory is rejected.
	 */
	public function test_memory_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/memory' );
		$this->assertStatus( 401, $response );
	}

	// ─── /skills ─────────────────────────────────────────────────────────────

	/**
	 * Test GET /skills returns list.
	 */
	public function test_list_skills(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/skills' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test POST /skills creates a skill.
	 */
	public function test_create_skill(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/skills', [
			'slug'    => 'test-skill-rest-' . wp_generate_password( 6, false ),
			'name'    => 'REST Test Skill',
			'content' => 'You are a test skill.',
		] );

		$this->assertStatus( 201, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertGreaterThan( 0, $data['id'] );
	}

	/**
	 * Test POST /skills requires slug.
	 */
	public function test_create_skill_missing_slug(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/skills', [
			'name'    => 'No Slug Skill',
			'content' => 'Content here.',
		] );

		$this->assertStatus( 400, $response );
	}

	/**
	 * Test PATCH /skills/{id} updates a skill.
	 */
	public function test_update_skill(): void {
		wp_set_current_user( $this->admin_id );

		$skill_id = Skill::create( [
			'slug'    => 'update-test-' . wp_generate_password( 6, false ),
			'name'    => 'Original Skill Name',
			'content' => 'Original content.',
		] );

		$request = new WP_REST_Request( 'PATCH', "/ai-agent/v1/skills/{$skill_id}" );
		$request->set_body_params( [ 'name' => 'Updated Skill Name' ] );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertSame( 'Updated Skill Name', $data['name'] );
	}

	/**
	 * Test DELETE /skills/{id} removes a skill.
	 */
	public function test_delete_skill(): void {
		wp_set_current_user( $this->admin_id );

		$skill_id = Skill::create( [
			'slug'    => 'delete-test-' . wp_generate_password( 6, false ),
			'name'    => 'Skill To Delete',
			'content' => 'Delete me.',
		] );

		$request  = new WP_REST_Request( 'DELETE', "/ai-agent/v1/skills/{$skill_id}" );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
	}

	/**
	 * Test PATCH /skills/{id} with non-existent ID returns 404.
	 */
	public function test_update_skill_not_found(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'PATCH', '/ai-agent/v1/skills/999999' );
		$request->set_body_params( [ 'name' => 'Ghost' ] );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 404, $response );
	}

	/**
	 * Test unauthenticated access to /skills is rejected.
	 */
	public function test_skills_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/skills' );
		$this->assertStatus( 401, $response );
	}

	// ─── /sessions ───────────────────────────────────────────────────────────

	/**
	 * Test GET /sessions returns list.
	 */
	public function test_list_sessions(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/sessions' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test POST /sessions creates a session.
	 */
	public function test_create_session(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/sessions', [
			'title' => 'REST Integration Test Session',
		] );

		$this->assertStatus( 201, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertGreaterThan( 0, $data['id'] );
	}

	/**
	 * Test GET /sessions/{id} returns session data.
	 */
	public function test_get_session(): void {
		wp_set_current_user( $this->admin_id );

		$session_id = Database::create_session( [
			'user_id' => $this->admin_id,
			'title'   => 'Get Session Test',
		] );

		$response = $this->dispatch( 'GET', "/ai-agent/v1/sessions/{$session_id}" );
		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertSame( 'Get Session Test', $data['title'] );
	}

	/**
	 * Test GET /sessions/{id} for another user's session returns 403.
	 */
	public function test_get_session_other_user_forbidden(): void {
		wp_set_current_user( $this->admin_id );

		// Create session as admin.
		$session_id = Database::create_session( [
			'user_id' => $this->admin_id,
			'title'   => 'Admin Session',
		] );

		// Try to access as a different admin.
		$other_admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $other_admin );

		$response = $this->dispatch( 'GET', "/ai-agent/v1/sessions/{$session_id}" );
		$this->assertStatus( 403, $response );
	}

	/**
	 * Test PATCH /sessions/{id} updates session title.
	 */
	public function test_update_session(): void {
		wp_set_current_user( $this->admin_id );

		$session_id = Database::create_session( [
			'user_id' => $this->admin_id,
			'title'   => 'Original Title',
		] );

		$request = new WP_REST_Request( 'PATCH', "/ai-agent/v1/sessions/{$session_id}" );
		$request->set_body_params( [ 'title' => 'Updated Title' ] );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertSame( 'Updated Title', $data['title'] );
	}

	/**
	 * Test DELETE /sessions/{id} removes session.
	 */
	public function test_delete_session(): void {
		wp_set_current_user( $this->admin_id );

		$session_id = Database::create_session( [
			'user_id' => $this->admin_id,
			'title'   => 'To Delete',
		] );

		$request  = new WP_REST_Request( 'DELETE', "/ai-agent/v1/sessions/{$session_id}" );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
	}

	/**
	 * Test GET /sessions/folders returns folder list.
	 */
	public function test_list_folders(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/sessions/folders' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test POST /sessions/bulk with trash action.
	 */
	public function test_bulk_sessions_trash(): void {
		wp_set_current_user( $this->admin_id );

		$s1 = Database::create_session( [ 'user_id' => $this->admin_id, 'title' => 'Bulk 1' ] );
		$s2 = Database::create_session( [ 'user_id' => $this->admin_id, 'title' => 'Bulk 2' ] );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/sessions/bulk', [
			'ids'    => [ $s1, $s2 ],
			'action' => 'trash',
		] );

		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'updated', $data );
		$this->assertSame( 2, $data['updated'] );
	}

	/**
	 * Test DELETE /sessions/trash empties trash.
	 */
	public function test_empty_trash(): void {
		wp_set_current_user( $this->admin_id );

		$session_id = Database::create_session( [
			'user_id' => $this->admin_id,
			'title'   => 'Trash Me',
		] );
		Database::update_session( $session_id, [ 'status' => 'trash' ] );

		$request  = new WP_REST_Request( 'DELETE', '/ai-agent/v1/sessions/trash' );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'deleted', $data );
	}

	/**
	 * Test unauthenticated access to /sessions is rejected.
	 */
	public function test_sessions_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/sessions' );
		$this->assertStatus( 401, $response );
	}

	// ─── /usage ──────────────────────────────────────────────────────────────

	/**
	 * Test GET /usage returns usage summary.
	 */
	public function test_get_usage(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/usage' );
		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertIsArray( $data );
	}

	/**
	 * Test unauthenticated access to /usage is rejected.
	 */
	public function test_usage_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/usage' );
		$this->assertStatus( 401, $response );
	}

	// ─── /run and /job/{id} ───────────────────────────────────────────────────

	/**
	 * Test POST /run requires authentication.
	 */
	public function test_run_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'POST', '/ai-agent/v1/run', [
			'message' => 'Hello',
		] );
		$this->assertStatus( 401, $response );
	}

	/**
	 * Test POST /run requires message parameter.
	 */
	public function test_run_requires_message(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'POST', '/ai-agent/v1/run', [] );
		$this->assertStatus( 400, $response );
	}

	/**
	 * Test POST /run returns 202 with job_id.
	 *
	 * The background worker is not exercised — we only verify the job is
	 * created and the polling endpoint can find it.
	 */
	public function test_run_creates_job(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/run', [
			'message' => 'Test message for job creation',
		] );

		$this->assertStatus( 202, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'job_id', $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 'processing', $data['status'] );
		$this->assertNotEmpty( $data['job_id'] );
	}

	/**
	 * Test GET /job/{id} returns 404 for unknown job.
	 */
	public function test_job_status_not_found(): void {
		wp_set_current_user( $this->admin_id );

		$fake_id  = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
		$response = $this->dispatch( 'GET', "/ai-agent/v1/job/{$fake_id}" );

		$this->assertStatus( 404, $response );
	}

	/**
	 * Test GET /job/{id} returns processing status for a real job.
	 */
	public function test_job_status_processing(): void {
		wp_set_current_user( $this->admin_id );

		// Create a job via /run.
		$run_response = $this->dispatch( 'POST', '/ai-agent/v1/run', [
			'message' => 'Status check test',
		] );

		$this->assertStatus( 202, $run_response );
		$job_id = $run_response->get_data()['job_id'];

		// Poll the job — it will still be 'processing' since the background
		// worker hasn't run in the test environment.
		$status_response = $this->dispatch( 'GET', "/ai-agent/v1/job/{$job_id}" );
		$this->assertStatus( 200, $status_response );
		$data = $status_response->get_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 'processing', $data['status'] );
	}

	/**
	 * Test GET /job/{id} requires authentication.
	 */
	public function test_job_status_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/job/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee' );
		$this->assertStatus( 401, $response );
	}

	// ─── /custom-tools ────────────────────────────────────────────────────────

	/**
	 * Test GET /custom-tools returns list.
	 */
	public function test_list_custom_tools(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/custom-tools' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test POST /custom-tools creates a tool.
	 */
	public function test_create_custom_tool(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/custom-tools', [
			'name' => 'REST Test Tool',
			'type' => 'http',
		] );

		$this->assertStatus( 201, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
	}

	/**
	 * Test POST /custom-tools requires name.
	 */
	public function test_create_custom_tool_missing_name(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/custom-tools', [
			'type' => 'http',
		] );

		$this->assertStatus( 400, $response );
	}

	/**
	 * Test POST /custom-tools requires type.
	 */
	public function test_create_custom_tool_missing_type(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/custom-tools', [
			'name' => 'No Type Tool',
		] );

		$this->assertStatus( 400, $response );
	}

	/**
	 * Test unauthenticated access to /custom-tools is rejected.
	 */
	public function test_custom_tools_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/custom-tools' );
		$this->assertStatus( 401, $response );
	}

	// ─── /tool-profiles ──────────────────────────────────────────────────────

	/**
	 * Test GET /tool-profiles returns list.
	 */
	public function test_list_tool_profiles(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/tool-profiles' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test POST /tool-profiles creates a profile.
	 */
	public function test_create_tool_profile(): void {
		wp_set_current_user( $this->admin_id );

		$slug = 'rest-test-profile-' . wp_generate_password( 6, false );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/tool-profiles', [
			'slug'       => $slug,
			'name'       => 'REST Test Profile',
			'tool_names' => [ 'memory_get', 'memory_set' ],
		] );

		$this->assertStatus( 201, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'slug', $data );
		$this->assertSame( $slug, $data['slug'] );
	}

	/**
	 * Test DELETE /tool-profiles/{slug} removes a profile.
	 */
	public function test_delete_tool_profile(): void {
		wp_set_current_user( $this->admin_id );

		$slug = 'delete-profile-' . wp_generate_password( 6, false );

		// Create first.
		$this->dispatch( 'POST', '/ai-agent/v1/tool-profiles', [
			'slug' => $slug,
			'name' => 'Profile To Delete',
		] );

		$request  = new WP_REST_Request( 'DELETE', "/ai-agent/v1/tool-profiles/{$slug}" );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
	}

	/**
	 * Test unauthenticated access to /tool-profiles is rejected.
	 */
	public function test_tool_profiles_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/tool-profiles' );
		$this->assertStatus( 401, $response );
	}

	// ─── /automations ────────────────────────────────────────────────────────

	/**
	 * Test GET /automations returns list.
	 */
	public function test_list_automations(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/automations' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test POST /automations creates an automation.
	 */
	public function test_create_automation(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/automations', [
			'name'     => 'REST Test Automation',
			'prompt'   => 'Summarise recent posts.',
			'schedule' => 'daily',
		] );

		$this->assertStatus( 201, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
	}

	/**
	 * Test POST /automations requires name.
	 */
	public function test_create_automation_missing_name(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/automations', [
			'prompt' => 'No name provided.',
		] );

		$this->assertStatus( 400, $response );
	}

	/**
	 * Test POST /automations requires prompt.
	 */
	public function test_create_automation_missing_prompt(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/automations', [
			'name' => 'No Prompt Automation',
		] );

		$this->assertStatus( 400, $response );
	}

	/**
	 * Test PATCH /automations/{id} updates an automation.
	 */
	public function test_update_automation(): void {
		wp_set_current_user( $this->admin_id );

		// Create first.
		$create = $this->dispatch( 'POST', '/ai-agent/v1/automations', [
			'name'   => 'Update Test Automation',
			'prompt' => 'Original prompt.',
		] );
		$this->assertStatus( 201, $create );
		$automation_id = $create->get_data()['id'];

		$request = new WP_REST_Request( 'PATCH', "/ai-agent/v1/automations/{$automation_id}" );
		$request->set_body_params( [ 'name' => 'Updated Automation Name' ] );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
		$data = $response->get_data();
		$this->assertSame( 'Updated Automation Name', $data['name'] );
	}

	/**
	 * Test DELETE /automations/{id} removes an automation.
	 */
	public function test_delete_automation(): void {
		wp_set_current_user( $this->admin_id );

		$create = $this->dispatch( 'POST', '/ai-agent/v1/automations', [
			'name'   => 'Delete Test Automation',
			'prompt' => 'Delete me.',
		] );
		$this->assertStatus( 201, $create );
		$automation_id = $create->get_data()['id'];

		$request  = new WP_REST_Request( 'DELETE', "/ai-agent/v1/automations/{$automation_id}" );
		$response = $this->server->dispatch( $request );

		$this->assertStatus( 200, $response );
	}

	/**
	 * Test GET /automation-templates returns list.
	 */
	public function test_automation_templates(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/automation-templates' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test unauthenticated access to /automations is rejected.
	 */
	public function test_automations_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/automations' );
		$this->assertStatus( 401, $response );
	}

	// ─── /event-automations ──────────────────────────────────────────────────

	/**
	 * Test GET /event-automations returns list.
	 */
	public function test_list_event_automations(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/event-automations' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test POST /event-automations creates an event automation.
	 */
	public function test_create_event_automation(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/event-automations', [
			'name'            => 'REST Test Event Automation',
			'hook_name'       => 'publish_post',
			'prompt_template' => 'A post was published: {{post_title}}',
		] );

		$this->assertStatus( 201, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
	}

	/**
	 * Test POST /event-automations requires hook_name.
	 */
	public function test_create_event_automation_missing_hook(): void {
		wp_set_current_user( $this->admin_id );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/event-automations', [
			'name'            => 'No Hook',
			'prompt_template' => 'Template here.',
		] );

		$this->assertStatus( 400, $response );
	}

	/**
	 * Test GET /event-triggers returns list.
	 */
	public function test_list_event_triggers(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/event-triggers' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test GET /automation-logs returns list.
	 */
	public function test_list_automation_logs(): void {
		wp_set_current_user( $this->admin_id );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/automation-logs' );
		$this->assertStatus( 200, $response );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test unauthenticated access to /event-automations is rejected.
	 */
	public function test_event_automations_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = $this->dispatch( 'GET', '/ai-agent/v1/event-automations' );
		$this->assertStatus( 401, $response );
	}

	// ─── /process permission ─────────────────────────────────────────────────

	/**
	 * Test POST /process with no token is rejected.
	 */
	public function test_process_requires_valid_token(): void {
		wp_set_current_user( 0 );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/process', [
			'job_id' => 'fake-job-id',
			'token'  => 'invalid-token',
		] );

		// check_process_permission returns false → 401 (no cookie auth) or 403.
		$this->assertContains( $response->get_status(), [ 401, 403 ] );
	}

	/**
	 * Test POST /process with missing parameters is rejected.
	 */
	public function test_process_requires_job_id_and_token(): void {
		wp_set_current_user( 0 );

		$response = $this->dispatch( 'POST', '/ai-agent/v1/process', [] );
		$this->assertContains( $response->get_status(), [ 400, 401, 403 ] );
	}
}
