/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import EditJob from './edit.jsx';
import './style.scss';
import registerStore from '../data';

const attributesConfig = {
	jobId: {
		type: 'integer',
		default: 0,
	},
};

const edit = ( { className, ...props } ) => (
	<div className={ className }>
		<EditJob { ...props } />
	</div>
);

const save = ( { attributes } ) => {
	if ( ! attributes.id ) {
		return;
	}

	return `[job id="${ attributes.id }"]`;
};

registerStore();

registerBlockType( 'wp-job-manager/job', {
	title: 'Job',
	icon: 'list-view',
	category: 'common',
	attributes: applyFilters( 'wpjm_block_job_attributes_config', attributesConfig ),
	edit,
	save,
} );
