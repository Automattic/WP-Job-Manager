
/**
 * Internal Dependencies.
 */
import Sidebar from './sidebar.jsx';
import AttributesEdit from './attributes-edit.jsx';

/**
 * WordPress Dependencies.
 */
const { Component } = wp.element,
	{ InspectorControls, BlockControls } = wp.editor,
	{ ServerSideRender, Toolbar } = wp.components;

/**
 * Edit UI for Jobs block.
 */
class JobsEdit extends Component {
	state = {
		editing: false,
	}

	render() {
		const { className, attributes, setAttributes } = this.props;
		const { editing } = this.state;

		return [
			<div className={ className } key="edit-ui">
				{ editing ?
					<AttributesEdit /> :
					<ServerSideRender
						block="wp-job-manager/jobs"
						attributes={ attributes }
					/>
				}
			</div>,
			<InspectorControls key="inspector">
				<Sidebar
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			</InspectorControls>,
			<BlockControls key="block">
				<Toolbar controls={ [
					{
						icon: 'edit',
						title: 'Edit',
						isActive: editing,
						onClick: () => this.setState( {
							editing: ! editing,
						} ),
					},
				] } />
			</BlockControls>,
		];
	}
}

export default JobsEdit;
