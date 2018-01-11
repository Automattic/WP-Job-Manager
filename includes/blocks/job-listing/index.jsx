/**
 * WordPress Dependencies
 */
const { __ } = wp.i18n;
const {
	registerBlockType,
	BlockControls,
	BlockDescription,
	Editable,
	InspectorControls,
	MediaUploadButton,
} = wp.blocks;
const {
	Dashicon,
	FormFileUpload,
	PanelBody,
	Placeholder,
	Toolbar,
} = wp.components;
const { mediaUpload } = wp.utils;
const {
	TextControl,
	ToggleControl,
} = InspectorControls;

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
	edit: ( { attributes, className, focus, setAttributes, setFocus } ) => {
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
		const focusedEditable = focus ? focus.editable || 'location' : null;
		const uploadButtonProps = { isLarge: true };
		const updateFocus = field => focusValue => setFocus( { editable: field, ...focusValue } );
		const updateLogo = ( { alt, id, url } ) => setAttributes( { alt, id, url } );
		const updateToggle = field => () => setAttributes( { [ field ]: ! attributes[ field ] } );
		const updateValue = field => value => setAttributes( { [ field ]: value } );
		const uploadFromFiles = event => mediaUpload( event.target.files, setAttributes );

		return (
			<div className={ className }>
				{ !! focus && (
					<BlockControls key="controls">
						<Toolbar>
							<MediaUploadButton
								buttonProps={ {
									className: 'components-icon-button components-toolbar__control',
									'aria-label': __( 'Edit Logo' ),
								} }
								onSelect={ updateLogo }
								type="image"
								value={ id }>
								<Dashicon icon="edit" />
							</MediaUploadButton>
						</Toolbar>
					</BlockControls>
				) }

				{ focus && (
					<InspectorControls key="inspector">
						<BlockDescription>
							<p>{ __( 'Shows a job listing.' ) }</p>
						</BlockDescription>

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

				{ ! url && (
					<Placeholder
						key="placeholder"
						instructions={ __( 'Drag logo here or insert from media library' ) }
						icon="format-image"
						label={ __( 'Company Logo' ) }
						className="job-listing__logo">
						<FormFileUpload
							isLarge
							className="job-listing__upload-button"
							onChange={ uploadFromFiles }
							accept="image/*">
							{ __( 'Upload' ) }
						</FormFileUpload>
						<MediaUploadButton
							buttonProps={ uploadButtonProps }
							onSelect={ updateLogo }
							type="image">
							{ __( 'Insert from Media Library' ) }
						</MediaUploadButton>
					</Placeholder>
				) }

				{ !! url && (
					<img
						alt={ alt }
						className="job-listing__logo"
						onClick={ setFocus }
						src={ url } />
				) }

				<div className="job-listing__details">
					{ false && (
						<ul className="job-listing__type-list">
							{ /* TODO: Dynamically add list item when job type is selected in Inspector. */ }
							<li className="job-listing__type is-full-time">
								{ __( 'Full Time' ) }
							</li>
							<li className="job-listing__type is-freelance">
								{ __( 'Freelance' ) }
							</li>
						</ul>
					) }

					<div className="job-listing__meta">
						<Editable
							focus={ focusedEditable === 'location' ? focus : null }
							onChange={ updateValue( 'location' ) }
							onFocus={ updateFocus( 'location' ) }
							placeholder={ __( 'Enter job location…' ) }
							tagName="span"
							value={ location }
							wrapperClassName="job-listing__location"
							keepPlaceholderOnFocus />

						{ false && (
							<span className="job-listing__date-posted">
								<time dateTime="2017-08-31">
									{ __( 'Posted 1 minute ago' ) }
								</time>
							</span>
						) }
					</div>

					<div className="job-listing__company-details">
						<Editable
							focus={ focusedEditable === 'company' ? focus : null }
							onChange={ updateValue( 'company' ) }
							onFocus={ updateFocus( 'company' ) }
							placeholder={ __( 'Enter company name…' ) }
							tagName="span"
							value={ company }
							keepPlaceholderOnFocus />
						<Editable
							focus={ focusedEditable === 'tagline' ? focus : null }
							onChange={ updateValue( 'tagline' ) }
							onFocus={ updateFocus( 'tagline' ) }
							placeholder={ __( 'Enter company tagline…' ) }
							tagName="span"
							value={ tagline }
							keepPlaceholderOnFocus />
						<Editable
							focus={ focusedEditable === 'twitter' ? focus : null }
							onChange={ updateValue( 'twitter' ) }
							onFocus={ updateFocus( 'twitter' ) }
							placeholder={ __( 'Enter company Twitter account…' ) }
							tagName="span"
							value={ twitter }
							wrapperClassName="job-listing__twitter"
							keepPlaceholderOnFocus />
					</div>

					<Editable
						focus={ focusedEditable === 'description' ? focus : null }
						onChange={ updateValue( 'description' ) }
						onFocus={ updateFocus( 'description' ) }
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
	},
	save: () => {
		// TODO
		return null;
	}
} );
