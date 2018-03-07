/**
 * WordPress Dependencies
 */
const { __ } = wp.i18n;
const {
	registerBlockType,
	InspectorControls,
	RichText,
} = wp.blocks;
const {
	withState,
	PanelBody,
	TextControl,
	ToggleControl,
} = wp.components;

/**
 * Internal dependencies
 */
import './editor.scss';
import './style.scss';
import BriefcaseIcon from './briefcase-icon';

registerBlockType( 'wpjm/job-listing', {
	title: __( 'Job Listing' ),
	category: 'widgets',
	description: __( 'Shows a job listing.' ),
	icon: BriefcaseIcon,
	attributes: {
		alt: {
			type: 'string',
			source: 'attribute',
			selector: 'img',
			attribute: 'alt',
		},
		id: {
			type: 'number',
		},
		description: {
			type: 'array',
			source: 'children',
			selector: '.description',
		},
		url: {
			type: 'string',
			source: 'attribute',
			selector: 'img',
			attribute: 'src',
		},
	},
	edit: withState( {
		editable: 'location',
	} )( ( { attributes, className, editable, isSelected, setAttributes, setState } ) => {
		const {
			alt,
			application,
			company,
			description,
			expiryDate,
			featuredListing,
			id,
			location,
			positionFilled,
			tagline,
			twitter,
			url,
		} = attributes;

		const onSetActiveEditable = newEditable => () => setState( { editable: newEditable } );
		const updateLogo = ( { alt, id, url } ) => setAttributes( { alt, id, url } );
		const updateToggle = field => () => setAttributes( { [ field ]: ! attributes[ field ] } );
		const updateValue = field => value => setAttributes( { [ field ]: value } );

		return (
			<div className={ className }>
				{ isSelected && (
					<InspectorControls key="inspector">
						<PanelBody title={ __( 'Job Listing Settings' ) }>
							<ToggleControl
								checked={ positionFilled }
								label={ __( 'Position Filled' ) }
								onChange={ updateToggle( 'positionFilled' ) } />

							<ToggleControl
								checked={ featuredListing }
								label={ __( 'Featured Listing' ) }
								onChange={ updateToggle( 'featuredListing' ) } />

							<TextControl
								label={ __( 'Application Email or URL' ) }
								onChange={ updateValue( 'application' ) }
								value={ application } />

							<TextControl
								label={ __( 'Expiry Date' ) }
								onChange={ updateValue( 'expiryDate' ) }
								value={ expiryDate } />
						</PanelBody>
					</InspectorControls>
				) }

				<div className="job-listing__details">
					{ /* TODO: Show when at least one job type is selected. */ }
					{ false && (
						<ul className="job-listing__type-list">
							{ /* TODO: Dynamically add list item when job type is selected. */ }
							<li className="job-listing__type is-full-time">
								{ __( 'Full Time' ) }
							</li>
							<li className="job-listing__type is-freelance">
								{ __( 'Freelance' ) }
							</li>
						</ul>
					) }

					<div className="job-listing__meta">
						<RichText
							isSelected={ isSelected && editable === 'location' }
							onChange={ updateValue( 'location' ) }
							onFocus={ onSetActiveEditable( 'location' ) }
							placeholder={ __( 'Enter job location…' ) }
							tagName="span"
							wrapperClassName="job-listing__location"
							value={ location }
							keepPlaceholderOnFocus />

						{ /* TODO: Show once job is saved. */ }
						{ false && (
							<span className="job-listing__date-posted">
								<time dateTime="2017-08-31">
									{ __( 'Posted 1 minute ago' ) }
								</time>
							</span>
						) }
					</div>

					<div className="job-listing__company-details">
						<RichText
							isSelected={ isSelected && editable === 'company' }
							onChange={ updateValue( 'company' ) }
							onFocus={ onSetActiveEditable( 'company' ) }
							placeholder={ __( 'Enter company name…' ) }
							tagName="span"
							value={ company }
							keepPlaceholderOnFocus />
						<RichText
							isSelected={ isSelected && editable === 'tagline' }
							onChange={ updateValue( 'tagline' ) }
							onFocus={ onSetActiveEditable( 'tagline' ) }
							placeholder={ __( 'Enter company tagline…' ) }
							tagName="span"
							value={ tagline }
							keepPlaceholderOnFocus />
						<RichText
							isSelected={ isSelected && editable === 'twitter' }
							onChange={ updateValue( 'twitter' ) }
							onFocus={ onSetActiveEditable( 'twitter' ) }
							placeholder={ __( 'Enter company Twitter account…' ) }
							tagName="span"
							value={ twitter }
							wrapperClassName="job-listing__twitter"
							keepPlaceholderOnFocus />
					</div>

					<RichText
						isSelected={ isSelected && editable === 'description' }
						onChange={ updateValue( 'description' ) }
						onFocus={ onSetActiveEditable( 'description' ) }
						placeholder={ __( 'Write job description…' ) }
						tagName="p"
						value={ description }
						wrapperClassName="job-listing__description"
						inlineToolbar
						keepPlaceholderOnFocus />

					{ !! application && (
						<div className="job-listing__application-wrapper">
							<input
								className="job-listing__application"
								type="button"
								value={ __( 'Apply for job' ) } />
						</div>
					) }
				</div>
			</div>
		);
	} ),
	save: () => {
		// TODO
		return null;
	}
} );
