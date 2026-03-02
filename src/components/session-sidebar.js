/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { plus } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

function relativeTime( dateStr ) {
	if ( ! dateStr ) {
		return '';
	}
	const date = new Date( dateStr + 'Z' );
	const now = new Date();
	const diff = Math.floor( ( now - date ) / 1000 );

	if ( diff < 60 ) {
		return __( 'just now', 'ai-agent' );
	}
	if ( diff < 3600 ) {
		return Math.floor( diff / 60 ) + 'm ago';
	}
	if ( diff < 86400 ) {
		return Math.floor( diff / 3600 ) + 'h ago';
	}
	if ( diff < 604800 ) {
		return Math.floor( diff / 86400 ) + 'd ago';
	}
	return date.toLocaleDateString();
}

function SessionItem( { session, isActive } ) {
	const { openSession, deleteSession } = useDispatch( STORE_NAME );

	const handleDelete = ( e ) => {
		e.stopPropagation();
		// eslint-disable-next-line no-alert
		if ( window.confirm( __( 'Delete this conversation?', 'ai-agent' ) ) ) {
			deleteSession( parseInt( session.id, 10 ) );
		}
	};

	return (
		<div
			className={ `ai-agent-session-item ${
				isActive ? 'is-active' : ''
			}` }
			onClick={ () => openSession( parseInt( session.id, 10 ) ) }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Enter' ) {
					openSession( parseInt( session.id, 10 ) );
				}
			} }
			role="button"
			tabIndex={ 0 }
		>
			<div className="ai-agent-session-title">
				{ session.title || __( 'Untitled', 'ai-agent' ) }
			</div>
			<div className="ai-agent-session-meta">
				{ relativeTime( session.updated_at ) }
			</div>
			<button
				className="ai-agent-session-delete"
				onClick={ handleDelete }
				title={ __( 'Delete', 'ai-agent' ) }
				type="button"
			>
				&times;
			</button>
		</div>
	);
}

export default function SessionSidebar() {
	const { sessions, currentSessionId } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			sessions: store.getSessions(),
			currentSessionId: store.getCurrentSessionId(),
		};
	}, [] );

	const { clearCurrentSession } = useDispatch( STORE_NAME );

	return (
		<div className="ai-agent-sidebar">
			<div className="ai-agent-sidebar-header">
				<Button
					variant="primary"
					icon={ plus }
					onClick={ clearCurrentSession }
					className="ai-agent-new-chat-btn"
				>
					{ __( 'New Chat', 'ai-agent' ) }
				</Button>
			</div>
			<div className="ai-agent-session-list">
				{ sessions.length === 0 && (
					<div className="ai-agent-session-empty">
						{ __( 'No conversations yet', 'ai-agent' ) }
					</div>
				) }
				{ sessions.map( ( session ) => (
					<SessionItem
						key={ session.id }
						session={ session }
						isActive={
							currentSessionId ===
							parseInt( session.id, 10 )
						}
					/>
				) ) }
			</div>
		</div>
	);
}
