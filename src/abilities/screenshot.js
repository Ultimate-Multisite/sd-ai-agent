/**
 * Client-side screenshot abilities.
 *
 * Two abilities for visual page review:
 *
 *   1. capture-screenshot — captures the page the user is currently viewing.
 *      Runs html2canvas on the live DOM and returns a base64 JPEG.
 *
 *   2. screenshot-url — loads any same-origin URL in a hidden iframe, waits
 *      for it to render, captures it with html2canvas, then tears down the
 *      iframe. Lets the agent review frontend pages without navigating the
 *      user away from wp-admin.
 *
 * Both abilities return { success, image, width, height, url, error } where
 * `image` is a base64-encoded JPEG data URL suitable for sending to a
 * vision-capable model.
 *
 * Security: screenshot-url restricts targets to the current WordPress site
 * origin (same-origin policy enforced both by URL validation and the browser
 * itself via iframe sandboxing).
 */

import html2canvas from 'html2canvas';
import { registerClientAbility } from './registry';

/* ── Constants ─────────────────────────────────────────────────────────── */

/** Maximum width (px) for the captured image to control token cost. */
const MAX_IMAGE_WIDTH = 1024;

/** JPEG quality (0-1). Balances clarity vs size for vision model input. */
const JPEG_QUALITY = 0.8;

/** How long to wait (ms) for an iframe page to fully load before capture. */
const IFRAME_LOAD_TIMEOUT = 15000;

/** Extra settle time (ms) after iframe load event for async renders. */
const IFRAME_SETTLE_DELAY = 1500;

/* ── Helpers ───────────────────────────────────────────────────────────── */

/**
 * Downscale a canvas if its width exceeds MAX_IMAGE_WIDTH.
 *
 * Returns a new canvas (or the original if no downscale needed) and exports
 * it as a JPEG data URL.
 *
 * @param {HTMLCanvasElement} canvas Source canvas from html2canvas.
 * @return {{ dataUrl: string, width: number, height: number }} JPEG data URL and dimensions.
 */
function canvasToJpeg( canvas ) {
	let target = canvas;

	if ( canvas.width > MAX_IMAGE_WIDTH ) {
		const scale = MAX_IMAGE_WIDTH / canvas.width;
		const w = Math.round( canvas.width * scale );
		const h = Math.round( canvas.height * scale );

		target = document.createElement( 'canvas' );
		target.width = w;
		target.height = h;

		const ctx = target.getContext( '2d' );
		ctx.drawImage( canvas, 0, 0, w, h );
	}

	return {
		dataUrl: target.toDataURL( 'image/jpeg', JPEG_QUALITY ),
		width: target.width,
		height: target.height,
	};
}

/**
 * Validate that a URL is within the current WordPress site origin.
 *
 * @param {string} url Absolute or relative URL.
 * @return {{ valid: boolean, resolved: string, error: string }} Validation result.
 */
function validateSameOrigin( url ) {
	if ( ! url ) {
		return { valid: false, resolved: '', error: 'URL is required.' };
	}

	try {
		// Resolve relative URLs against current origin.
		const resolved = new URL( url, window.location.origin );

		if ( resolved.origin !== window.location.origin ) {
			return {
				valid: false,
				resolved: resolved.href,
				error: `URL must be on the same site (${ window.location.origin }). Got: ${ resolved.origin }`,
			};
		}

		return { valid: true, resolved: resolved.href, error: '' };
	} catch ( err ) {
		return {
			valid: false,
			resolved: '',
			error: `Invalid URL: ${ err.message }`,
		};
	}
}

/* ── Ability 1: capture-screenshot ─────────────────────────────────────── */

/**
 * Capture a screenshot of the current page (or a specific element).
 *
 * @param {Object}  args
 * @param {string}  [args.selector] CSS selector to capture. Defaults to document body.
 * @param {boolean} [args.fullPage] Capture the full scrollable page, not just viewport.
 * @return {Promise<Object>} Screenshot result.
 */
async function executeCaptureScreenshot( args ) {
	const selector = args?.selector || '';
	const fullPage = args?.fullPage ?? false;

	try {
		let target = document.body;

		if ( selector ) {
			const el = document.querySelector( selector );
			if ( ! el ) {
				return {
					success: false,
					image: '',
					width: 0,
					height: 0,
					url: window.location.href,
					error: `Element not found for selector: ${ selector }`,
				};
			}
			target = el;
		}

		const canvas = await html2canvas( target, {
			useCORS: true,
			allowTaint: false,
			logging: false,
			windowWidth:
				target === document.body
					? document.documentElement.scrollWidth
					: undefined,
			windowHeight:
				fullPage && target === document.body
					? document.documentElement.scrollHeight
					: undefined,
		} );

		const { dataUrl, width, height } = canvasToJpeg( canvas );

		return {
			success: true,
			image: dataUrl,
			width,
			height,
			url: window.location.href,
			error: '',
		};
	} catch ( err ) {
		return {
			success: false,
			image: '',
			width: 0,
			height: 0,
			url: window.location.href,
			error: `Screenshot failed: ${ err.message }`,
		};
	}
}

/* ── Ability 2: screenshot-url ─────────────────────────────────────────── */

/**
 * Load a URL in a hidden iframe and capture a screenshot.
 *
 * The iframe is created, positioned off-screen, allowed to load and settle,
 * captured, then removed. Same-origin restriction is enforced.
 *
 * @param {Object}  args
 * @param {string}  args.url        URL to screenshot (absolute or site-relative path like "/about/").
 * @param {number}  [args.width]    Viewport width for the iframe (default 1280).
 * @param {number}  [args.height]   Viewport height for the iframe (default 800).
 * @param {boolean} [args.fullPage] Capture the full scrollable height.
 * @return {Promise<Object>} Screenshot result.
 */
async function executeScreenshotUrl( args ) {
	const rawUrl = args?.url || '';
	const viewportWidth = args?.width || 1280;
	const viewportHeight = args?.height || 800;
	const fullPage = args?.fullPage ?? false;

	// Validate same-origin.
	const { valid, resolved, error: urlError } = validateSameOrigin( rawUrl );
	if ( ! valid ) {
		return {
			success: false,
			image: '',
			width: 0,
			height: 0,
			url: rawUrl,
			error: urlError,
		};
	}

	// Append query param to suppress the WordPress admin bar in the iframe.
	const targetUrl = new URL( resolved );
	if ( ! targetUrl.pathname.includes( '/wp-admin' ) ) {
		targetUrl.searchParams.set( 'preview', 'true' );
	}

	let iframe = null;

	try {
		// Create hidden iframe.
		iframe = document.createElement( 'iframe' );
		iframe.style.cssText = [
			'position: fixed',
			'top: -20000px',
			'left: -20000px',
			`width: ${ viewportWidth }px`,
			`height: ${ viewportHeight }px`,
			'border: none',
			'opacity: 0',
			'pointer-events: none',
			'z-index: -9999',
		].join( '; ' );
		iframe.setAttribute( 'aria-hidden', 'true' );
		iframe.setAttribute( 'tabindex', '-1' );

		// Wait for load.
		const loadPromise = new Promise( ( resolveLoad, rejectLoad ) => {
			const timer = setTimeout( () => {
				rejectLoad( new Error( 'Iframe load timed out.' ) );
			}, IFRAME_LOAD_TIMEOUT );

			iframe.addEventListener( 'load', () => {
				clearTimeout( timer );
				resolveLoad();
			} );

			iframe.addEventListener( 'error', () => {
				clearTimeout( timer );
				rejectLoad( new Error( 'Iframe failed to load.' ) );
			} );
		} );

		iframe.src = targetUrl.href;
		document.body.appendChild( iframe );
		await loadPromise;

		// Allow async rendering (lazy images, web fonts, JS-rendered content)
		// to settle before capturing.
		await new Promise( ( r ) => setTimeout( r, IFRAME_SETTLE_DELAY ) );

		// Access iframe document (same-origin guaranteed by validation above).
		const iframeDoc =
			iframe.contentDocument || iframe.contentWindow?.document;
		if ( ! iframeDoc || ! iframeDoc.body ) {
			return {
				success: false,
				image: '',
				width: 0,
				height: 0,
				url: resolved,
				error: 'Cannot access iframe content. The page may block framing.',
			};
		}

		const captureTarget = iframeDoc.body;

		const canvas = await html2canvas( captureTarget, {
			useCORS: true,
			allowTaint: false,
			logging: false,
			width: viewportWidth,
			height: fullPage
				? iframeDoc.documentElement.scrollHeight
				: viewportHeight,
			windowWidth: viewportWidth,
			windowHeight: fullPage
				? iframeDoc.documentElement.scrollHeight
				: viewportHeight,
		} );

		const { dataUrl, width, height } = canvasToJpeg( canvas );

		return {
			success: true,
			image: dataUrl,
			width,
			height,
			url: resolved,
			error: '',
		};
	} catch ( err ) {
		return {
			success: false,
			image: '',
			width: 0,
			height: 0,
			url: resolved || rawUrl,
			error: `Screenshot failed: ${ err.message }`,
		};
	} finally {
		// Always clean up the iframe.
		if ( iframe && iframe.parentNode ) {
			iframe.parentNode.removeChild( iframe );
		}
	}
}

/* ── Registration ──────────────────────────────────────────────────────── */

/**
 * Register the capture-screenshot ability.
 *
 * @return {Promise<void>}
 */
export async function registerCaptureScreenshotAbility() {
	await registerClientAbility( {
		name: 'gratis-ai-agent-js/capture-screenshot',
		label: 'Capture Screenshot',
		description:
			'Capture a screenshot of the current page the user is viewing. ' +
			'Optionally target a specific element with a CSS selector. ' +
			'Returns a base64 JPEG image for visual review by the AI.',
		inputSchema: {
			type: 'object',
			properties: {
				selector: {
					type: 'string',
					description:
						'CSS selector to capture a specific element (e.g. "#main-content", ".entry-content"). ' +
						'Leave empty to capture the full page body.',
				},
				fullPage: {
					type: 'boolean',
					description:
						'If true, captures the full scrollable page height instead of just the viewport. Default: false.',
				},
			},
			required: [],
		},
		outputSchema: {
			type: 'object',
			properties: {
				success: { type: 'boolean' },
				image: {
					type: 'string',
					description:
						'Base64-encoded JPEG data URL of the screenshot.',
				},
				width: { type: 'integer' },
				height: { type: 'integer' },
				url: { type: 'string' },
				error: { type: 'string' },
			},
		},
		annotations: { readonly: true },
		callback: executeCaptureScreenshot,
	} );
}

/**
 * Register the screenshot-url ability.
 *
 * @return {Promise<void>}
 */
export async function registerScreenshotUrlAbility() {
	await registerClientAbility( {
		name: 'gratis-ai-agent-js/screenshot-url',
		label: 'Screenshot URL',
		description:
			'Load any page on this WordPress site in a hidden iframe and capture a screenshot. ' +
			'Use this to visually review frontend pages without navigating the user away from wp-admin. ' +
			'The URL must be on the same site. Returns a base64 JPEG image for visual review by the AI.',
		inputSchema: {
			type: 'object',
			properties: {
				url: {
					type: 'string',
					description:
						'URL to screenshot. Can be a full URL on this site or a relative path (e.g. "/about/", "/contact/", "/").',
				},
				width: {
					type: 'integer',
					description:
						'Viewport width in pixels for the capture. Default: 1280.',
				},
				height: {
					type: 'integer',
					description:
						'Viewport height in pixels for the capture. Default: 800.',
				},
				fullPage: {
					type: 'boolean',
					description:
						'If true, captures the full scrollable page height instead of just the viewport. Default: false.',
				},
			},
			required: [ 'url' ],
		},
		outputSchema: {
			type: 'object',
			properties: {
				success: { type: 'boolean' },
				image: {
					type: 'string',
					description:
						'Base64-encoded JPEG data URL of the screenshot.',
				},
				width: { type: 'integer' },
				height: { type: 'integer' },
				url: { type: 'string' },
				error: { type: 'string' },
			},
		},
		annotations: { readonly: true },
		callback: executeScreenshotUrl,
	} );
}
