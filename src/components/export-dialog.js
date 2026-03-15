/**
 * WordPress dependencies
 */
import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

export default function ExportDialog( { sessionId, onClose } ) {
	const [ format, setFormat ] = useState( 'json' );
	const { exportSession } = useDispatch( STORE_NAME );
	const dialogRef = useRef( null );

	useEffect( () => {
		const handler = ( e ) => {
			if ( e.key === 'Escape' ) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', handler );
		return () => document.removeEventListener( 'keydown', handler );
	}, [ onClose ] );

	useEffect( () => {
		const handler = ( e ) => {
			if (
				dialogRef.current &&
				! dialogRef.current.contains( e.target )
			) {
				onClose();
			}
		};
		document.addEventListener( 'mousedown', handler );
		return () => document.removeEventListener( 'mousedown', handler );
	}, [ onClose ] );

	const handleExport = useCallback( () => {
		exportSession( sessionId, format );
		onClose();
	}, [ sessionId, format, exportSession, onClose ] );

	return (
		<div className="ai-agent-shortcuts-overlay">
			<div className="ai-agent-export-dialog" ref={ dialogRef }>
				<div className="ai-agent-export-header">
					<h3>{ __( 'Export Conversation', 'ai-agent' ) }</h3>
					<button type="button" onClick={ onClose }>
						&times;
					</button>
				</div>
				<div className="ai-agent-export-body">
					<label
						className="ai-agent-export-option"
						htmlFor="export-format-json"
						aria-label={ __( 'Export as JSON', 'ai-agent' ) }
					>
						<input
							id="export-format-json"
							type="radio"
							name="format"
							value="json"
							checked={ format === 'json' }
							onChange={ () => setFormat( 'json' ) }
						/>
						<div>
							<strong>JSON</strong>
							<p>
								{ __(
									'Full conversation data. Can be imported back.',
									'ai-agent'
								) }
							</p>
						</div>
					</label>
					<label
						className="ai-agent-export-option"
						htmlFor="export-format-markdown"
						aria-label={ __( 'Export as Markdown', 'ai-agent' ) }
					>
						<input
							id="export-format-markdown"
							type="radio"
							name="format"
							value="markdown"
							checked={ format === 'markdown' }
							onChange={ () => setFormat( 'markdown' ) }
						/>
						<div>
							<strong>Markdown</strong>
							<p>
								{ __(
									'Human-readable format. Good for sharing.',
									'ai-agent'
								) }
							</p>
						</div>
					</label>
				</div>
				<div className="ai-agent-export-footer">
					<button
						type="button"
						className="button"
						onClick={ onClose }
					>
						{ __( 'Cancel', 'ai-agent' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
						onClick={ handleExport }
					>
						{ __( 'Download', 'ai-agent' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
