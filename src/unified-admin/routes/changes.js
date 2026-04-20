/**
 * Internal dependencies
 */
import ChangesApp from '../../changes-page/changes-app';
import '../../changes-page/style.css';

/**
 * Changes Route Component
 *
 * Renders the full ChangesApp inside the unified admin layout.
 *
 * @return {JSX.Element} Changes route element.
 */
export default function ChangesRoute() {
	return (
		<div className="gratis-ai-agent-route gratis-ai-agent-route-changes">
			<ChangesApp />
		</div>
	);
}
