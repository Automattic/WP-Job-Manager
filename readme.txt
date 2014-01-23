=== WP Job Manager ===
Contributors: mikejolley
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=mike.jolley@me.com&currency_code=&amount=&return=&item_name=Buy+me+a+coffee+for+A+New+Job+Board+Plugin+for+WordPress
Tags: job listing, job board, job, jobs, company, hiring, employment, employees, candidate, freelance, internship
Requires at least: 3.8
Tested up to: 3.8
Stable tag: 1.7.1

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

[Read more about WP Job Manager](http://mikejolley.com/projects/wp-job-manager/).

= Documentation =

Documentation will be maintained on the [GitHub Wiki here](https://github.com/mikejolley/wp-job-manager/wiki).

= Add-ons =

Additonal functionality can be added through add-ons - you can browse these after installing the plugin by going to `Job Listings > Add-ons`.

Some notable add-ons include:

* [Simple Paid Listings](http://mikejolley.com/projects/wp-job-manager/add-ons/simple-paid-listings/) - Charge users a single fee to post a job via Stripe or PayPal.
* [WooCommerce Paid Lisings](http://mikejolley.com/projects/wp-job-manager/add-ons/woocommerce-paid-listings/) - Charge users to post jobs using WooCommerce to take payment.
* [Job Alerts](http://mikejolley.com/projects/wp-job-manager/add-ons/job-alerts/) - Add saved search/email alert functionality.

= Contributing and reporting bugs =

You can contribute code to this plugin via GitHub: [https://github.com/mikejolley/wp-job-manager](https://github.com/mikejolley/wp-job-manager) and localizations via Transifex: [https://www.transifex.com/projects/p/wp-job-manager/](https://www.transifex.com/projects/p/wp-job-manager/)

= Support =

Use the WordPress.org forums for community support - I cannot offer support directly for free. If you spot a bug, you can of course log it on [Github](https://github.com/mikejolley/wp-job-manager) instead where I can act upon it more efficiently.

If you want help with a customisation, hire a developer!

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

For more information, [read the documentation](https://github.com/mikejolley/wp-job-manager/wiki).

== Screenshots ==

1. The submit job form.
2. Submit job preview.
3. A single job listing.
4. Job dashboard.
5. Job listings and filters.
6. Job listings in admin.

== Changelog ==

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
