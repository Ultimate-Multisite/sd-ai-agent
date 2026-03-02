/**
 * WordPress dependencies
 */
import { useState, useRef, useCallback, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Icon, arrowUp } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

export default function MessageInput( { compact = false } ) {
	const [ text, setText ] = useState( '' );
	const textareaRef = useRef( null );
	const sending = useSelect(
		( select ) => select( STORE_NAME ).isSending(),
		[]
	);
	const { sendMessage } = useDispatch( STORE_NAME );

	// Auto-resize textarea to fit content.
	useEffect( () => {
		const el = textareaRef.current;
		if ( ! el ) {
			return;
		}
		el.style.height = 'auto';
		el.style.height = Math.min( el.scrollHeight, compact ? 120 : 200 ) + 'px';
	}, [ text, compact ] );

	const handleSend = useCallback( () => {
		const trimmed = text.trim();
		if ( ! trimmed || sending ) {
			return;
		}
		sendMessage( trimmed );
		setText( '' );
		// Re-focus after send.
		setTimeout( () => textareaRef.current?.focus(), 0 );
	}, [ text, sending, sendMessage ] );

	const handleKeyDown = useCallback(
		( e ) => {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				handleSend();
			}
		},
		[ handleSend ]
	);

	return (
		<div
			className={ `ai-agent-input-area ${
				compact ? 'is-compact' : ''
			}` }
		>
			<textarea
				ref={ textareaRef }
				className="ai-agent-input"
				rows={ 1 }
				placeholder={ __( 'Type a message...', 'ai-agent' ) }
				value={ text }
				onChange={ ( e ) => setText( e.target.value ) }
				onKeyDown={ handleKeyDown }
				disabled={ sending }
			/>
			<Button
				variant="primary"
				onClick={ handleSend }
				disabled={ sending || ! text.trim() }
				className="ai-agent-send-btn"
				label={ __( 'Send', 'ai-agent' ) }
				icon={ <Icon icon={ arrowUp } /> }
			/>
		</div>
	);
}
