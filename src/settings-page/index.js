/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ErrorBoundary from '../components/error-boundary';
import SettingsApp from './settings-app';
import './style.css';

const container = document.getElementById( 'gratis-ai-agent-settings-root' );
if ( container ) {
	const root = createRoot( container );
	root.render(
		<ErrorBoundary label={ __( 'Settings', 'ai-agent' ) }>
			<SettingsApp />
		</ErrorBoundary>
	);
}
