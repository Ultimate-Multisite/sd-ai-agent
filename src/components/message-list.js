/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { useRef, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ToolCallDetails from './tool-call-details';

/**
 * Lightweight markdown to HTML converter.
 *
 * Handles: headings, bold, italic, inline code, code blocks, links,
 * unordered lists, ordered lists, and paragraphs.
 *
 * @param {string} md Markdown source.
 * @return {string} HTML string.
 */
function markdownToHtml( md ) {
	if ( ! md ) {
		return '';
	}

	// Escape HTML entities first to prevent XSS.
	let text = md
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );

	// Fenced code blocks (``` ... ```)
	text = text.replace(
		/```(\w*)\n([\s\S]*?)```/g,
		( _match, _lang, code ) =>
			`<pre><code>${ code.replace( /\n$/, '' ) }</code></pre>`
	);

	// Process line-by-line for block elements.
	const lines = text.split( '\n' );
	const output = [];
	let inList = false;
	let listType = '';

	for ( let i = 0; i < lines.length; i++ ) {
		let line = lines[ i ];

		// Skip lines inside <pre> blocks (already handled).
		if ( line.includes( '<pre>' ) || line.includes( '</pre>' ) ) {
			if ( inList ) {
				output.push( listType === 'ul' ? '</ul>' : '</ol>' );
				inList = false;
			}
			output.push( line );
			continue;
		}

		// Horizontal rules.
		if ( /^---+$/.test( line.trim() ) ) {
			if ( inList ) {
				output.push( listType === 'ul' ? '</ul>' : '</ol>' );
				inList = false;
			}
			output.push( '<hr />' );
			continue;
		}

		// Headings.
		const headingMatch = line.match( /^(#{1,6})\s+(.+)$/ );
		if ( headingMatch ) {
			if ( inList ) {
				output.push( listType === 'ul' ? '</ul>' : '</ol>' );
				inList = false;
			}
			const level = headingMatch[ 1 ].length;
			output.push( `<h${ level }>${ inlineMarkdown( headingMatch[ 2 ] ) }</h${ level }>` );
			continue;
		}

		// Unordered list items.
		const ulMatch = line.match( /^(\s*)[-*]\s+(.+)$/ );
		if ( ulMatch ) {
			if ( ! inList || listType !== 'ul' ) {
				if ( inList ) {
					output.push( listType === 'ul' ? '</ul>' : '</ol>' );
				}
				output.push( '<ul>' );
				inList = true;
				listType = 'ul';
			}
			output.push( `<li>${ inlineMarkdown( ulMatch[ 2 ] ) }</li>` );
			continue;
		}

		// Ordered list items.
		const olMatch = line.match( /^(\s*)\d+\.\s+(.+)$/ );
		if ( olMatch ) {
			if ( ! inList || listType !== 'ol' ) {
				if ( inList ) {
					output.push( listType === 'ul' ? '</ul>' : '</ol>' );
				}
				output.push( '<ol>' );
				inList = true;
				listType = 'ol';
			}
			output.push( `<li>${ inlineMarkdown( olMatch[ 2 ] ) }</li>` );
			continue;
		}

		// Close list if we hit a non-list line.
		if ( inList ) {
			output.push( listType === 'ul' ? '</ul>' : '</ol>' );
			inList = false;
		}

		// Empty lines become breaks between paragraphs.
		if ( ! line.trim() ) {
			output.push( '' );
			continue;
		}

		// Regular paragraph.
		output.push( `<p>${ inlineMarkdown( line ) }</p>` );
	}

	if ( inList ) {
		output.push( listType === 'ul' ? '</ul>' : '</ol>' );
	}

	return output.join( '\n' );
}

/**
 * Convert inline markdown: bold, italic, code, links.
 */
function inlineMarkdown( text ) {
	return text
		// Inline code.
		.replace( /`([^`]+)`/g, '<code>$1</code>' )
		// Bold + italic.
		.replace( /\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>' )
		// Bold.
		.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' )
		// Italic.
		.replace( /\*(.+?)\*/g, '<em>$1</em>' )
		// Links.
		.replace( /\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>' );
}

function MessageBubble( { role, text } ) {
	const classMap = {
		user: 'ai-agent-bubble ai-agent-user',
		model: 'ai-agent-bubble ai-agent-assistant',
		system: 'ai-agent-bubble ai-agent-system',
	};

	const html = useMemo( () => {
		if ( role === 'model' ) {
			return markdownToHtml( text );
		}
		return null;
	}, [ role, text ] );

	if ( html ) {
		return (
			<div
				className={ classMap[ role ] || classMap.system }
				dangerouslySetInnerHTML={ { __html: html } }
			/>
		);
	}

	return (
		<div className={ classMap[ role ] || classMap.system }>{ text }</div>
	);
}

function extractText( message ) {
	if ( ! message.parts?.length ) {
		return '';
	}
	return message.parts
		.filter( ( p ) => p.text )
		.map( ( p ) => p.text )
		.join( '' );
}

export default function MessageList() {
	const { messages, sending } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			messages: store.getCurrentSessionMessages(),
			sending: store.isSending(),
		};
	}, [] );

	const bottomRef = useRef( null );

	useEffect( () => {
		bottomRef.current?.scrollIntoView( { behavior: 'smooth' } );
	}, [ messages, sending ] );

	const visibleMessages = messages.filter( ( msg ) => {
		// Skip function-role messages (tool responses).
		if ( msg.role === 'function' ) {
			return false;
		}
		// Skip model messages that only have function calls and no text.
		if ( msg.role === 'model' ) {
			const text = extractText( msg );
			if ( ! text ) {
				return false;
			}
		}
		return true;
	} );

	return (
		<div className="ai-agent-messages">
			{ visibleMessages.length === 0 && ! sending && (
				<div className="ai-agent-empty-state">
					{ __( 'Send a message to start a conversation.', 'ai-agent' ) }
				</div>
			) }
			{ visibleMessages.map( ( msg, i ) => {
				const text = extractText( msg );
				if ( ! text ) {
					return null;
				}
				return (
					<div key={ i }>
						{ msg.toolCalls?.length > 0 && (
							<ToolCallDetails toolCalls={ msg.toolCalls } />
						) }
						<MessageBubble role={ msg.role } text={ text } />
					</div>
				);
			} ) }
			{ sending && (
				<div className="ai-agent-bubble ai-agent-assistant ai-agent-thinking">
					<Spinner />
					{ __( 'Thinking...', 'ai-agent' ) }
				</div>
			) }
			<div ref={ bottomRef } />
		</div>
	);
}
