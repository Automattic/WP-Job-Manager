=== WP Job Manager ===
Contributors: mikejolley, automattic, adamkheckler, chaselivingston, csonnek, jacobshere, jeherve, jenhooks, kraftbj, lschuyler, macmanx, nancythanki, ryancowles, richardmtl, drawmyface, davor.altman, lamdayap
Tags: job manager, job listing, job board, job management, job lists, job list, job, jobs, company, hiring, employment, employer, employees, candidate, freelance, internship, job listings, positions, board, application, hiring, listing, manager, recruiting, recruitment, talent
Requires at least: 4.1
Tested up to: 4.4
Stable tag: 1.25.0
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

= 1.22.3 =
* Fix frontend listing edits.

= 1.22.2 =
* Tweak - Set form actions to current page.
* Fix - Video embeds.
* Fix - Load textdomain before post types are registered.

= 1.22.1 =
* Fix - It's 2015, but some people are still running PHP 5.2. Compatibility fix.

= 1.22.0 =
* Tweak - Refactored form classes to be instance based rather than static. Reduction in code base.
* Tweak - Admin styling of the job data panels.
* Tweak - Admin styling of the status column.
* Tweak - Better handling of the expiry field.
* Tweak - Search _geolocation_state_long.
* Tweak - Allow admin fields to have custom 'name'.
* Tweak - Use wp_video_shortcode instead of oembed directly.
* Tweak - Tweak menu order code for featured jobs - use -1 for featured and leave other jobs alone.
* Tweak - Clear expired date when publishing an expired listing.
* Tweak - clear_expired_transients function.
* Fix - Prevent term-checklist being disabled for guests.
* Fix - Add WPML var to transient name.
* Fix - Remove use of create_function.

= 1.21.4 =
* Fix - get_job_listings_keyword_search keyword search.
* Fix - Clear term cache when terms are set for any object.
* Fix - Legacy uploads.
* Tweak - RTL improvements.
* Tweak - Use RLIKE to search keywords in content.
* Tweak - Show relative pagination.
* Updated translations.
* Arabic translation by Mamdouh Samy.

= 1.21.3 =
* Feature - Support posts_per_page in feed.
* Fix - Correctly set menu_order when creating a new job, or updating featured status.
* Fix - Updater when there are no featured jobs.
* Fix - Add geolocation_street_number to clear_location_data.

= 1.21.2 =
* Fix - Remove requried attribute from file input.

= 1.21.1 =
* Fix - Remove file type check when field is not required and empty.
* Fix - Hide "Applications have closed" for previews.

= 1.21.0 =
* Feature - Ajax loading history - back button will take you back to current position in the search. If you are on > page 1, a 'load previous' button will be shown so you can paginate either way.
* Feature - Ajax file upload during job submission.
* Feature - Cookie set when submitting a job to allow resuming if you leave the page.
* Feature - job_apply shortcode to show application area in other places on your site.
* Feature - Allow admin fields to be priority sorted.
* Feature - Featured job widget.
* Feature - Scroll to top on pagination click.
* Feature - Option to "Hide content within expired listings". If disabled, expired listings will be listed normally with applications disabled.
* Fix - Prevent attachments being uploaded several times.
* Fix - Expiry date on first save.
* Fix - Relist should go back to form.
* Fix - jquery.com CDN for CSS.
* Tweak - Geocode street and street number separately.
* Tweak - File upload field markup.
* Tweak - Added filters around taxonomy definition.
* Tweak - Chanced search logic/query to use the new meta queries in 4.1.
* Tweak - Implement transient caching for searches.
* Tweak - Removed wp_dropdown_user due to performance concerns.
* Tweak - Use menu_order to make featured listings sticky. Improves performance.
* Tweak - Retrieve AJAX jobs with GET rather than POST request to take advantage of more caching. Plugins looking for POST data will need to update to look for GET/REQUEST instead.
* Tweak - Prevent themes that (sigh) mess with content hooks from breaking inputs.
* Tweak - Remove unused job-category field.
* Tweak - Hide company div if company name missing.

= 1.20.1 =
* Fix - Core template overrides.
* Updated localisations.

= 1.20.0 =
* Feature - Sortable location column in admin.
* Feature - Automatically Generate Username from Email Address option (disable to show a username field).
* Feature - 'filled' option for job shortcode to show all filled/non filled jobs.
* Fix - Pagination with default permalinks.
* Fix - Correctly generate geolocation data when adding a post manually.
* Fix - Chosen width when resizing the page.
* Fix - Show no jobs when all types de-selected.
* Tweak - content-widget-no-jobs-found.php template file.
* Tweak - Don't limit keyword search query functions to published jobs.
* Tweak - job_manager_output_jobs_no_results action.
* Tweak - No results template hooked into job_manager_output_jobs_no_results.
* Tweak - Changed content-no-jobs-found.php content to work for ajax and static lists of jobs. Tweaked text.
* Tweak - job_manager_default_company_logo filter for changing default company image.
* Tweak - Enhance multiselect field with chosen.
* Dev - Abiltiy to pass shortcode args to submit_job_form shortcode.
* Dev - Made get_job_manager_template_part() use locate_job_manager_template().
* Dev - Changed how username/email/role are passed to wp_job_manager_create_account (backwards compat).

= 1.19.0 =
* Feature - Added html5 required attribute to required fields.
* Feature - Added compatibility with RP4WP.
* Fix - Chosen RTL.
* Fix - Addded additonal check to check edit capabilities.
* Fix - Add correct step input to submission form.
* Tweak - Add CSS class to 'showing' bar when shoing all results (no filters).
* Tweak - Geocode, use sublocality_level_1 as city.
* Tweak - Don't update slug when editing via the frontend.
* Tweak - Set default meta data for new jobs.
* Tweak - Add geolocation data after import with WP ALL Import.
* Tweak - Filter to disable chosen: job_manager_chosen_enabled
* Tweak - Login link on job dashboard. job-dashboard-login.php template file.
* Tweak - Made backend management honour capabilities of users. Props to minderdl.

= 1.18.0 =
* Fix - Keep post name when pending job is posted by non-admin.
* Fix - Prevent special chars breaking the feeds.
* Tweak - Added new capabilities for all aspects of Job Listing Management. e.g. edit_job_listings, add_job_listing etc etc. Admin role will be updated on activation/upgrade. If you use custom roles, you'll need to edit them to grant access to the parts you wish.
* Tweak - Improved uninstaller.
* Tweak - Clear location data should include geolocation_postcode.
* Tweak - Always show 'showing' bar, but conditonally show 'reset' link.
* Tweak - Trigger geolocation whenever location field is saved, even by 3rd parties.

= 1.17.0 =
* Feature - job_manager_user_can_edit_pending_submissions function and setting.
* Feature - Job summary shortcode - support random display of job (featured or non featured).
* Feature - In admin, when clicking an author name show all jobs for that author.
* Tweak - Added sanitization function to form class to handle strings and arrays.

= 1.16.1 =
* Fix - Use triggerHandler() instead of trigger() in ajax-filters to prevent events bubbling up.
* Fix - Append current 'lang' to AJAX calls for WPML.
* Fix - When specifying categories on the jobs shortcode, don't clear those categories on reset.
* Tweak - Added job_manager_admin_screen_ids filter.

= 1.16.0 =
* Added setup wizard for new installs that creates pages/shortcodes automatically.
* Added job_manager_get_permalink function.
* Fix - Only show website link when actually set.
* Fix - Only validate application when field is set.
* Tweak - Added description to all fields.
* Tweak - Added better setting fields for defining which pages contain the shortcodes.
* Tweak - Nofollow website links.
* Tweak - Removed font-sizes from default CSS and fixed display in default WP themes.

= 1.15.0 =
* Added location/keyword option to recent jobs widget.
* Added job-listings-start and job-listings-end.php templates for customisation the wrapping elements.
* Added filter type option for job categories. Can be set to require matching to any or all selected categories.
* Added checkbox field type for forms.
* Made backend application email field default to logged in user.
* Fix - job_manager_get_resized_image to not error when it cannot read the image file.
* Fix - Make admin_url relative.
* Fix - Chosen CSS cutting off the placeholder in Firefox.
* Fix - Added _company_video in backend.

= 1.14.0 =
* Extra filters for the filters template.
* Changed text strings for easier customisations based on post type labels, and made some strings more generic.
* New field types - term-checklist, term-multiselect, term-select. These save terms only.
* Added chosen javascript to enhance multiselect boxes when needed.
* Multiple category support.
* Attach Images on Upload to the job posts.
* Added an option to show a multiselect for categories on the job filters.
* Enabled chosen() for the job category filter.
* Added content-single-job_listing-company.php and content-single-job_listing-meta.php templates. These are hooked in.
* Added optional company video field to submission form.
* Video appended to company information box.
* the_company_video() and get_the_company_video() functions.
* show_more="false" for jobs shortcode to prevent loading more jobs.
* job_manager_delete_expired_jobs filter (__return_false to disable expired job deletion).
* job_manager_delete_expired_jobs_days filter (set number of days before deletion - default is 30).
* Support HTML5 multiple attribute for file upload field. Pass multiple=>'true' to form field definition to enable.
* Later loading for template functions.

= 1.13.0 =
* Shortcode arg to show numbered pagination instead of 'load more jobs'. show_pagination argument.
* Define support for Jetpack publicize.
* Show company name alt text for company logo.
* Sort jobs by title, date, expirey date.
* Added noscript element for jobs shortcode.
* filter_var to validate URLs on the job submission form.

= 1.12.1 =
* Job submission form categories must not hide empty categories.

= 1.12.0 =
* On the job submission form, display hierarchical categories.
* Use job_manager_ prefixed hooks for registration (register_post/registration_errors/register_form) to prevent issues with Captcha plugins.
* Pass $post to job_manager_application_email_subject
* Additonal hooks in job-filters template, and moved out job types output to separate template file.
* Option to set the job dashboard page slug so plugins know where to look.
* Allow you@yourdomain.com to be translated.
* Make taxonomies hidden unless current_theme_supports( 'job-manager-templates' )
* Adjusted job application area styling and added some additonal filters.
* Improve backend order status selection.
* Added some responsive styles for job lists.
* Allow users to relist a job from the frontend. (Ensure WC Paid Listings and Simple Paid Listings are updated to support this).

= 1.11.1 =
* Fix ajax filters 'true' for show_filters
* Fix geocoding for certain address strings
* Fix keywords typo
* Remove deprecated $wpdb->escape calls. Replaced with esc_sql

= 1.11.0 =
* Switch geocode to json and improve error checking.
* If query limit is reached, stop making requests for a while.
* Added extra data inside job_feed.
* Few extra icons in font.
* Additonal hooks in single template.
* Pick up search_category from querystring to set default/selected category.
* Ability to define selected_job_types for the jobs shortcode which will set the default job type filters.
* Took out show_featured_only arg for the [jobs] shortcode and added 'featured' which can be set to true or false to show or hide featured jobs, or left null to show both.
* Removed nonce from frontend job submission form to prevent issues with caching.

= 1.10.0 =
* Trigger change on 'enter' when filtering jobs.
* Updated add-ons page to pull from wpjobmanager.com.
* Updated links.
* Fixed support for custom upload URLs.
* Choose/limit application method to email, url or either.
* Default application value (if logged in) set to user's email address.
* show_featured_only option for [jobs] shortcode.
* Add required-field class around required inputs.
* Enable paste as text in wp-editor field.

= 1.9.3 =
* Fix email URLs.
* Target blank for application URLs.
* Add posted by (author) setting in backend.
* When saving jobs, ensure _featured and _filled are set.
* Load admin scripts conditionally.

= 1.9.2 =
* Fix missing parameter in application_details_url causing URLs to be missing when applying.

= 1.9.1 =
* Removed resource heavy 'default_meta' function from the installation process.

= 1.9.0 =
* Template - Split off URL and email application methods and added new hooks. This allows other plugins to manipulate the content.
* Pass $values to edit job save function so permalinks are preserved.
* When showing filters, ensure we check by slug if category is non-numeric.
* Give listings ul a min height so that loading image is visible.
* content-no-jobs-found.php template.
* Fix apostrophe direction in signin template.
* Bulk expire jobs.
* submit_job_form_required_label hook.
* ability to set default state for selects on submit form.
* allow passed in classes in get_job_listing_class function.
* Hook in the content only if in_the_loop(). Fixes issues with jobify and yoast SEO.
* Removed .clear mixin to prevent theme conflicts.

= 1.8.2 =
* For initial load, target all .job_filters areas. Jobify compat.

= 1.8.1 =
* Fix - Corrected check to see if any category terms with jobs exist

= 1.8.0 =
* Feature - Take search/location vars from the querystring if set
* Feature - Option to choose role for registration, and added an 'employer' role.
* Feature - Support for comma separated keywords when searching
* Fix - Use add_post_meta when editing a job to maintain featured status
* Fix - category ordering
* Fix - searching for keyword + location at the same time
* Fix - Only show categories select box when they exist
* Dev - job_manager_application_email_subject filter

= 1.7.3 =
* Some changes to file uploads to support custom mime types
* Updated icon file (http://fontello.com/)
* Fix category rss links
* When doing a location search, search geolocation data
* Fix notices when removing all company fields
* Made jslint happy with ajax-filters.js
* Use get_option( 'default_role' ) when creating a user
* Grunt for release

= 1.7.2 =
* Preserve line breaks when saving textarea fields in admin
* Hide 'showing all x' when no filters are chosen.
* Register 'preview' status so that the counts are correct.
* Delete previews via cron job.

= 1.7.1 =
* Updated textdomain to wp-job-manager
* Re-done .pot file
* Additonal filters for ajax responses
* Moved localisations to Transifex https://www.transifex.com/projects/p/wp-job-manager/

= 1.7.0 =
* Added geolocation to save location data to meta after posting or saving a job. This will be used by other plugins.
* Filter job_manager_geolocation_enabled and return false to turn off geolocation features.
* Jobs shortcode can now be passed 'location' and 'keywords' to set the default for filters, or show only jobs with those keywords if filters are disabled
* Html fix in widget
* Add border around wp editor
* Fix company logo in firefox
* submit_job_form_wp_editor_args filter
* "Empty" categories are visible when filtering jobs in admin.

= 1.6.0 =
* MP6/WP 3.8 optimised styling. Min version 3.8 for new styling.
* Removed images previously used in admin.
* Tweak the_company_logo() to check if logo is valid URL.
* Replaced Genericons with custom set
* Only show link to view job on dashboard when published

= 1.5.2 =
* Fix wp-editor field
* Fix editing job images

= 1.5.1 =
* Changed get_the_time to get_post_time
* Added textarea and wp-editor to form api
* When using the job submit form, generate a more unqiue slug for the job - company-location-type-job-title
* Ability to remove image from job submission form
* Update icon font
* Fix job_types filters
* Field_select in admin
* Fix access control on job editing
* Job forms multiselect support

= 1.5.0 =
* Ability to edit job expiration date manually via admin
* Settings API: Password field
* Frontend Forms: Password field
* Correctly turn off expiration when 'days' is not set
* Greek should be el_GR
* Settings: Use key for tabs - fixes issues with locales
* Show pending count in admin menu
* Added job_types argument to jobs shortcode to show jobs of a certain type only
* Hierarchical dropdown for categories on filter form
* job_manager_job_submitted hook in submission form

= 1.4.0 =
* Added pagination to the job dashboard to avoid memory issues
* Schema.org markup for job listings
* Greek translation by Ioannis Arsenis

= 1.3.1 =
* Remove line breaks from markup to prevent theme issues

= 1.3.0 =
* When using the [jobs] shortcode without filters, if jobs > per-page show the 'load more' link
* Clearfix for meta div
* Hooked up $size option for company logos
* submit_job_form_save_job_data filter
* Italian translation
* Brazillian Portuguese translation
* Respect other plugin columns in admin
* Re-arranged admin columns to show less non-useful data

= 1.2.0 =
* Support for featured job listings
* Support for meta job duration
* set_expirey when publishing jobs from admin manually
* Update handler

= 1.1.3 =
* Corrected form field label
* Added french translation by Remi Corson

= 1.1.2 =
* job_manager_get_dashboard_jobs_args filter
* Better handling of submit job steps.
* Option to store the slug of the submit job page - used by addons.
* Use :input in JS to support multiple input types if customised.

= 1.1.1 =
* Improved accuracy of job search
* Fixed category filter dropdown in admin

= 1.1.0 =
* Tweaked css clearfixes
* Use built in antispambot for encoding email.
* job_manager_job_filters_showing_jobs_links filter
* IE8 Apply filters JS fix
* Fix spanish locale
* Fixed strict standards errors
* Improve 2013 Styles
* Addons page. Disabled usings add_filter( 'job_manager_show_addons_page', '__return_false' );

= 1.0.5 =
* Added function to get listings by certain criteria.
* Added ES translation.
* Fix job feed when no args are present.

= 1.0.4 =
* More hooks in the submit process.
* Hide apply button if url/email is unset.

= 1.0.3 =
* Some extra hooks in job-filters.php
* Added a workaround for scripts which bork placeholders inside the job filters.

= 1.0.2 =
* Action in update_job_data() to allow saving of extra fields.
* Added German translation by Chris Penning

= 1.0.1 =
* Slight tweak to listing field filters in admin.
* 'attributes' argument for admin settings.

= 1.0.0 =
* First stable release.

== Upgrade Notice ==

= 1.22.0 =
As this is a major update, please ensure you update any plugins and themes that rely on Job Manager. This includes Resume Manager, Applications, WC Paid Lisitngs, and themes such as Jobify.
