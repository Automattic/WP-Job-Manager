=== WP Job Manager ===
Contributors: mikejolley
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=mike.jolley@me.com&currency_code=&amount=&return=&item_name=Buy+me+a+coffee+for+A+New+Job+Board+Plugin+for+WordPress
Tags: job listing, job board, job, jobs, company, hiring, employment, employees, candidate, freelance, internship
Requires at least: 3.8
Tested up to: 3.9
Stable tag: 1.12.1

Manage job listings from the WordPress admin panel, and allow users to post jobs directly to your site.

== Description ==

WP Job Manager is a **lightweight** plugin for adding job-board functionality to your WordPress site. Being shortcode based, it can work with any theme (given a bit of CSS styling) and is really simple to setup.

= Features =

* Add, manage, and categorise job listings using the familiar WordPress UI.
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

Documentation for the core plugin and add-ons can be found [on the docs site here](https://wpjobmanager.com/documentation/).

= Add-ons =

Additonal functionality can be added through add-ons - you can browse these after installing the plugin by going to `Job Listings > Add-ons`.

Some notable add-ons include:

* [Simple Paid Listings](https://wpjobmanager.com/add-ons/simple-paid-listings/) - Charge users a single fee to post a job via Stripe or PayPal.
* [WooCommerce Paid Lisings](https://wpjobmanager.com/add-ons/wc-paid-listings/) - Charge users to post jobs using WooCommerce to take payment.
* [Job Alerts](https://wpjobmanager.com/add-ons/job-alerts/) - Add saved search/email alert functionality.
* [Resume Manager](https://wpjobmanager.com/add-ons/resume-manager/) - Add a resume submission area for employers to browse.

= Contributing and reporting bugs =

You can contribute code to this plugin via GitHub: [https://github.com/mikejolley/wp-job-manager](https://github.com/mikejolley/wp-job-manager) and localizations via Transifex: [https://www.transifex.com/projects/p/wp-job-manager/](https://www.transifex.com/projects/p/wp-job-manager/)

= Support =

Use the WordPress.org forums for community support - I cannot offer support directly for free. If you spot a bug, you can of course log it on [Github](https://github.com/mikejolley/wp-job-manager) instead where I can act upon it more efficiently.

If you want help with a customisation, hire a developer! [http://jobs.wordpress.net/](http://jobs.wordpress.net/) is a good place to start.

== Installation ==

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't even need to leave your web browser. To do an automatic install, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.

In the search field type "WP Job Manager" and click Search Plugins. Once you've found the plugin you can view details about it such as the the point release, rating and description. Most importantly of course, you can install it by clicking _Install Now_.

= Manual installation =

The manual installation method involves downloading the plugin and uploading it to your webserver via your favourite FTP application.

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

== Screenshots ==

1. The submit job form.
2. Submit job preview.
3. A single job listing.
4. Job dashboard.
5. Job listings and filters.
6. Job listings in admin.

== Changelog ==

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
