
/**
 * WordPress Dependencies.
 */
const { Component } = wp.element,
	{ __ } = wp.i18n,
	{
		CheckboxControl,
		Button,
		TextControl,
	} = wp.components;

/**
 * UI for editing the shortcode attributes.
 */
class AttributesEdit extends Component {
	renderTextFilters() {
		const { attributes, setAttributes } = this.props;

		return (
			<div className="attributes-edit__text-filters">
				<TextControl
					label={ __( 'Keywords' ) }
					help={ __( 'Filter the results by keyword' ) }
					value={ attributes.keywords }
					onChange={ ( keywords ) => setAttributes( { keywords } ) }
				/>
				<TextControl
					label={ __( 'Location' ) }
					help={ __( 'Filter the results by location' ) }
					value={ attributes.location }
					onChange={ ( location ) => setAttributes( { location } ) }
				/>
			</div>
		);
	}

	render() {
		const { className, attributes, setAttributes } = this.props;

		return (
			<div className={ className }>
				<div className="attributes-edit">
					<h2>{ __( 'Edit Settings' ) }</h2>
					<CheckboxControl
						heading={ __( 'Show filters' ) }
						label={ __( 'Show search filters on the frontend' ) }
						checked={ attributes.showFilters }
						onChange={ ( showFilters ) => setAttributes( { showFilters } ) }
					/>
					{ ! attributes.showFilters && this.renderTextFilters() }
					<div className="attributes-edit__save">
						<Button
							onClick={ () => console.log( 'Clicked!' ) }
						/>
					</div>
				</div>
			</div>
		);
	}
}

export default AttributesEdit;
