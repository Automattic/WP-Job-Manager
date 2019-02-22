=== WP Job Manager ===
Contributors: mikejolley, automattic, adamkheckler, alexsanford1, annezazu, cena, chaselivingston, csonnek, davor.altman, donnapep, donncha, drawmyface, erania-pinnera, jacobshere, jakeom, jeherve, jenhooks, jgs, jonryan, kraftbj, lamdayap, lschuyler, macmanx, nancythanki, orangesareorange, rachelsquirrel, ryancowles, richardmtl, scarstocea
Tags: job manager, job listing, job board, job management, job lists, job list, job, jobs, company, hiring, employment, employer, employees, candidate, freelance, internship, job listings, positions, board, application, hiring, listing, manager, recruiting, recruitment, talent
Requires at least: 4.7.0
Tested up to: 5.1
Stable tag: 1.32.2
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

If you want help with a customization, please consider hiring a developer! [http://jobs.wordpress.net/](http://jobs.wordpress.net/) is a good place to start.

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
There are several ways to customize the job application process in WP Job Manager, including using some extra plugins (some are free on Wordpress.org).

See: [Customizing the Job Application Process](https://wpjobmanager.com/document/customising-job-application-process/)

= How can I customize the job submission form? =
There are three ways to customize the fields in WP Job Manager;

1. For simple text changes, using a localisation file or a plugin such as https://wordpress.org/plugins/say-what/
2. For field changes, or adding new fields, using functions/filters inside your theme's functions.php file: [https://wpjobmanager.com/document/editing-job-submission-fields/](https://wpjobmanager.com/document/editing-job-submission-fields/)
3. Use a 3rd party plugin such as [https://plugins.smyl.es/wp-job-manager-field-editor/](https://plugins.smyl.es/wp-job-manager-field-editor/?in=1) which has a UI for field editing.

If you'd like to learn about WordPress filters, here is a great place to start: [https://pippinsplugins.com/a-quick-introduction-to-using-filters/](https://pippinsplugins.com/a-quick-introduction-to-using-filters/)

= How can I be notified of new jobs via email? =
If you wish to be notified of new postings on your site you can use a plugin such as [Post Status Notifier](http://wordpress.org/plugins/post-status-notifier-lite/).

= What language files are available? =
You can view (and contribute) translations via the [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wp-job-manager).

== Unit Testing ==

The plugin contains all the files needed for running tests.
Developers who would like to run the existing tests or add their tests to the test suite and execute them will have to follow these steps:
1. `cd` into the plugin directory.
2. Run the install script(you will need to have `wget` installed) - `bash tests/bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> <wp-version>`.
3. Run the plugin tests - `phpunit`

The install script installs a copy of WordPress in the `/tmp` directory along with the WordPress unit testing tools. 
It then creates a database based on the parameters passed to it.

== Screenshots ==

1. The submit job form.
2. Submit job preview.
3. A single job listing.
4. Job dashboard.
5. Job listings and filters.
6. Job listings in admin.

== Changelog ==

= 1.32.2 =
* Fix: Issue saving job types for job listings in WordPress admin after WordPress 5.1 update.
* Fix: Add nonce checks on edit/submit forms for logged in users. Will require updates to `templates/job-preview.php` if overridden in theme. (Props to foobar7)
* Fix: Escape JSON encoded strings.
* Fix: Add additional sanitization for file attachment fields.

= 1.32.1 =
* Fix: Adds compatibility with PHP 7.3
* Fix: Restores original site search functionality.

= 1.32.0 =
* Enhancement: Switched from Chosen to Select2 for enhanced dropdown handling and better mobile support. May require theme update.
* Enhancement: Draft and unsubmitted job listings now appear in `[job_dashboard]`, allowing users to complete their submission.
* Enhancement: [REVERTED IN 1.32.1] Filled and expired positions are now hidden from WordPress search. (@felipeelia) 
* Enhancement: Adds additional support for the new block editor. Restricted to classic block for compatibility with frontend editor.
* Enhancement: Job types can be preselected in `[jobs]` shortcode with `?search_job_type=term-slug`. (@felipeelia)
* Enhancement: Author selection in WP admin now uses a searchable dropdown.
* Enhancement: Setup wizard is accessed with a flash message instead of an automatic redirect upon activation.
* Enhancement: When using supported themes, job listing archive slug can be changed in Permalink settings.
* Fix: Company tagline alignment issue with company name. (@0xDELS)
* Fix: "Load Previous Listings" link unnecessarily shows up on `[jobs]` shortcode. (@tonytettinger)
* Fix: Category selector fixed in the job listings page in WP Admin. (@AmandaJBell)
* Fix: Issue with quote encoding on Apply for Job email link.
* Fix: Link `target` attributes have been removed in templates.
* Dev: Allow for job submission flow to be interrupted using `before` argument on form steps.
* Dev: HTML allowed in custom company field labels. (@tripflex)
* Dev: Job feed slug name can be customized with the `job_manager_job_feed_name` filter.
* Deprecated: Unreleased REST API implementation using `WPJM_REST_API_ENABLED` was replaced with standard WP REST API.

= 1.31.3 =
* Fix: Escape the attachment URL. (Props to karimeo)
* Fix: Custom job field priority fix when using decimals. (@tripflex)
* Fix: Fix issue with empty mutli-select in WP admin jobs page. (@felipeelia)
* Fix: Issue with data export when email doesn't have any job listings. 
* Third Party: Improved WPML support. (@vukvukovich)

= 1.31.2 =
* Fix: Adds missing quote from WP admin taxonomy fields. (@redpik)

= 1.31.1 =
* Enhancement: Add option to show company logo in Recent Jobs widget. (@RajeebTheGreat)
* Enhancement: Suggest additional cookie information on Privacy Policy page.
* Enhancement: Add WPJM related meta data to user data extract.
* Fix: Tightened the security of the plugin with additional string escaping.
* Fix: Issue with map link in admin backend. (@RajeebTheGreat)
* Fix: No longer auto-expire job listings in Draft status.
* Fix: Issue with undefined index error in WP admin. (@albionselimaj)
* Fix: Issue with duplicate usernames preventing submission of job listings. (@timothyjensen)
* Dev: Widespread code formatting cleanup throughout the plugin. 

= 1.31.0 =
* Change: Minimum WordPress version is now 4.7.0.
* Enhancement: Add email notifications with initial support for new jobs, updated jobs, and expiring listings.
* Enhancement: For GDPR, scrub WPJM data from database on uninstall if option is enabled.
* Enhancement: Filter by Filled and Featured status in WP admin.
* Enhancement: Simplify the display of application URLs.
* Enhancement: When using WPML, prevent changes to page options when on a non-default language. (@vukvukovich) 
* Enhancement: Include company logo in structured data. (@RajeebTheGreat)
* Enhancement: Use more efficient jQuery selectors in scripts. (@RajeebTheGreat)
* Enhancement: Use proper `<h2>` tag in `content-summary-job_listing.php` template for the job title. (@abdullah1908)
* Enhancement: Hide empty categories on `[job]` filter.
* Fix: Update calls to `get_terms()` to use the new format.
* Fix: Maintain the current tab when saving settings in WP Admin.
* Fix: Enqueue the date picker CSS when used on the front-end.
* Fix: Remove errors when widget instance was created without setting defaults.
* REST API Pre-release: Add support for job category taxonomy endpoints.
* Dev: Add `$job_id` parameter to `job_manager_job_dashboard_do_action_{$action}` action hook. (@jonasvogel)
* Dev: Add support for hidden WPJM settings in WP Admin.

= 1.30.2 =
* Enhancement: Show notice when user is using an older version of WordPress.
* Enhancement: Hide unnecessary view mode in WP Admin's Job Listings page. (@RajeebTheGreat) 
* Enhancement: Add support for the `paged` parameter in the RSS feed. (@RajeebTheGreat)
* Fix: Minor PHP 7.2 compatibility fixes.
* Dev: Allow `parent` attribute to be passed to `job_manager_dropdown_categories()`. (@RajeebTheGreat)

= 1.30.1 =
* Fix: Minor issue with a strict standard error being displayed on some instances.

= 1.30.0 =
* Enhancement: Adds ability to have a reCAPTCHA field to check if job listing author is human.
* Enhancement: Allows for option to make edits to job listings force listing back into pending approval status.
* Enhancement: Adds spinner and disables form when user submits job listing.
* Enhancement: Update the add-ons page of the plugin.
* Enhancement: Added the ability to sort jobs randomly on the Featured Jobs Widget.
* Enhancement: Improved handling of alternative date formats when editing job expiration field in WP admin.
* Enhancement: Added star indicator next to featured listings on `[job_dashboard]`.
* Enhancement: Opt-in to usage tracking so we can better improve the plugin.
* Enhancement: Introduced new asset enqueuing strategy that will be turned on in 1.32.0. Requires plugin and theme updates. (Dev notes: https://github.com/Automattic/WP-Job-Manager/pull/1354)
* Fix: Use WordPress core checks for image formats to not confuse `docx` as an image. (@tripflex)
* Fix: Issue with `[jobs]` shortcode when `categories` argument is provided.
* Fix: Issue with double encoding HTML entities in custom text area fields.
* Fix: Updates `job-dashboard.php` template with `colspan` fix on no active listings message.
* Fix: Clear job listings cache when deleting a user and their job listings.
* Dev: Adds `is_wpjm()` and related functions to test if we're on a WPJM related page.
* Dev: Adds `job_manager_user_edit_job_listing` action that fires after a user edits a job listing.
* Dev: Adds `job_manager_enable_job_archive_page` filter to enable job archive page.
* Dev: Adds `date` field for custom job listing form fields.

= 1.29.3 =
* Fix: When retrieving job listing results, cache only the post results and not all of `WP_Query` (props slavco)

= 1.29.2 =
* Fix: PHP Notice when sanitizing multiple inputs (bug in 1.29.1 release). (@albionselimaj)

= 1.29.1 =
* Enhancement: When retrieving listings in `[jobs]` shortcode, setting `orderby` to `rand_featured` will still place featured listings at the top.
* Enhancement: Scroll to show application details when clicking on "Apply for Job" button.
* Change: Updates `account-signin.php` template to warn users email will be confirmed only if that is enabled.
* Fix: Sanitize URLs and emails differently on the application method job listing field.
* Fix: Remove PHP notice in Featured Jobs widget. (@himanshuahuja96)
* Fix: String fix for consistent spelling of "license" when appearing in strings. (@garrett-eclipse)
* Fix: Issue with paid add-on licenses not showing up when some third-party plugins were installed.
* Dev: Runs new actions (`job_manager_recent_jobs_widget_before` and `job_manager_recent_jobs_widget_after`) inside Recent Jobs widget.
* Dev: Change `wpjm_get_the_job_types()` to return an empty array when job types are disabled.
* See all: https://github.com/Automattic/WP-Job-Manager/milestone/15?closed=1

= 1.29.0 =
* Enhancement: Moves license and update management for official add-ons to the core plugin.
* Enhancement: Update language for setup wizard with more clear descriptions.
* Fix: Prevent duplicate attachments to job listing posts for non-image media. (@tripflex)
* Fix: PHP error on registration form due to missing placeholder text.
* Fix: Apply `the_job_application_method` filter even when no default is available. (@turtlepod)
* Fix: Properly reset category selector on `[jobs]` shortcode.

= 1.28.0 =
* Enhancement: Improves support for Google Job Search by adding `JobPosting` structured data.
* Enhancement: Adds ability for job types to be mapped to an employment type as defined for Google Job Search.
* Enhancement: Requests search engines no longer index expired and filled job listings.
* Enhancement: Improves support with third-party sitemap generation in Jetpack, Yoast SEO, and All in One SEO.
* Enhancement: Updated descriptions and help text on settings page.
* Enhancement: Lower cache expiration times across plugin and limit use of autoloaded cache transients.
* Fix: Localization issue with WPML in the [jobs] shortcode.
* Fix: Show job listings' published date in localized format.
* Fix: Job submission form allows users to select multiple job types when they go back a step.
* Fix: Some themes that overloaded functions would break in previous release.
* Dev: Adds versions to template files so it is easier to tell when they are updated.
* Dev: Adds a new `wpjm_notify_new_user` action that allows you to override default behavior.
* Dev: Early version of REST API is bundled but disabled by default. Requires PHP 5.3+ and `WPJM_REST_API_ENABLED` constant must be set to true. Do not use in production; endpoints may change. (@pkg)

= 1.27.0 =
* Enhancement: Admins can now allow users to specify an account password when posting their first job listing.
* Enhancement: Pending job listing counts are now cached for improved WP Admin performance. (@tripflex)
* Enhancement: Allows users to override permalink slugs in WP Admin's Permalink Settings screen.
* Enhancement: Allows admins to perform bulk updating of jobs as filled/not filled.
* Enhancement: Adds job listing status CSS classes on single job listings.
* Enhancement: Adds `wpjm_the_job_title` filter for inserting non-escaped HTML alongside job titles in templates.
* Enhancement: Allows admins to filter by `post_status` in `[jobs]` shortcode.
* Enhancement: Allows accessing settings tab from hash in URL. (@tripflex)
* Fix: Make sure cron jobs for checking/cleaning expired listings are always in place.
* Fix: Better handling of multiple job types. (@spencerfinnell)
* Fix: Issue with deleting company logos from job listings submission form.
* Fix: Warning thrown on job submission form when user not logged in. (@piersb)  
* Fix: Issue with WPML not syncing some meta fields.
* Fix: Better handling of AJAX upload errors. (@tripflex)
* Fix: Remove job posting cookies on logout.
* Fix: Expiration date can be cleared if default job duration option is empty. (@spencerfinnell)
* Fix: Issue with Safari and expiration datepicker.

= 1.26.2 =
* Fix: Prevents use of Ajax file upload endpoint for visitors who aren't logged in. Themes should check with `job_manager_user_can_upload_file_via_ajax()` if using endpoint in templates.  
* Fix: Escape post title in WP Admin's Job Listings page and template segments. (Props to @EhsanCod3r)

= 1.26.1 =
* Enhancement: Add language using WordPress's current locale to geocode requests.
* Fix: Allow attempts to use Google Maps Geocode API without an API key. (@spencerfinnell)
* Fix: Issue affecting job expiry date when editing a job listing. (@spencerfinnell)
* Fix: Show correct total count of results on `[jobs]` shortcode.

= 1.26.0 =
* Enhancement: Warn the user if they're editing an existing job.
* Enhancement: WP Admin Job Listing page's table is now responsive. (@turtlepod)
* Enhancement: New setting for hiding expired listings from `[jobs]` filter. (@turtlepod)
* Enhancement: Use WP Query's built in search function to improve searching in `[jobs]`.
* Fix: Job Listing filter only searches meta fields with relevant content. Add custom fields with `job_listing_searchable_meta_keys` filter. (@turtlepod)
* Fix: Improved support for WPML and Polylang.
* Fix: Expired field no longer forces admins to choose a date in the future. (@turtlepod)
* Fix: Listings with expiration date in past will immediately expire; moving to Active status will extend if necessary. (@turtlepod)
* Fix: Google Maps API key setting added to fix geolocation retrieval on new sites.
* Fix: Issue when duplicating a job listing with a field for multiple file uploads. (@turtlepod)
* Fix: Hide page results when adding links in the `[submit_job_form]` shortcode.
* Fix: Job feed now loads when a site has no posts.
* Fix: No error is thrown when deleting a user. (@tripflex)
* Dev: Plugins and themes can now retrieve JSON of Job Listings results without HTML. (@spencerfinnell)
* Dev: Updated inline documentation.

See additional changelog items in changelog.txt
