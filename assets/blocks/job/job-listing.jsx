/**
 * External dependencies
 */
import moment from 'moment';
import { find, map } from 'lodash';

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Returns the URL for the company image
 *
 * @param  {Array}  media All listing's media
 * @param  {number} id    Company logo's media id
 * @return {string} Company logo's URL
 */
const getCompanyLogo = ( media, id ) => id && find( media, { id } ).source_url;

/**
 * Returns the name of a job type given the id and an array of types
 *
 * @param  {Array}  types All job types
 * @param  {number} id    Job type ID
 * @return {string} Job type name
 */
const getJobType = ( types, id ) => find( types, { id } ).name;

const JobListing = ( { listing, jobTypes } ) => {
	const logo = getCompanyLogo( listing._embedded && listing._embedded[ 'wp:featuredmedia' ], listing.featured_media );
	const twitter = listing.fields._company_twitter;
	const website = listing.fields._company_website;

	return (
		<div className="job_shortcode single_job_listing">
			<h1 className="job-listing__title">{ listing.title.rendered }</h1>
			<div className="single_job_listing">
				<ul className="job-listing-meta meta">
					{ map( listing[ 'job-types' ], ( id ) => {
						const type = getJobType( jobTypes, id );

						return <li className={ `job-type ${ type }` }>{ type }</li>;
					} ) }

					<li className="location">{ listing.fields._job_location }</li>
					<li className="date-posted">
						{ sprintf( __( 'Posted %s' ), moment( listing.date_gmt ).fromNow() ) }
					</li>
				</ul>
				<div className="company">
					{ logo && <img className="company_logo" src={ logo } alt={ listing.fields._company_name } /> }
					<p className="name">
						{ website && <a href={ website } className="website" target="_blank" rel="noopener noreferrer">{ __( 'Website' ) }</a> }
						{
							twitter &&
							<a
								href={ `https://twitter.com/${ twitter }` }
								className="company_twitter"
								target="_blank"
								rel="noopener noreferrer">
								{ twitter }
							</a>
						}
						<strong>{ listing.fields._company_name }</strong>
					</p>
					<p className="tagline">{ listing.fields._company_tagline }</p>
					<div className="company_video"></div>
				</div>
				<div className="job_description" dangerouslySetInnerHTML={ { __html: listing.content.rendered } }></div>
				<div className="job_application application">
					<input type="button" className="application_button button" value={ __( 'Apply for job' ) } />
				</div>
			</div>
		</div>
	);
};

export default JobListing;
