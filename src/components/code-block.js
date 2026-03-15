/**
 * WordPress dependencies
 */
import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 *
 * Use highlight.js core (tree-shakeable) with explicit language registration
 * to avoid bundling all ~190 hljs grammars. Only the most common languages
 * used in AI chat responses are registered here.
 */
import hljs from 'highlight.js/lib/core';
import 'highlight.js/styles/atom-one-dark.css';

// Register only the languages we need (keeps bundle lean).
import bash from 'highlight.js/lib/languages/bash';
import css from 'highlight.js/lib/languages/css';
import diff from 'highlight.js/lib/languages/diff';
import dockerfile from 'highlight.js/lib/languages/dockerfile';
import graphql from 'highlight.js/lib/languages/graphql';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import markdown from 'highlight.js/lib/languages/markdown';
import php from 'highlight.js/lib/languages/php';
import python from 'highlight.js/lib/languages/python';
import ruby from 'highlight.js/lib/languages/ruby';
import rust from 'highlight.js/lib/languages/rust';
import scss from 'highlight.js/lib/languages/scss';
import shell from 'highlight.js/lib/languages/shell';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import yaml from 'highlight.js/lib/languages/yaml';

hljs.registerLanguage( 'bash', bash );
hljs.registerLanguage( 'css', css );
hljs.registerLanguage( 'diff', diff );
hljs.registerLanguage( 'docker', dockerfile );
hljs.registerLanguage( 'dockerfile', dockerfile );
hljs.registerLanguage( 'graphql', graphql );
hljs.registerLanguage( 'javascript', javascript );
hljs.registerLanguage( 'js', javascript );
hljs.registerLanguage( 'json', json );
hljs.registerLanguage( 'jsx', javascript );
hljs.registerLanguage( 'markdown', markdown );
hljs.registerLanguage( 'md', markdown );
hljs.registerLanguage( 'php', php );
hljs.registerLanguage( 'python', python );
hljs.registerLanguage( 'py', python );
hljs.registerLanguage( 'ruby', ruby );
hljs.registerLanguage( 'rb', ruby );
hljs.registerLanguage( 'rust', rust );
hljs.registerLanguage( 'scss', scss );
hljs.registerLanguage( 'shell', shell );
hljs.registerLanguage( 'sh', bash );
hljs.registerLanguage( 'sql', sql );
hljs.registerLanguage( 'tsx', typescript );
hljs.registerLanguage( 'typescript', typescript );
hljs.registerLanguage( 'ts', typescript );
hljs.registerLanguage( 'xml', xml );
hljs.registerLanguage( 'yaml', yaml );
hljs.registerLanguage( 'yml', yaml );

/**
 * Normalise language aliases to the registered name.
 *
 * @param {string|undefined} lang Raw language identifier from the fenced code block.
 * @return {string|null} Normalised language name registered with hljs, or null if unknown.
 */
function normaliseLanguage( lang ) {
	if ( ! lang ) {
		return null;
	}
	const aliases = {
		js: 'javascript',
		ts: 'typescript',
		tsx: 'typescript',
		jsx: 'javascript',
		py: 'python',
		rb: 'ruby',
		sh: 'bash',
		yml: 'yaml',
		md: 'markdown',
		docker: 'dockerfile',
	};
	const normalised = aliases[ lang.toLowerCase() ] ?? lang.toLowerCase();
	return hljs.getLanguage( normalised ) ? normalised : null;
}

export default function CodeBlock( { language, children } ) {
	const [ copied, setCopied ] = useState( false );
	const [ showLineNumbers, setShowLineNumbers ] = useState( false );
	const [ wrapLines, setWrapLines ] = useState( false );
	const codeRef = useRef( null );
	const code = String( children ).replace( /\n$/, '' );
	const normalisedLang = normaliseLanguage( language );

	useEffect( () => {
		if ( codeRef.current ) {
			// Reset the data-highlighted attribute so hljs re-highlights on
			// content or language changes without duplicating spans.
			codeRef.current.removeAttribute( 'data-highlighted' );
			hljs.highlightElement( codeRef.current );
		}
	}, [ code, normalisedLang ] );

	const handleCopy = useCallback( () => {
		navigator.clipboard.writeText( code ).then( () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		} );
	}, [ code ] );

	const codeClassName = normalisedLang
		? `language-${ normalisedLang }`
		: 'language-plaintext';

	return (
		<div className="ai-agent-code-block">
			<div className="ai-agent-code-header">
				{ language && (
					<span className="ai-agent-code-language">{ language }</span>
				) }
				<div className="ai-agent-code-header-actions">
					<button
						className={ `ai-agent-code-toggle${
							showLineNumbers ? ' is-active' : ''
						}` }
						onClick={ () => setShowLineNumbers( ( v ) => ! v ) }
						type="button"
						aria-pressed={ showLineNumbers }
						title={ __( 'Toggle line numbers', 'ai-agent' ) }
					>
						{ __( '#', 'ai-agent' ) }
					</button>
					<button
						className={ `ai-agent-code-toggle${
							wrapLines ? ' is-active' : ''
						}` }
						onClick={ () => setWrapLines( ( v ) => ! v ) }
						type="button"
						aria-pressed={ wrapLines }
						title={ __( 'Toggle word wrap', 'ai-agent' ) }
					>
						{ __( '↵', 'ai-agent' ) }
					</button>
					<button
						className="ai-agent-code-copy"
						onClick={ handleCopy }
						type="button"
					>
						{ copied
							? __( 'Copied!', 'ai-agent' )
							: __( 'Copy', 'ai-agent' ) }
					</button>
				</div>
			</div>
			<pre
				className={ `ai-agent-code-pre${
					showLineNumbers ? ' show-line-numbers' : ''
				}${ wrapLines ? ' wrap-lines' : '' }` }
			>
				<code ref={ codeRef } className={ codeClassName }>
					{ code }
				</code>
			</pre>
		</div>
	);
}
