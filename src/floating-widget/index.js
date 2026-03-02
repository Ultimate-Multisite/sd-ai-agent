/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import FloatingButton from './floating-button';
import FloatingPanel from './floating-panel';
import './style.css';

function FloatingWidget() {
	const { fetchProviders, fetchSessions, setPageContext } =
		useDispatch( STORE_NAME );
	const isOpen = useSelect(
		( select ) => select( STORE_NAME ).isFloatingOpen(),
		[]
	);

	useEffect( () => {
		fetchProviders();
		fetchSessions();
	}, [ fetchProviders, fetchSessions ] );

	// Gather page context on mount.
	useEffect( () => {
		const context = gatherPageContext();
		if ( context ) {
			setPageContext( context );
		}
	}, [ setPageContext ] );

	return (
		<>
			{ ! isOpen && <FloatingButton /> }
			{ isOpen && <FloatingPanel /> }
		</>
	);
}

/**
 * Gather context about the current admin page.
 */
function gatherPageContext() {
	const parts = [];

	// Page title.
	const heading =
		document.querySelector( '.wrap > h1' ) ||
		document.querySelector( '#wpbody-content h1' );
	if ( heading ) {
		parts.push( 'Page title: ' + heading.textContent.trim() );
	}

	// Current URL.
	parts.push( 'URL: ' + window.location.href );

	// Admin page from body classes.
	const bodyClasses = document.body.className;
	const pageMatch = bodyClasses.match(
		/(?:toplevel|[\w-]+)_page_[\w-]+|edit-php|post-php|upload-php|edit-tags-php/
	);
	if ( pageMatch ) {
		parts.push( 'Admin page class: ' + pageMatch[ 0 ] );
	}

	// Post type if editing.
	const postType = document.getElementById( 'post_type' );
	if ( postType ) {
		parts.push( 'Post type: ' + postType.value );
	}

	// Post title if editing.
	const titleField = document.getElementById( 'title' );
	if ( titleField && titleField.value ) {
		parts.push( 'Post title: ' + titleField.value );
	}

	// Screen ID from body class.
	const screenMatch = bodyClasses.match( /wp-admin\s/ );
	if ( screenMatch ) {
		parts.push( 'Screen: WordPress Admin' );
	}

	return parts.join( '\n' );
}

// Mount the floating widget.
const wrapper = document.createElement( 'div' );
wrapper.id = 'ai-agent-floating-root';
document.body.appendChild( wrapper );

const root = createRoot( wrapper );
root.render( <FloatingWidget /> );
