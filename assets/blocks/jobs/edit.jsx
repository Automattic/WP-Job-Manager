
/**
 * WordPress Dependencies.
 */
const { Component } = wp.element,
	{ InspectorControls } = wp.editor,
	{ __ } = wp.i18n,
	{ TextControl, ToggleControl } = wp.components;

/**
 * Internal Dependencies.
 */
import Sidebar from './sidebar.jsx';
import Types from './types.jsx';
import JobPlaceholderList from './job-placeholder-list.jsx';

/**
 * Edit UI for Jobs block.
 */
class JobsEdit extends Component {
	renderShowFiltersControl() {
		const { className, attributes, setAttributes } = this.props;

		return (
			<div className={ `${className}__show-filters` }>
				<ToggleControl
					label={ __( 'Show filters on the frontend?' ) }
					checked={ attributes.showFilters }
					onChange={ ( showFilters ) => setAttributes( { showFilters } ) }
				/>
			</div>
		);
	}

	renderFilters() {
		const { className, attributes, setAttributes, isSelected } = this.props;

		return (
			<div>
				<div className={ `${className}__search-boxes` }>
					<TextControl
						className={ `${className}__keywords` }
						placeholder={ __( 'Keywords' ) }
						help={ isSelected ? __( 'Default keyword search' ) : '' }
						value={ attributes.keywords }
						onChange={ ( keywords ) => setAttributes( { keywords } ) }
					/>
					<TextControl
						className={ `${className}__location` }
						placeholder={ __( 'Location' ) }
						help={ isSelected ? __( 'Default location search' ) : '' }
						value={ attributes.location }
						onChange={ ( location ) => setAttributes( { location } ) }
					/>
					<div className="clearfix"/>
				</div>
				<Types
					className={ `${className}__types` }
					attributes={ attributes }
					setAttributes={ setAttributes }
					isSelected={ isSelected }
				/>
			</div>
		);
	}

	render() {
		const { attributes, setAttributes, className, isSelected } = this.props;

		return [
			(
				<div className={ className }>
					{ isSelected && this.renderShowFiltersControl() }
					{ this.renderFilters() }
					<JobPlaceholderList number={ 3 } />
				</div>
			),
			(
				<InspectorControls key="inspector">
					<Sidebar
						attributes={ attributes }
						setAttributes={ setAttributes }
					/>
				</InspectorControls>
			),
		];
	}
}

export default JobsEdit;
