/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { Button, TextareaControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Onboarding interview component — shown after the site scan completes.
 *
 * Presents targeted questions based on the detected site type, collects
 * answers, and saves them as agent memories via the REST API. The interview
 * uses a conversational card-per-question layout: one question is shown at
 * a time, with the user's previous answers displayed above as a summary.
 *
 * @param {Object}   props            - Component props.
 * @param {Function} props.onComplete - Called when the interview is finished or skipped.
 * @return {JSX.Element} The onboarding interview element.
 */
export default function OnboardingInterview( { onComplete } ) {
	const [ questions, setQuestions ] = useState( [] );
	const [ answers, setAnswers ] = useState( {} );
	const [ currentIndex, setCurrentIndex ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const textareaRef = useRef( null );

	// Fetch questions on mount.
	useEffect( () => {
		apiFetch( { path: '/sd-ai-agent/v1/onboarding/interview' } )
			.then( ( data ) => {
				if ( data.done || ! data.ready ) {
					// Interview already done or scan not ready — skip straight through.
					onComplete();
					return;
				}
				setQuestions( data.questions || [] );
				setLoading( false );
			} )
			.catch( () => {
				// On error, skip the interview rather than blocking the user.
				onComplete();
			} );
	}, [ onComplete ] );

	// Focus the textarea when the current question changes.
	useEffect( () => {
		if ( ! loading && textareaRef.current ) {
			textareaRef.current.focus();
		}
	}, [ currentIndex, loading ] );

	const currentQuestion = questions[ currentIndex ] ?? null;
	const isLast = currentIndex === questions.length - 1;
	const isRequired = currentQuestion?.required ?? false;
	const currentAnswer = answers[ currentQuestion?.id ?? '' ] ?? '';
	const canAdvance = ! isRequired || currentAnswer.trim().length > 0;

	/**
	 * Advance to the next question, or submit if on the last question.
	 */
	const handleNext = useCallback( () => {
		if ( isLast ) {
			handleSubmit();
		} else {
			setCurrentIndex( ( i ) => i + 1 );
		}
	}, [ isLast ] ); // eslint-disable-line react-hooks/exhaustive-deps

	/**
	 * Go back to the previous question.
	 */
	const handleBack = useCallback( () => {
		setCurrentIndex( ( i ) => Math.max( 0, i - 1 ) );
	}, [] );

	/**
	 * Submit all answers to the REST API.
	 */
	const handleSubmit = useCallback( async () => {
		setSaving( true );
		setError( null );

		try {
			await apiFetch( {
				path: '/sd-ai-agent/v1/onboarding/interview',
				method: 'POST',
				data: { answers },
			} );
			onComplete();
		} catch ( err ) {
			setSaving( false );
			setError(
				err?.message ||
					__(
						'Failed to save answers. Please try again.',
						'superdav-ai-agent'
					)
			);
		}
	}, [ answers, onComplete ] );

	/**
	 * Skip the interview entirely.
	 */
	const handleSkip = useCallback( async () => {
		setSaving( true );
		try {
			await apiFetch( {
				path: '/sd-ai-agent/v1/onboarding/interview',
				method: 'POST',
				data: { skipped: true },
			} );
		} catch {
			// Non-fatal — proceed regardless.
		}
		onComplete();
	}, [ onComplete ] );

	/**
	 * Handle Enter key to advance (Shift+Enter for newline).
	 *
	 * @param {KeyboardEvent} event
	 */
	const handleKeyDown = useCallback(
		( event ) => {
			if ( event.key === 'Enter' && ! event.shiftKey && canAdvance ) {
				event.preventDefault();
				handleNext();
			}
		},
		[ canAdvance, handleNext ]
	);

	/**
	 * Return the label for the primary action button.
	 *
	 * Extracted to avoid nested ternary expressions (no-nested-ternary rule).
	 *
	 * @param {boolean} isSaving - Whether a save is in progress.
	 * @param {boolean} isLastQ  - Whether this is the last question.
	 * @return {string} Button label.
	 */
	function getNextButtonLabel( isSaving, isLastQ ) {
		if ( isSaving && isLastQ ) {
			return __( 'Saving…', 'superdav-ai-agent' );
		}
		if ( isLastQ ) {
			return __( 'Finish', 'superdav-ai-agent' );
		}
		return __( 'Next', 'superdav-ai-agent' );
	}

	if ( loading ) {
		return (
			<div className="sdaa-interview sdaa-interview--loading">
				<Spinner />
				<p>
					{ __(
						'Preparing your personalised setup…',
						'superdav-ai-agent'
					) }
				</p>
			</div>
		);
	}

	if ( questions.length === 0 ) {
		return null;
	}

	// Answered questions shown as a summary above the current question.
	const answeredSummary = questions
		.slice( 0, currentIndex )
		.filter( ( q ) => answers[ q.id ]?.trim() );

	return (
		<div className="sdaa-interview">
			<div className="sdaa-interview__header">
				<h2>
					{ __( 'Tell us about your site', 'superdav-ai-agent' ) }
				</h2>
				<p className="sdaa-interview__subtitle">
					{ __(
						'Your answers help the AI give you relevant suggestions and automations.',
						'superdav-ai-agent'
					) }
				</p>
				<div className="sdaa-interview__progress">
					{ questions.map( ( _, i ) => (
						<span
							key={ i }
							className={ [
								'sdaa-interview__dot',
								i === currentIndex ? 'is-active' : '',
								i < currentIndex ? 'is-complete' : '',
							]
								.filter( Boolean )
								.join( ' ' ) }
						/>
					) ) }
				</div>
			</div>

			{ /* Previous answers summary */ }
			{ answeredSummary.length > 0 && (
				<div className="sdaa-interview__summary">
					{ answeredSummary.map( ( q ) => (
						<div
							key={ q.id }
							className="sdaa-interview__summary-item"
						>
							<span className="sdaa-interview__summary-q">
								{ q.question }
							</span>
							<span className="sdaa-interview__summary-a">
								{ answers[ q.id ] }
							</span>
						</div>
					) ) }
				</div>
			) }

			{ /* Current question */ }
			<div className="sdaa-interview__question-card">
				<label
					className="sdaa-interview__question-label"
					htmlFor={ `sd-interview-${ currentQuestion.id }` }
				>
					{ currentQuestion.question }
					{ isRequired && (
						<span
							className="sdaa-interview__required"
							aria-label={ __( 'required', 'superdav-ai-agent' ) }
						>
							{ ' *' }
						</span>
					) }
				</label>
				<TextareaControl
					id={ `sd-interview-${ currentQuestion.id }` }
					value={ currentAnswer }
					placeholder={ currentQuestion.placeholder }
					onChange={ ( value ) =>
						setAnswers( ( prev ) => ( {
							...prev,
							[ currentQuestion.id ]: value,
						} ) )
					}
					onKeyDown={ handleKeyDown }
					rows={ 3 }
					ref={ textareaRef }
					__nextHasNoMarginBottom
				/>
				{ ! isRequired && (
					<p className="sdaa-interview__optional-hint">
						{ __(
							'Optional — press Enter or click Next to skip.',
							'superdav-ai-agent'
						) }
					</p>
				) }
			</div>

			{ error && <p className="sdaa-interview__error">{ error }</p> }

			<div className="sdaa-interview__footer">
				{ currentIndex > 0 && (
					<Button
						variant="tertiary"
						onClick={ handleBack }
						disabled={ saving }
					>
						{ __( 'Back', 'superdav-ai-agent' ) }
					</Button>
				) }

				<Button
					variant="link"
					onClick={ handleSkip }
					disabled={ saving }
					className="sdaa-interview__skip"
				>
					{ __( 'Skip all', 'superdav-ai-agent' ) }
				</Button>

				<Button
					variant="primary"
					onClick={ handleNext }
					disabled={ saving || ! canAdvance }
					isBusy={ saving && isLast }
				>
					{ getNextButtonLabel( saving, isLast ) }
				</Button>
			</div>
		</div>
	);
}
