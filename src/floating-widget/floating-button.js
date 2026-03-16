/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, comment } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import STORE_NAME from '../store';

/**
 * Floating action button that opens the AI Agent chat panel.
 * Displays a notification badge when there are proactive alerts.
 *
 * Rendered in the bottom-right corner when the floating panel is closed.
 *
 * @return {JSX.Element} The floating action button element.
 */
export default function FloatingButton() {
	const { setFloatingOpen } = useDispatch( STORE_NAME );
	const alertCount = useSelect(
		( select ) => select( STORE_NAME ).getAlertCount(),
		[]
	);

	return (
		<Button
			className="gratis-ai-agent-fab"
			onClick={ () => setFloatingOpen( true ) }
			label={ __( 'Open Gratis AI Agent', 'gratis-ai-agent' ) }
		>
			<Icon icon={ comment } size={ 24 } />
			{ alertCount > 0 && (
				<span
					className="gratis-ai-agent-fab-badge"
					aria-label={ sprintf(
						/* translators: %d: number of alerts */
						__( '%d alert(s)', 'gratis-ai-agent' ),
						alertCount
					) }
				>
					{ alertCount > 9 ? '9+' : alertCount }
				</span>
			) }
		</Button>
	);
}
