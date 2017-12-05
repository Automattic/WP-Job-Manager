# WP Job Manager #
**Contributors:** [mikejolley](https://profiles.wordpress.org/mikejolley), [automattic](https://profiles.wordpress.org/automattic), [adamkheckler](https://profiles.wordpress.org/adamkheckler), [annezazu](https://profiles.wordpress.org/annezazu), [cena](https://profiles.wordpress.org/cena), [chaselivingston](https://profiles.wordpress.org/chaselivingston), [csonnek](https://profiles.wordpress.org/csonnek), [davor.altman](https://profiles.wordpress.org/davor.altman), [drawmyface](https://profiles.wordpress.org/drawmyface), [erania-pinnera](https://profiles.wordpress.org/erania-pinnera), [jacobshere](https://profiles.wordpress.org/jacobshere), [jakeom](https://profiles.wordpress.org/jakeom), [jeherve](https://profiles.wordpress.org/jeherve), [jenhooks](https://profiles.wordpress.org/jenhooks), [jgs](https://profiles.wordpress.org/jgs), [jonryan](https://profiles.wordpress.org/jonryan), [kraftbj](https://profiles.wordpress.org/kraftbj), [lamdayap](https://profiles.wordpress.org/lamdayap), [lschuyler](https://profiles.wordpress.org/lschuyler), [macmanx](https://profiles.wordpress.org/macmanx), [nancythanki](https://profiles.wordpress.org/nancythanki), [orangesareorange](https://profiles.wordpress.org/orangesareorange), [rachelsquirrel](https://profiles.wordpress.org/rachelsquirrel), [ryancowles](https://profiles.wordpress.org/ryancowles), [richardmtl](https://profiles.wordpress.org/richardmtl), [scarstocea](https://profiles.wordpress.org/scarstocea)  
**Tags:** job manager, job listing, job board, job management, job lists, job list, job, jobs, company, hiring, employment, employer, employees, candidate, freelance, internship, job listings, positions, board, application, hiring, listing, manager, recruiting, recruitment, talent  
**Requires at least:** 4.3.1  
**Tested up to:** 4.9  
**Stable tag:** 1.29.2  
**License:** GPLv3  
**License URI:** http://www.gnu.org/licenses/gpl-3.0.html  

Manage job listings from the WordPress admin panel, and allow users to post job listings directly to your site.

## Description ##

WP Job Manager is a **lightweight** job listing plugin for adding job-board like functionality to your WordPress site. Being shortcode based, it can work with any theme (given a bit of CSS styling) and is really simple to setup.

### Features ###

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

### Documentation ###

Documentation for the core plugin and add-ons can be found [on the docs site here](https://wpjobmanager.com/documentation/). Please take a look before requesting support because it covers all frequently asked questions!

### Add-ons ###

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

### Contributing and reporting bugs ###

You can contribute code to this plugin via GitHub: [https://github.com/Automattic/WP-Job-Manager](https://github.com/Automattic/WP-Job-Manager) and localizations via [https://translate.wordpress.org/projects/wp-plugins/wp-job-manager](https://translate.wordpress.org/projects/wp-plugins/wp-job-manager)

Thanks to all of our contributors.

### Support ###

Use the WordPress.org forums for community support where we try to help all users. If you spot a bug, you can log it (or fix it) on [Github](https://github.com/Automattic/WP-Job-Manager) where we can act upon them more efficiently.

If you need help with one of our add-ons, [please raise a ticket in our help desk](https://wpjobmanager.com/support/).

If you want help with a customization, please consider hiring a developer! [http://jobs.wordpress.net/](http://jobs.wordpress.net/) is a good place to start.

## Installation ##

### Automatic installation ###

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't even need to leave your web browser. To do an automatic install, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.

In the search field type "WP Job Manager" and click Search Plugins. Once you've found the plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by clicking _Install Now_.

### Manual installation ###

The manual installation method involves downloading the plugin and uploading it to your web server via your favorite FTP application.

* Download the plugin file to your computer and unzip it
* Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation's `wp-content/plugins/` directory.
* Activate the plugin from the Plugins menu within the WordPress admin.

### Getting started ###

Once installed:

1. Create a page called "jobs" and inside place the `[jobs]` shortcode. This will list your jobs.
2. Create a page called "submit job" and inside place the `[submit_job_form]` shortcode if you want front-end submissions.
3. Create a page called "job dashboard" and inside place the `[job_dashboard]` shortcode for logged in users to manage their listings.

**Note when using shortcodes**, if the content looks blown up/spaced out/poorly styled, edit your page and above the visual editor click on the 'text' tab. Then remove any 'pre' or 'code' tags wrapping your shortcode.

For more information, [read the documentation](https://wpjobmanager.com/documentation/).

## Frequently Asked Questions ##

### How do I setup WP Job Manager? ###
View the getting [installation](https://wpjobmanager.com/document/installation/) and [setup](https://wpjobmanager.com/document/setting-up-wp-job-manager/) guide for advice getting started with the plugin. In most cases it's just a case of adding some shortcodes to your pages!

### Can I use WP Job Manager without frontend job submission? ###
Yes! If you don't setup the [submit_job_form] shortcode, you can just post from the admin backend.

### How can I customize the job application process? ###
There are several ways to customize the job application process in WP Job Manager, including using some extra plugins (some are free on Wordpress.org).

See: [Customizing the Job Application Process](https://wpjobmanager.com/document/customising-job-application-process/)

### How can I customize the job submission form? ###
There are three ways to customize the fields in WP Job Manager;

1. For simple text changes, using a localisation file or a plugin such as https://wordpress.org/plugins/say-what/
2. For field changes, or adding new fields, using functions/filters inside your theme's functions.php file: [https://wpjobmanager.com/document/editing-job-submission-fields/](https://wpjobmanager.com/document/editing-job-submission-fields/)
3. Use a 3rd party plugin such as [https://plugins.smyl.es/wp-job-manager-field-editor/](https://plugins.smyl.es/wp-job-manager-field-editor/?in=1) which has a UI for field editing.

If you'd like to learn about WordPress filters, here is a great place to start: [https://pippinsplugins.com/a-quick-introduction-to-using-filters/](https://pippinsplugins.com/a-quick-introduction-to-using-filters/)

### How can I be notified of new jobs via email? ###
If you wish to be notified of new postings on your site you can use a plugin such as [Post Status Notifier](http://wordpress.org/plugins/post-status-notifier-lite/).

### What language files are available? ###
You can view (and contribute) translations via the [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wp-job-manager).

## Screenshots ##

1. The submit job form.
2. Submit job preview.
3. A single job listing.
4. Job dashboard.
5. Job listings and filters.
6. Job listings in admin.

## Changelog ##

### 1.29.2 ###
* Fix: PHP Notice when sanitizing multiple inputs (bug in 1.29.1 release). (@albionselimaj)

### 1.29.1 ###
* Enhancement: When retrieving listings in `[jobs]` shortcode, setting `orderby` to `rand_featured` will still place featured listings at the top. (@jom)
* Enhancement: Scroll to show application details when clicking on "Apply for Job" button. (@jom)
* Change: Updates `account-signin.php` template to warn users email will be confirmed only if that is enabled. (@jom)
* Fix: Sanitize URLs and emails differently on the application method job listing field. (@jom)
* Fix: Remove PHP notice in Featured Jobs widget. (@himanshuahuja96)
* Fix: String fix for consistent spelling of "license" when appearing in strings. (@garrett-eclipse)
* Fix: Issue with paid add-on licenses not showing up when some third-party plugins were installed. (@jom)
* Dev: Runs new actions (`job_manager_recent_jobs_widget_before` and `job_manager_recent_jobs_widget_after`) inside Recent Jobs widget. (@jom)
* Dev: Change `wpjm_get_the_job_types()` to return an empty array when job types are disabled. (@jom)
* See all: https://github.com/Automattic/WP-Job-Manager/milestone/15?closed=1

### 1.29.0 ###
* Enhancement: Moves license and update management for official add-ons to the core plugin. (@jom)
* Enhancement: Update language for setup wizard with more clear descriptions. (@donnapep)
* Fix: Prevent duplicate attachments to job listing posts for non-image media. (@tripflex)
* Fix: PHP error on registration form due to missing placeholder text. (@jom)
* Fix: Apply `the_job_application_method` filter even when no default is available. (@turtlepod)
* Fix: Properly reset category selector on `[jobs]` shortcode. (@jom)

### 1.28.0 ###
* Enhancement: Improves support for Google Job Search by adding `JobPosting` structured data. (@jom)
* Enhancement: Adds ability for job types to be mapped to an employment type as defined for Google Job Search. (@jom)
* Enhancement: Requests search engines no longer index expired and filled job listings. (@jom)
* Enhancement: Improves support with third-party sitemap generation in Jetpack, Yoast SEO, and All in One SEO. (@jom)
* Enhancement: Updated descriptions and help text on settings page. (@donnapep; Props to @michelleweber for updated copy)
* Enhancement: Lower cache expiration times across plugin and limit use of autoloaded cache transients. (@jom/files) 
* Fix: Localization issue with WPML in the [jobs] shortcode. (@jom)
* Fix: Show job listings' published date in localized format. (@jom)
* Fix: Job submission form allows users to select multiple job types when they go back a step. (@jom)
* Fix: Some themes that overloaded functions would break in previous release. (@jom)
* Dev: Adds versions to template files so it is easier to tell when they are updated. (@jom)
* Dev: Adds a new `wpjm_notify_new_user` action that allows you to override default behavior. (@jom)
* Dev: Early version of REST API is bundled but disabled by default. Requires PHP 5.3+ and `WPJM_REST_API_ENABLED` constant must be set to true. Do not use in production; endpoints may change. (@pkg)

### 1.27.0 ###
* Enhancement: Admins can now allow users to specify an account password when posting their first job listing. (@jom)
* Enhancement: Pending job listing counts are now cached for improved WP Admin performance. (@tripflex)
* Enhancement: Allows users to override permalink slugs in WP Admin's Permalink Settings screen. (@jom)
* Enhancement: Allows admins to perform bulk updating of jobs as filled/not filled. (@jom)
* Enhancement: Adds job listing status CSS classes on single job listings. (@jom)
* Enhancement: Adds `wpjm_the_job_title` filter for inserting non-escaped HTML alongside job titles in templates. (@jom)
* Enhancement: Allows admins to filter by `post_status` in `[jobs]` shortcode. (@jom)
* Enhancement: Allows accessing settings tab from hash in URL. (@tripflex)
* Fix: Make sure cron jobs for checking/cleaning expired listings are always in place. (@jom)
* Fix: Better handling of multiple job types. (@spencerfinnell)
* Fix: Issue with deleting company logos from job listings submission form. (@jom)
* Fix: Warning thrown on job submission form when user not logged in. (@piersb)  
* Fix: Issue with WPML not syncing some meta fields. (@jom)
* Fix: Better handling of AJAX upload errors. (@tripflex)
* Fix: Remove job posting cookies on logout. (@jom)
* Fix: Expiration date can be cleared if default job duration option is empty. (@spencerfinnell)
* Fix: Issue with Safari and expiration datepicker. (@jom)

### 1.26.2 ###
* Fix: Prevents use of Ajax file upload endpoint for visitors who aren't logged in. Themes should check with `job_manager_user_can_upload_file_via_ajax()` if using endpoint in templates.  
* Fix: Escape post title in WP Admin's Job Listings page and template segments. (Props to @EhsanCod3r)

### 1.26.1 ###
* Enhancement: Add language using WordPress's current locale to geocode requests. (@jom)
* Fix: Allow attempts to use Google Maps Geocode API without an API key. (@spencerfinnell)
* Fix: Issue affecting job expiry date when editing a job listing. (@spencerfinnell, @jom)
* Fix: Show correct total count of results on `[jobs]` shortcode. (@jom)

### 1.26.0 ###
* Enhancement: Warn the user if they're editing an existing job. (@donnchawp)
* Enhancement: WP Admin Job Listing page's table is now responsive. (@turtlepod)
* Enhancement: New setting for hiding expired listings from `[jobs]` filter. (@turtlepod)
* Enhancement: Use WP Query's built in search function to improve searching in `[jobs]`. (@jom)
* Fix: Job Listing filter only searches meta fields with relevant content. Add custom fields with `job_listing_searchable_meta_keys` filter. (@turtlepod)
* Fix: Improved support for WPML and Polylang. (@jom)
* Fix: Expired field no longer forces admins to choose a date in the future. (@turtlepod)
* Fix: Listings with expiration date in past will immediately expire; moving to Active status will extend if necessary. (@turtlepod, @jom, https://github.com/Automattic/WP-Job-Manager/pull/975)
* Fix: Google Maps API key setting added to fix geolocation retrieval on new sites. (@jom)
* Fix: Issue when duplicating a job listing with a field for multiple file uploads. (@turtlepod)
* Fix: Hide page results when adding links in the `[submit_job_form]` shortcode. (@jom)
* Fix: Job feed now loads when a site has no posts. (@dbtlr)
* Fix: No error is thrown when deleting a user. (@tripflex)
* Dev: Plugins and themes can now retrieve JSON of Job Listings results without HTML. (@spencerfinnell)
* Dev: Updated inline documentation.

See additional changelog items in changelog.txt

## Upgrade Notice ##

### 1.25.3 ###
Make job types optional! Date format improvements! Update today!
