import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const PROMO_LINK = 'https://wpjobmanager.com/add-ons/job-tags/?utm_source=plugin_wpjm&utm_medium=upsell&utm_campaign=job_tags_editor_upsell';

const WPJM_Job_Tags_Upsell_Panel = () => (
    <PluginDocumentSettingPanel
        name="job-tags-upsell-panel"
        title="Job Tags"
        className="job-tags-upsell-panel"
    >
	<p>
		{ __( 'Improve job listings by adding Job Tags, which can include skills, interests, technologies, and more.', 'wp-job-manager' ) }
	</p>
	<ExternalLink href={ PROMO_LINK }>
		{ __( 'Get Job Tags', 'wp-job-manager' ) }
	</ExternalLink>
    </PluginDocumentSettingPanel>
)

registerPlugin('plugin-document-setting-panel-demo', {
    render: WPJM_Job_Tags_Upsell_Panel
})
