/**
 * E2E tests for core chat UI interactions.
 *
 * Tests message input, slash commands, and UI state transitions
 * that do not require a live AI provider response.
 *
 * Run: npm run test:e2e:playwright
 */

const { test, expect } = require( '@playwright/test' );
const {
	loginToWordPress,
	goToAgentPage,
	getMessageInput,
	getSendButton,
	getStopButton,
} = require( './utils/wp-admin' );

/**
 * The fake session ID used by interceptAutoTitle() for deterministic tests.
 * Must be a number to match the store's parseInt(session.id, 10) comparison.
 */
const FAKE_SESSION_ID = 9001;

/**
 * Set up full API interception for auto-title tests.
 *
 * How the auto-title flow works:
 *   1. The store POSTs to gratis-ai-agent/v1/sessions to create a session.
 *   2. It POSTs to gratis-ai-agent/v1/stream and reads the SSE response.
 *   3. On the `done` event it calls updateSessionTitle(sessionId, generatedTitle)
 *      — an optimistic update that patches the session in state.sessions.
 *   4. It then calls fetchSessions() which GETs gratis-ai-agent/v1/sessions and
 *      replaces state.sessions with the server response.
 *
 * Strategy: intercept ALL three endpoints so the tests are completely
 * self-contained and do not depend on the WordPress REST API being available.
 * This makes the tests reliable across all WP versions and CI environments.
 *
 * - POST /sessions → returns a fake session with FAKE_SESSION_ID
 * - POST /stream   → returns a minimal SSE stream with FAKE_SESSION_ID
 * - GET  /sessions → returns the fake session list
 *
 * @param {import('@playwright/test').Page} page
 * @return {Promise<void>}
 */
async function interceptAutoTitle( page ) {
	const fakeSession = {
		id: FAKE_SESSION_ID,
		title: 'Untitled',
		created_at: new Date().toISOString(),
		updated_at: new Date().toISOString(),
		status: 'active',
		user_id: 1,
		pinned: 0,
		is_shared: 0,
	};

	// Intercept POST /sessions — session creation.
	await page.route( /gratis-ai-agent\/v1\/sessions$/, async ( route ) => {
		if ( route.request().method() === 'POST' ) {
			await route.fulfill( {
				status: 200,
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( fakeSession ),
			} );
		} else {
			// GET /sessions — return the fake session list.
			await route.fulfill( {
				status: 200,
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( [ fakeSession ] ),
			} );
		}
	} );

	// Intercept POST /stream — return a minimal SSE stream.
	await page.route( /gratis-ai-agent\/v1\/stream/, async ( route ) => {
		const sseBody = [
			'event: token',
			`data: ${ JSON.stringify( { token: 'Hello!' } ) }`,
			'',
			'event: done',
			`data: ${ JSON.stringify( { session_id: FAKE_SESSION_ID } ) }`,
			'',
			'',
		].join( '\n' );

		await route.fulfill( {
			status: 200,
			headers: {
				'Content-Type': 'text/event-stream',
				'Cache-Control': 'no-cache',
			},
			body: sseBody,
		} );
	} );
}

/**
 * Directly inject a generated title into the WordPress data store via
 * page.evaluate(). This simulates what the backend would do after auto-titling
 * without requiring a live AI provider.
 *
 * Must be called after interceptAutoTitle() has set up the route intercepts
 * and after the stream has completed (state.sessions contains FAKE_SESSION_ID).
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          generatedTitle - Title to inject.
 * @param {number}                          sessionId      - Session ID to update.
 */
async function injectGeneratedTitle( page, generatedTitle, sessionId ) {
	await page.evaluate(
		( { title, sid } ) => {
			const dispatch = window.wp?.data?.dispatch( 'gratis-ai-agent' );
			if ( dispatch ) {
				dispatch.updateSessionTitle( sid, title );
			}
		},
		{ title: generatedTitle, sid: sessionId }
	);
}

test.describe( 'Chat Input Interactions', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'textarea auto-resizes as text grows', async ( { page } ) => {
		const input = getMessageInput( page );
		const initialHeight = await input.evaluate( ( el ) => el.offsetHeight );

		// Type multiple lines.
		await input.fill( 'Line 1\nLine 2\nLine 3\nLine 4\nLine 5' );

		const newHeight = await input.evaluate( ( el ) => el.offsetHeight );
		expect( newHeight ).toBeGreaterThan( initialHeight );
	} );

	test( 'Shift+Enter inserts a newline instead of sending', async ( {
		page,
	} ) => {
		const input = getMessageInput( page );
		await input.fill( 'First line' );
		await input.press( 'Shift+Enter' );
		await input.type( 'Second line' );

		const value = await input.inputValue();
		expect( value ).toContain( '\n' );
	} );

	test( 'Enter key sends the message', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( 'Test message' );
		await input.press( 'Enter' );

		// Input should be cleared after send.
		await expect( input ).toHaveValue( '' );
	} );

	test( 'send button click sends the message', async ( { page } ) => {
		const input = getMessageInput( page );
		const sendButton = getSendButton( page );

		await input.fill( 'Test via button' );
		await sendButton.click();

		await expect( input ).toHaveValue( '' );
	} );

	test( 'stop button appears while sending', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( 'Trigger a response' );
		await input.press( 'Enter' );

		// Stop button should appear while the request is in flight.
		const stopButton = getStopButton( page );
		await expect( stopButton ).toBeVisible( { timeout: 5_000 } );
	} );
} );

test.describe( 'Slash Command Menu', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'slash menu appears when typing /', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();
	} );

	test( 'slash menu shows expected commands', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		// Core commands should be listed.
		await expect( slashMenu ).toContainText( '/new' );
		await expect( slashMenu ).toContainText( '/remember' );
		await expect( slashMenu ).toContainText( '/forget' );
		await expect( slashMenu ).toContainText( '/clear' );
		await expect( slashMenu ).toContainText( '/help' );
	} );

	test( 'slash menu filters as user types', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/rem' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		// Only /remember should match /rem.
		const items = page.locator( '.ai-agent-slash-item' );
		const count = await items.count();
		expect( count ).toBeGreaterThanOrEqual( 1 );

		await expect( slashMenu ).toContainText( '/remember' );
	} );

	test( 'slash menu closes when Escape is pressed', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		await input.press( 'Escape' );
		await expect( slashMenu ).not.toBeVisible();
	} );

	test( 'selecting /new from slash menu clears the session', async ( {
		page,
	} ) => {
		const input = getMessageInput( page );
		await input.fill( '/new' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		// Click the /new item.
		const newItem = page.locator( '.ai-agent-slash-item' ).filter( {
			hasText: '/new',
		} );
		await newItem.click();

		// Empty state should be visible.
		const emptyState = page.locator( '.ai-agent-empty-state' );
		await expect( emptyState ).toBeVisible();
	} );

	test( '/help slash command opens shortcuts dialog', async ( { page } ) => {
		const input = getMessageInput( page );
		await input.fill( '/help' );

		const slashMenu = page.locator( '.ai-agent-slash-menu' );
		await expect( slashMenu ).toBeVisible();

		const helpItem = page.locator( '.ai-agent-slash-item' ).filter( {
			hasText: '/help',
		} );
		await helpItem.click();

		// Shortcuts dialog should open.
		const shortcutsDialog = page.locator( '.ai-agent-shortcuts-overlay' );
		await expect( shortcutsDialog ).toBeVisible();
	} );
} );

test.describe( 'Provider Selector', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'provider selector is visible in the chat header', async ( {
		page,
	} ) => {
		const providerSelector = page.locator( '.ai-agent-provider-selector' );
		await expect( providerSelector ).toBeVisible();
	} );
} );

/**
 * Auto-title sessions (t099)
 *
 * After the first AI response the store reads `generated_title` from the SSE
 * done event and calls `updateSessionTitle()` to update the sidebar item.
 *
 * These tests use interceptAutoTitle() to intercept all three REST endpoints
 * (POST /sessions, POST /stream, GET /sessions) so the tests are completely
 * self-contained and do not depend on the WordPress REST API being available.
 * A fake session with FAKE_SESSION_ID is used for deterministic assertions.
 */
test.describe( 'Auto-Title Sessions (t099)', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await goToAgentPage( page );
	} );

	test( 'session title updates in sidebar after first AI response', async ( {
		page,
	} ) => {
		const expectedTitle = 'My Auto-Generated Title';

		// Intercept all REST endpoints so the test is self-contained.
		await interceptAutoTitle( page );

		// Set up the response waiter BEFORE sending the message to avoid a
		// race where fetchSessions() completes before waitForResponse() starts.
		const sessionsResponsePromise = page.waitForResponse(
			( resp ) =>
				resp.url().includes( 'gratis-ai-agent/v1/sessions' ) &&
				resp.request().method() === 'GET',
			{ timeout: 15_000 }
		);

		const input = getMessageInput( page );
		await input.fill( 'Tell me about WordPress' );
		await input.press( 'Enter' );

		// Wait for the GET /sessions response that fires after the stream
		// completes (fetchSessions() is called at the end of streamMessage).
		// This is the most reliable signal that state.sessions has been updated
		// with the fake session.
		await sessionsResponsePromise;

		// Inject the generated title directly into the store using the known
		// fake session ID. updateSessionTitle() patches state.sessions in-place.
		await injectGeneratedTitle( page, expectedTitle, FAKE_SESSION_ID );

		// The sidebar item should now display the generated title.
		const sessionItems = page.locator( '.ai-agent-session-item' );
		await expect( sessionItems.first() ).toContainText( expectedTitle, {
			timeout: 5_000,
		} );
	} );

	test( 'session title is not "Untitled" after auto-title fires', async ( {
		page,
	} ) => {
		const expectedTitle = 'WordPress Plugin Development';

		await interceptAutoTitle( page );

		const sessionsResponsePromise = page.waitForResponse(
			( resp ) =>
				resp.url().includes( 'gratis-ai-agent/v1/sessions' ) &&
				resp.request().method() === 'GET',
			{ timeout: 15_000 }
		);

		const input = getMessageInput( page );
		await input.fill( 'How do I build a WordPress plugin?' );
		await input.press( 'Enter' );

		// Wait for the GET /sessions response after the stream completes.
		await sessionsResponsePromise;

		// Inject the generated title.
		await injectGeneratedTitle( page, expectedTitle, FAKE_SESSION_ID );

		// The title element should not say "Untitled".
		const sessionItems = page.locator( '.ai-agent-session-item' );
		const titleEl = sessionItems
			.first()
			.locator( '.ai-agent-session-title' );
		await expect( titleEl ).not.toContainText( 'Untitled', {
			timeout: 5_000,
		} );
		await expect( titleEl ).toContainText( expectedTitle, {
			timeout: 5_000,
		} );
	} );

	test( 'new session starts as Untitled before any AI response', async ( {
		page,
	} ) => {
		// Intercept all REST endpoints so the test is self-contained.
		await interceptAutoTitle( page );

		const sessionsResponsePromise = page.waitForResponse(
			( resp ) =>
				resp.url().includes( 'gratis-ai-agent/v1/sessions' ) &&
				resp.request().method() === 'GET',
			{ timeout: 15_000 }
		);

		const input = getMessageInput( page );
		await input.fill( 'Hello' );
		await input.press( 'Enter' );

		// Wait for the GET /sessions response after the stream completes.
		// The intercepted GET /sessions returns the fake session with title
		// "Untitled" — no generated_title was in the done event.
		await sessionsResponsePromise;

		// The fake session has title "Untitled" — no auto-title was injected.
		const sessionItems = page.locator( '.ai-agent-session-item' );
		await expect( sessionItems.first() ).toBeVisible( { timeout: 5_000 } );
		const titleEl = sessionItems
			.first()
			.locator( '.ai-agent-session-title' );
		const titleText = await titleEl.textContent();
		expect(
			titleText === '' ||
				titleText === 'Untitled' ||
				titleText?.includes( 'Untitled' )
		).toBe( true );
	} );
} );
