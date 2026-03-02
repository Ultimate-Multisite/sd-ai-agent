/**
 * Internal dependencies
 */
import ProviderSelector from './provider-selector';
import MessageList from './message-list';
import MessageInput from './message-input';
import ContextIndicator from './context-indicator';

export default function ChatPanel( { compact = false } ) {
	return (
		<div
			className={ `ai-agent-chat-panel ${ compact ? 'is-compact' : '' }` }
		>
			<div className="ai-agent-header">
				<ProviderSelector compact={ compact } />
			</div>
			<ContextIndicator />
			<MessageList />
			<MessageInput compact={ compact } />
		</div>
	);
}
