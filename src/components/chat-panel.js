/**
 * WordPress dependencies
 */
import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ProviderSelector from './provider-selector';
import MessageList from './message-list';
import MessageInput from './message-input';
import ContextIndicator from './context-indicator';
import ToolConfirmationDialog from './tool-confirmation-dialog';

export default function ChatPanel( { compact = false, onSlashCommand } ) {
	const { confirmToolCall, rejectToolCall } = useDispatch( STORE_NAME );
	const { pendingConfirmation, debugMode, yoloMode } = useSelect(
		( select ) => ( {
			pendingConfirmation: select( STORE_NAME ).getPendingConfirmation(),
			debugMode: select( STORE_NAME ).isDebugMode(),
			yoloMode: select( STORE_NAME ).isYoloMode(),
		} ),
		[]
	);

	// When YOLO mode is active, auto-confirm any pending tool call immediately.
	useEffect( () => {
		if ( yoloMode && pendingConfirmation ) {
			confirmToolCall( pendingConfirmation.jobId, false );
		}
	}, [ yoloMode, pendingConfirmation, confirmToolCall ] );

	return (
		<div
			className={ `ai-agent-chat-panel ${ compact ? 'is-compact' : '' }` }
		>
			<div className="ai-agent-header">
				<ProviderSelector compact={ compact } />
				{ debugMode && (
					<span className="ai-agent-debug-badge">
						{ __( 'DEBUG', 'ai-agent' ) }
					</span>
				) }
				{ yoloMode && (
					<span
						className="ai-agent-yolo-badge"
						title={ __(
							'YOLO mode is active — all tool confirmations are skipped automatically.',
							'ai-agent'
						) }
					>
						{ __( 'YOLO', 'ai-agent' ) }
					</span>
				) }
			</div>
			<ContextIndicator />
			<MessageList />
			<MessageInput
				compact={ compact }
				onSlashCommand={ onSlashCommand }
			/>
			{ pendingConfirmation && ! yoloMode && (
				<ToolConfirmationDialog
					confirmation={ pendingConfirmation }
					onConfirm={ ( alwaysAllow ) =>
						confirmToolCall(
							pendingConfirmation.jobId,
							alwaysAllow
						)
					}
					onReject={ () =>
						rejectToolCall( pendingConfirmation.jobId )
					}
				/>
			) }
		</div>
	);
}
