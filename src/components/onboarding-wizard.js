/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Button, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';
import ProviderSelector from './provider-selector';

export default function OnboardingWizard( { onComplete } ) {
	const [ step, setStep ] = useState( 0 );
	const [ abilities, setAbilities ] = useState( [] );
	const [ disabledAbilities, setDisabledAbilities ] = useState( [] );
	const { saveSettings } = useDispatch( STORE_NAME );
	const { providers, selectedProviderId, selectedModelId } = useSelect(
		( select ) => ( {
			providers: select( STORE_NAME ).getProviders(),
			selectedProviderId: select( STORE_NAME ).getSelectedProviderId(),
			selectedModelId: select( STORE_NAME ).getSelectedModelId(),
		} ),
		[]
	);

	useEffect( () => {
		apiFetch( { path: '/ai-agent/v1/abilities' } )
			.then( setAbilities )
			.catch( () => {} );
	}, [] );

	const handleFinish = useCallback( async () => {
		await saveSettings( {
			onboarding_complete: true,
			default_provider: selectedProviderId,
			default_model: selectedModelId,
			disabled_abilities: disabledAbilities,
		} );
		onComplete();
	}, [
		saveSettings,
		selectedProviderId,
		selectedModelId,
		disabledAbilities,
		onComplete,
	] );

	const steps = [
		// Step 0: Welcome
		{
			title: __( 'Welcome to AI Agent', 'ai-agent' ),
			content: (
				<div className="ai-agent-wizard-welcome">
					<p>
						{ __(
							'AI Agent is an intelligent assistant that can interact with your WordPress site using registered abilities (tools).',
							'ai-agent'
						) }
					</p>
					<p>
						{ __(
							"It can manage content, query data, run commands, and more — all through a natural chat interface. Let's get set up!",
							'ai-agent'
						) }
					</p>
				</div>
			),
		},
		// Step 1: Provider
		{
			title: __( 'Choose AI Provider', 'ai-agent' ),
			content: (
				<div className="ai-agent-wizard-provider">
					{ providers.length === 0 ? (
						<div>
							<p>
								{ __(
									'No AI providers are configured yet.',
									'ai-agent'
								) }
							</p>
							<p>
								{ __(
									'Go to Settings > AI to configure a provider, then come back here.',
									'ai-agent'
								) }
							</p>
						</div>
					) : (
						<>
							<p>
								{ __(
									'Select which AI provider and model to use by default.',
									'ai-agent'
								) }
							</p>
							<ProviderSelector />
						</>
					) }
				</div>
			),
		},
		// Step 2: Abilities
		{
			title: __( 'Configure Abilities', 'ai-agent' ),
			content: (
				<div className="ai-agent-wizard-abilities">
					<p>
						{ __(
							'Choose which abilities the AI agent can use. You can change these later in settings.',
							'ai-agent'
						) }
					</p>
					{ abilities.length === 0 && (
						<p className="description">
							{ __(
								'No abilities registered yet. They will appear once plugins register them.',
								'ai-agent'
							) }
						</p>
					) }
					{ abilities.map( ( ability ) => {
						const disabled = disabledAbilities.includes(
							ability.name
						);
						return (
							<ToggleControl
								key={ ability.name }
								label={ ability.label || ability.name }
								help={ ability.description || '' }
								checked={ ! disabled }
								onChange={ ( enabled ) => {
									if ( enabled ) {
										setDisabledAbilities( ( prev ) =>
											prev.filter(
												( n ) => n !== ability.name
											)
										);
									} else {
										setDisabledAbilities( ( prev ) => [
											...prev,
											ability.name,
										] );
									}
								} }
								__nextHasNoMarginBottom
							/>
						);
					} ) }
				</div>
			),
		},
		// Step 3: Done
		{
			title: __( 'All Set!', 'ai-agent' ),
			content: (
				<div className="ai-agent-wizard-done">
					<p>
						{ __(
							"You're all set! The AI Agent is ready to help you manage your WordPress site.",
							'ai-agent'
						) }
					</p>
					<p>
						{ __(
							'You can access it from the floating chat bubble on any admin page, or from the full-page chat under Tools > AI Agent.',
							'ai-agent'
						) }
					</p>
				</div>
			),
		},
	];

	const current = steps[ step ];
	const isLast = step === steps.length - 1;

	return (
		<div className="ai-agent-wizard">
			<div className="ai-agent-wizard-header">
				<h2>{ current.title }</h2>
				<div className="ai-agent-wizard-progress">
					{ steps.map( ( _, i ) => (
						<span
							key={ i }
							className={ `ai-agent-wizard-dot ${
								i === step ? 'is-active' : ''
							} ${ i < step ? 'is-complete' : '' }` }
						/>
					) ) }
				</div>
			</div>
			<div className="ai-agent-wizard-body">{ current.content }</div>
			<div className="ai-agent-wizard-footer">
				{ step > 0 && (
					<Button
						variant="tertiary"
						onClick={ () => setStep( step - 1 ) }
					>
						{ __( 'Back', 'ai-agent' ) }
					</Button>
				) }
				<Button
					variant="link"
					onClick={ handleFinish }
					className="ai-agent-wizard-skip"
				>
					{ __( 'Skip', 'ai-agent' ) }
				</Button>
				{ isLast ? (
					<Button variant="primary" onClick={ handleFinish }>
						{ __( 'Start Chatting', 'ai-agent' ) }
					</Button>
				) : (
					<Button
						variant="primary"
						onClick={ () => setStep( step + 1 ) }
					>
						{ __( 'Next', 'ai-agent' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
