/**
 * WordPress dependencies
 */
import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Feedback consent modal.
 *
 * Shown when the user invokes /report-issue or other feedback triggers.
 * Lets the user review and optionally edit a description before sending a
 * report to the configured feedback endpoint via the server-side proxy.
 *
 * Extensibility note: this component is intentionally minimal for the
 * initial implementation. The payload preview, environment summary, and
 * "Strip tool results" checkbox described in t182 can be added as additional
 * sections in the modal body without changing the props interface.
 *
 * @param {Object}   props                      - Component props.
 * @param {string}   props.reportType           - Type of report sent in the
 *                                              payload: 'user_reported',
 *                                              'thumbs_down', 'self_reported', etc.
 * @param {string}   [props.userDescription=''] - Pre-filled description text.
 *                                              Editable by the user before sending.
 * @param {Function} props.onClose              - Called when the modal should close.
 * @return {JSX.Element} The feedback consent modal element.
 */
export default function FeedbackConsentModal( {
	reportType,
	userDescription = '',
	onClose,
} ) {
	const [ description, setDescription ] = useState( userDescription );
	const [ isSending, setIsSending ] = useState( false );
	const [ isSent, setIsSent ] = useState( false );
	const [ error, setError ] = useState( null );
	const dialogRef = useRef( null );

	// Close on Escape key.
	useEffect( () => {
		const handler = ( e ) => {
			if ( e.key === 'Escape' ) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', handler );
		return () => document.removeEventListener( 'keydown', handler );
	}, [ onClose ] );

	// Close on click outside the dialog box.
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

	const handleSend = useCallback( async () => {
		setIsSending( true );
		setError( null );
		try {
			await apiFetch( {
				path: '/gratis-ai-agent/v1/feedback/send',
				method: 'POST',
				data: {
					report_type: reportType,
					user_description: description,
				},
			} );
			setIsSent( true );
			// Auto-close after a short confirmation delay.
			setTimeout( onClose, 1500 );
		} catch {
			setError(
				__(
					'Failed to send report. Please try again.',
					'gratis-ai-agent'
				)
			);
			setIsSending( false );
		}
	}, [ reportType, description, onClose ] );

	return (
		<div className="gratis-ai-agent-shortcuts-overlay">
			<div
				className="gratis-ai-agent-feedback-modal"
				ref={ dialogRef }
				role="dialog"
				aria-modal="true"
				aria-labelledby="gratis-ai-agent-feedback-title"
			>
				<div className="gratis-ai-agent-feedback-modal__header">
					<h3 id="gratis-ai-agent-feedback-title">
						{ __( 'Send Feedback Report', 'gratis-ai-agent' ) }
					</h3>
					<button
						type="button"
						className="gratis-ai-agent-feedback-modal__close"
						onClick={ onClose }
						aria-label={ __( 'Close', 'gratis-ai-agent' ) }
					>
						&times;
					</button>
				</div>
				<div className="gratis-ai-agent-feedback-modal__body">
					{ isSent ? (
						<p className="gratis-ai-agent-feedback-modal__success">
							{ __(
								'Report sent. Thank you!',
								'gratis-ai-agent'
							) }
						</p>
					) : (
						<>
							<p className="gratis-ai-agent-feedback-modal__notice">
								{ __(
									'No passwords, API keys, or credentials are included. Server paths are anonymized.',
									'gratis-ai-agent'
								) }
							</p>
							<label
								htmlFor="gratis-ai-agent-feedback-description"
								className="gratis-ai-agent-feedback-modal__label"
							>
								{ __(
									'Describe the issue (optional):',
									'gratis-ai-agent'
								) }
							</label>
							<textarea
								id="gratis-ai-agent-feedback-description"
								className="gratis-ai-agent-feedback-modal__textarea"
								value={ description }
								onChange={ ( e ) =>
									setDescription( e.target.value )
								}
								rows={ 4 }
								placeholder={ __(
									'What went wrong?',
									'gratis-ai-agent'
								) }
							/>
							{ error && (
								<p className="gratis-ai-agent-feedback-modal__error">
									{ error }
								</p>
							) }
						</>
					) }
				</div>
				{ ! isSent && (
					<div className="gratis-ai-agent-feedback-modal__footer">
						<button
							type="button"
							className="button"
							onClick={ onClose }
							disabled={ isSending }
						>
							{ __( 'Dismiss', 'gratis-ai-agent' ) }
						</button>
						<button
							type="button"
							className="button button-primary"
							onClick={ handleSend }
							disabled={ isSending }
						>
							{ isSending
								? __( 'Sending\u2026', 'gratis-ai-agent' )
								: __( 'Send Report', 'gratis-ai-agent' ) }
						</button>
					</div>
				) }
			</div>
		</div>
	);
}
