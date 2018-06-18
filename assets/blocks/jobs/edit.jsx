
/**
 * Internal Dependencies.
 */
import Sidebar from './sidebar.jsx';

/**
 * WordPress Dependencies.
 */
const { Component } = wp.element,
	{ InspectorControls } = wp.editor,
	{ __ } = wp.i18n,
	{ ServerSideRender } = wp.components;

/**
 * Edit UI for Jobs block.
 */
class JobsEdit extends Component {
	render() {
		const { className, attributes, setAttributes } = this.props;

		return [
			<div className={ className }>
				<ServerSideRender
					block='wp-job-manager/jobs'
					attributes={ attributes }
				/>
			</div>,
			<InspectorControls key="inspector">
				<Sidebar
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			</InspectorControls>,
		];
	}
}

export default JobsEdit;
