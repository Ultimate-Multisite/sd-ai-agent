/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import SessionSidebar from '../components/session-sidebar';
import ChatPanel from '../components/chat-panel';
import OnboardingWizard from '../components/onboarding-wizard';
import './style.css';

function AdminPageApp() {
	const { fetchProviders, fetchSessions, fetchSettings } =
		useDispatch( STORE_NAME );
	const { settings, settingsLoaded } = useSelect(
		( select ) => ( {
			settings: select( STORE_NAME ).getSettings(),
			settingsLoaded: select( STORE_NAME ).getSettingsLoaded(),
		} ),
		[]
	);

	const [ showOnboarding, setShowOnboarding ] = useState( false );

	useEffect( () => {
		fetchProviders();
		fetchSessions();
		fetchSettings();
	}, [ fetchProviders, fetchSessions, fetchSettings ] );

	useEffect( () => {
		if ( settingsLoaded && settings ) {
			setShowOnboarding( settings.onboarding_complete === false );
		}
	}, [ settingsLoaded, settings ] );

	if ( ! settingsLoaded ) {
		return null;
	}

	if ( showOnboarding ) {
		return (
			<OnboardingWizard
				onComplete={ () => setShowOnboarding( false ) }
			/>
		);
	}

	return (
		<div className="ai-agent-layout">
			<SessionSidebar />
			<div className="ai-agent-main">
				<ChatPanel />
			</div>
		</div>
	);
}

const container = document.getElementById( 'ai-agent-root' );
if ( container ) {
	const root = createRoot( container );
	root.render( <AdminPageApp /> );
}
