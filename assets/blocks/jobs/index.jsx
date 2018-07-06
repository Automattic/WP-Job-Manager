
/**
 * Internal Dependencies.
 */
import './style.scss';
import edit from './edit.jsx';

/**
 * WordPress Dependencies.
 */
const { registerBlockType } = wp.blocks;

/**
 * Register the Jobs block.
 */
registerBlockType( 'wp-job-manager/jobs', {
	title: 'Jobs',
	icon: 'list-view',
	category: 'common',

	edit,
	save: () => null,
} );
