=== WP Job Manager ===
Contributors: mikejolley, automattic, adamkheckler, annezazu, artemitos, bikedorkjon, cena, chaselivingston, csonnek, davor.altman, drawmyface, erania-pinnera, jacobshere, jeherve, jenhooks, jgs, kraftbj, lamdayap, lschuyler, macmanx, nancythanki, orangesareorange, rachelsquirrel, ryancowles, richardmtl, scarstocea
Tags: job manager, job listing, job board, job management, job lists, job list, job, jobs, company, hiring, employment, employer, employees, candidate, freelance, internship, job listings, positions, board, application, hiring, listing, manager, recruiting, recruitment, talent
Requires at least: 4.1
Tested up to: 4.6
Stable tag: 1.25.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Manage job listings from the WordPress admin panel, and allow users to post job listings directly to your site.

== Description ==

WP Job Manager is a **lightweight** job listing plugin for adding job-board like functionality to your WordPress site. Being shortcode based, it can work with any theme (given a bit of CSS styling) and is really simple to setup.

= Features =

* Add, manage, and categorize job listings using the familiar WordPress UI.
* Searchable & filterable ajax powered job listings added to your pages via shortcodes.
* Frontend forms for guests and registered users to submit & manage job listings.
* Allow job listers to preview their listing before it goes live. The preview matches the appearance of a live job listing.
* Each listing can be tied to an email or website address so that job seekers can apply to the jobs.
* Searches also display RSS links to allow job seekers to be alerted to new jobs matching their search.
* Allow logged in employers to view, edit, mark filled, or delete their active job listings.
* Developer friendly code — Custom Post Types, endpoints & template files.

The plugin comes with several shortcodes to output jobs in various formats, and since its built with Custom Post Types you are free to extend it further through themes.

[Read more about WP Job Manager](https://wpjobmanager.com/).

= Documentation =

Documentation for the core plugin and add-ons can be found [on the docs site here](https://wpjobmanager.com/documentation/). Please take a look before requesting support because it covers all frequently asked questions!

= Add-ons =

The core WP Job Manager plugin is free and always will be. It covers all functionality we consider 'core' to running a simple job board site.

Additional, advanced functionality is available through add-ons. Not only do these extend the usefulness of the core plugin, they also help fund the development and support of core.

You can browse available add-ons after installing the plugin by going to `Job Listings > Add-ons`. Our popular add-ons include:

**[Applications](https://wpjobmanager.com/add-ons/applications/)**

Allow candidates to apply to jobs using a form & employers to view and manage the applications from their job dashboard.

**[WooCommerce Paid Listings](https://wpjobmanager.com/add-ons/wc-paid-listings/)**

Paid listing functionality powered by WooCommerce. Create custom job packages which can be purchased or redeemed during job submission. Requires the WooCommerce plugin.

**[Resume Manager](https://wpjobmanager.com/add-ons/resume-manager/)**

Resume Manager is a plugin built on top of WP Job Manager which adds a resume submission form to your site and resume listings, all manageable from WordPress admin.

**[Job Alerts](https://wpjobmanager.com/add-ons/job-alerts/)**

Allow registered users to save their job searches and create alerts which send new jobs via email daily, weekly or fortnightly.

**[Core add-on bundle](https://wpjobmanager.com/add-ons/bundle/)**

You can get the above add-ons and several others at discount with our [Core Add-on Bundle](https://wpjobmanager.com/add-ons/bundle/). Take a look!

= Contributing and reporting bugs =

You can contribute code to this plugin via GitHub: [https://github.com/Automattic/WP-Job-Manager](https://github.com/Automattic/WP-Job-Manager) and localizations via [https://translate.wordpress.org/projects/wp-plugins/wp-job-manager](https://translate.wordpress.org/projects/wp-plugins/wp-job-manager)

Thanks to all of our contributors.

= Support =

Use the WordPress.org forums for community support where we try to help all users. If you spot a bug, you can log it (or fix it) on [Github](https://github.com/Automattic/WP-Job-Manager) where we can act upon them more efficiently.

If you need help with one of our add-ons, [please raise a ticket in our help desk](https://wpjobmanager.com/support/).

If you want help with a customisation, please consider hiring a developer! [http://jobs.wordpress.net/](http://jobs.wordpress.net/) is a good place to start.

== Installation ==

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't even need to leave your web browser. To do an automatic install, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.

In the search field type "WP Job Manager" and click Search Plugins. Once you've found the plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by clicking _Install Now_.

= Manual installation =

The manual installation method involves downloading the plugin and uploading it to your web server via your favorite FTP application.

* Download the plugin file to your computer and unzip it
* Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation's `wp-content/plugins/` directory.
* Activate the plugin from the Plugins menu within the WordPress admin.

= Getting started =

Once installed:

1. Create a page called "jobs" and inside place the `[jobs]` shortcode. This will list your jobs.
2. Create a page called "submit job" and inside place the `[submit_job_form]` shortcode if you want front-end submissions.
3. Create a page called "job dashboard" and inside place the `[job_dashboard]` shortcode for logged in users to manage their listings.

**Note when using shortcodes**, if the content looks blown up/spaced out/poorly styled, edit your page and above the visual editor click on the 'text' tab. Then remove any 'pre' or 'code' tags wrapping your shortcode.

For more information, [read the documentation](https://wpjobmanager.com/documentation/).

== Frequently Asked Questions ==

= How do I setup WP Job Manager? =
View the getting [installation](https://wpjobmanager.com/document/installation/) and [setup](https://wpjobmanager.com/document/setting-up-wp-job-manager/) guide for advice getting started with the plugin. In most cases it's just a case of adding some shortcodes to your pages!

= Can I use WP Job Manager without frontend job submission? =
Yes! If you don't setup the [submit_job_form] shortcode, you can just post from the admin backend.

= How can I customize the job application process? =
There are several ways to customise the job application process in WP Job Manager, including using some extra plugins (some are free on Wordpress.org).

See: [Customising the Job Application Process](https://wpjobmanager.com/document/customising-job-application-process/)

= How can I customize the job submission form? =
There are three ways to customise the fields in WP Job Manager;

1. For simple text changes, using a localisation file or a plugin such as https://wordpress.org/plugins/say-what/
2. For field changes, or adding new fields, using functions/filters inside your theme's functions.php file: [https://wpjobmanager.com/document/editing-job-submission-fields/](https://wpjobmanager.com/document/editing-job-submission-fields/)
3. Use a 3rd party plugin such as [https://plugins.smyl.es/wp-job-manager-field-editor/](https://plugins.smyl.es/wp-job-manager-field-editor/?in=1) which has a UI for field editing.

If you'd like to learn about WordPress filters, here is a great place to start: [https://pippinsplugins.com/a-quick-introduction-to-using-filters/](https://pippinsplugins.com/a-quick-introduction-to-using-filters/)

= How can I be notified of new jobs via email? =
If you wish to be notified of new postings on your site you can use a plugin such as [Post Status Notifier](http://wordpress.org/plugins/post-status-notifier-lite/).

= What language files are available? =
You can view (and contribute) translations via the [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wp-job-manager).

== Screenshots ==

1. The submit job form.
2. Submit job preview.
3. A single job listing.
4. Job dashboard.
5. Job listings and filters.
6. Job listings in admin.

== Changelog ==

= 1.25.1 =
* Feature - Adds a view button to the Admin UI for easy access to submitted files or URLs. Props tripflex (https://github.com/Automattic/WP-Job-Manager/pull/650)
* Fix - Add hardening to file uploads to prevent accepting unexpected file times. Previously, other WP-allowed types were sometimes accepted.
* Fix - Job post form categories are now properly cached and displayed per language when using WPML or Polylang. (https://github.com/Automattic/WP-Job-Manager/issues/692)
* Fix - Refactored WPML workaround, which was causing no job listings on non-default languages. (https://github.com/Automattic/WP-Job-Manager/issues/617)
* Fix - Allow employers to edit job listings when a listing is pending payment. (https://github.com/Automattic/WP-Job-Manager/pull/664)
* Fix - No longer display Job Taxonomies in the WordPress tag cloud. (https://github.com/Automattic/WP-Job-Manager/pull/658)
* Fix - Migrate away from jQuery.live, which is no longer supported. ( https://github.com/Automattic/WP-Job-Manager/pull/664 )
* Tweak - Updated incorrect settings description. (https://github.com/Automattic/WP-Job-Manager/pull/632)
* Dev - Adds hook to add items in a job's RSS feed item. (https://github.com/Automattic/WP-Job-Manager/pull/636)
* Dev - Adds filter to disable Job Listings cache (https://github.com/Automattic/WP-Job-Manager/pull/684)
* Dev - Inline docs and coding standards improvements.

= 1.25.0 =
* Feature - Ability to duplicate listings from job dashboard.
* Fix - Support WP_EMBED in job descriptions.
* Fix - Ensure logo is displayed on edit, before submission.
* Fix - Attachment URLs on multisite.
* Fix - Refactored WPML workaround, which was causing no job listings on non-default languages. (https://github.com/Automattic/WP-Job-Manager/issues/617)
* Fix - No need to decode URLs anymore https://core.trac.wordpress.org/ticket/23605.
* Dev - submit_job_form_end/submit_job_form_start actions.
* Dev - job-manager-datepicker class for backend date fields.

= 1.24.0 =
* Feature - Use featured images to store company logos.
* Feature - Search term names for keywords.
* Feature - Search custom fields in backend job listing search.
* Tweak - Allow job expiry field to be localised.
* Fix - The above change avoids creation of duplicate images in media library.
* Dev - Added methods to WP_Job_Manager_Form; get_steps, get_step_key, set_step.
* Dev - Made WP_Job_Manager_Form call the next 'handler' if no view is defined for the next step.
* Dev - Added template to control job preview form.

= 1.23.13 =
* Fix - Conflict between the_job_location() and the regions plugin.
* Tweak - Allow some HTML in the_job_location - uses wp_kses_post.

= 1.23.12 =
* Fix - Transient clear query.
* Tweak - New user notification pluggable function.
* Tweak - Use subquery in keyword search to avoid long queries.
* Tweak - Only search for keywords of 2 or more characters.
* Tweak - job_manager_get_listings_keyword_length_threshold filter.
* Tweak - PolyLang compatibility functions.
* Tweak - Unattach company logo when a new attachment is uploaded.

= 1.23.11 =
* Fix - Author check in job_manager_user_can_edit_job().
* Tweak - Before deleting a job, delete its attachments.
* Tweak - Show previews in backend if needed.

= 1.23.10 =
* Fix - Handle WP 4.3 signup notification.
* Fix - Map mime types to those that WordPress knows.
* Fix - Alert text color.
* Fix - Searches containing special chars.
* Tweak - Improved uploader error handling and updated library.
* Tweak - Improve job_manager_user_can_post_job and job_manager_user_can_edit_job capability handling in job-submit.php
* Tweak - Clear transients in batches of 500.
* Tweak - Removed transifex and translations - translation will take place on https://translate.wordpress.org/projects/wp-plugins/wp-job-manager

= 1.23.9 =
* Fixed editing content with wp_editor. Can no longer be passed to function already escaped.

= 1.23.8 =
* Fix - Security: XSS issue in account signin.
* Tweak - Update new account email text.

= 1.23.7 =
* Fix - 4.3 issue showing "Description is a required field" due to editor field.
* Tweak - Default job_manager_delete_expired_jobs to false. Set to true to have expired jobs deleted automatically. More sensible default.
* Tweak - job_manager_term_select_field_wp_dropdown_categories_args filter.
* Tweak - Ajax WPML handling.

= 1.23.6 =
* Fix - job_manager_ajax_filters -> job_manager_ajax_file_upload in file upload script.

= 1.23.5 =
* Feature - Allow [job_summary] to output multiple listings via 'limit' parameter.
* Feature - Added flowplayer support.
* Fix - Special chars in feeds.
* Fix - Permalinks with index.php inside.
* Fix - Notice when saving job form.
* Fix - PHP4 widget constructors (https://gist.github.com/chriscct7/d7d077afb01011b1839d).
* Tweak - Allow translation of job_manager_dropdown_categories.
* Tweak - Added handling for .job-manager-filter class.
* Tweak - Added trailing slashes to ajax endpoints.
* Tweak - Made videos responsive.
* Tweak - job_manager_attach_uploaded_files filter.

= 1.23.4 =
* Tweak - In 1.21.0 we switched to GET ajax requests to leverage caching, however, due to the length of some queries this was causing 414 request URI too long errors in many environments. Reverted to POST to avoid this.
* Tweak - flush_rewrite_rules after updates to ensure ajax endpoint exists.
* Tweak - Use relative path for ajax endpoint to work around https/http.

= 1.23.3 =
* Fix - WPML integration with lang.
* Tweak - Improved plugin activation code.
* Tweak - Improved theme switch code.
* Tweak - Search the entire meta field, not just from the start.
* Tweak - Added some debugging code to ajax-filters to display in console.

= 1.23.2 =
* Fix - Send entire form data (listify workaround).
* Fix - Set is_home false on ajax endpoint (listify workaround).

= 1.23.1 =
* Fix - Orderby featured should be "menu order, date", not "manu order, title".
* Tweak - Remove duplicate data from form_data in filters JS.
* Tweak - If index is -1 in filters JS, abort.

= 1.23.0 =
* Feature - Custom AJAX endpoints to reduce overhead of loading admin.
* Feature - Support radio fields.
* Fix - Video max width.
* Tweak - Admin remove overflow hidden from data box.
* Tweak - Update notice styling.
* Tweak - Improve orderby. https://make.wordpress.org/core/2014/08/29/a-more-powerful-order-by-in-wordpress-4-0/
* Tweak - nofollow apply links.
* Tweak - Rename 'title' to 'job title' for clarity.
* Tweak - submit_job_form_prefix_post_name_with_company filter.
* Tweak - submit_job_form_prefix_post_name_with_location filter.
* Tweak - submit_job_form_prefix_post_name_with_job_type filter.
* Tweak - Improved job_feed searching.
* Tweak - Improved transient cleaning.

See additional changelog items in changelog.txt

== Upgrade Notice ==

= 1.25.1 =
This release includes many bugs and fixes including some security improvements. Upgrade today!
