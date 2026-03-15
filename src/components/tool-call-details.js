/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

export default function ToolCallDetails( { toolCalls } ) {
	if ( ! toolCalls?.length ) {
		return null;
	}

	return (
		<div className="ai-agent-tool-calls">
			<details>
				<summary>
					{ toolCalls.length }{ ' ' }
					{ toolCalls.length === 1
						? __( 'tool call executed', 'ai-agent' )
						: __( 'tool calls executed', 'ai-agent' ) }
				</summary>
				<div className="ai-agent-tool-list">
					{ toolCalls.map( ( entry, i ) => (
						<div
							key={ i }
							className={ `ai-agent-tool-entry ai-agent-tool-${ entry.type }` }
						>
							{ entry.type === 'call' ? (
								<>
									<span className="ai-agent-tool-label">
										{ __( 'Call:', 'ai-agent' ) }
									</span>{ ' ' }
									<code>{ entry.name }</code>
									<pre>
										{ JSON.stringify(
											entry.args,
											null,
											2
										) }
									</pre>
								</>
							) : (
								<>
									<span className="ai-agent-tool-label">
										{ __( 'Result:', 'ai-agent' ) }
									</span>{ ' ' }
									<code>{ entry.name }</code>
									<pre>
										{ truncate(
											typeof entry.response === 'string'
												? entry.response
												: JSON.stringify(
														entry.response,
														null,
														2
												  ),
											500
										) }
									</pre>
								</>
							) }
						</div>
					) ) }
				</div>
			</details>
		</div>
	);
}

function truncate( str, max ) {
	if ( ! str ) {
		return '';
	}
	return str.length > max ? str.substring( 0, max ) + '...' : str;
}
