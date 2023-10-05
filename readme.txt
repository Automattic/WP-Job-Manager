=== WP Job Manager ===
Contributors: mikejolley, automattic, adamkheckler, alexsanford1, annezazu, cena, chaselivingston, csonnek, davor.altman, donnapep, donncha, drawmyface, erania-pinnera, fjorgemota, jacobshere, jakeom, jeherve, jenhooks, jgs, jonryan, kraftbj, lamdayap, lschuyler, macmanx, nancythanki, orangesareorange, rachelsquirrel, renathoc, ryancowles, richardmtl, scarstocea
Tags: job manager, job listing, job board, job management, job lists, job list, job, jobs, company, hiring, employment, employer, employees, candidate, freelance, internship, job listings, positions, board, application, hiring, listing, manager, recruiting, recruitment, talent
Requires at least: 6.1
Tested up to: 6.3
Requires PHP: 7.2
Stable tag: 1.42.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Create a careers page for your company website, or build a public job board for your community. 

== Description ==

WP Job Manager is a **lightweight** job listing plugin for adding job-board like functionality to your WordPress site. Being shortcode based, it can work with any theme (given a bit of CSS styling) and is really simple to setup.

= Features =

* Add, manage, and categorize job listings using the familiar WordPress UI.
* Post jobs on your own site, then promote them across a worldwide job network — on LinkedIn, Indeed and more.
* Searchable & filterable ajax powered job listings added to your pages via shortcodes.
* Frontend forms for guests and registered users to submit & manage job listings.
* Allow job listers to preview their listing before it goes live. The preview matches the appearance of a live job listing.
* Each listing can be tied to an email or website address so that job seekers can apply to the jobs.
* Searches also display RSS links to allow job seekers to be alerted to new jobs matching their search.
* Allow logged in employers to view, edit, mark filled, or delete their active job listings.
* Developer friendly code - Custom Post Types, endpoints & template files.

The plugin comes with several shortcodes to output jobs in various formats, and since its built with Custom Post Types you are free to extend it further through themes.

[Read more about WP Job Manager](https://wpjobmanager.com/).

= Documentation =

Documentation for the core plugin and add-ons can be found [on the docs site here](https://wpjobmanager.com/documentation/). Please take a look before requesting support because it covers all frequently asked questions!

= Demo =

For a real-life example site, check out [jobs.blog](https://jobs.blog), built by the WP Job Manager team!

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

== Changelog ==

== Screenshots ==

1. The submit job form.
2. Submit job preview.
3. A single job listing.
4. Job dashboard.
5. Job listings and filters.
6. Job listings in admin.

== Changelog ==

## 1.42.0 - 2023-10-05
New!

* Easily promote job listings on Indeed, LinkedIn, and 1000s of job boards with JobTarget integration. See https://wpjobmanager.com/jobtarget for more information.

Improvements:

* Fix: Only show file upload input for company logo when it's empty (#2569)
* Fix: Fix error when showing admin notices (#2557)
* Fix: Show the links (RSS, Reset) below search even when there are no results (#2454)
* Tweak: Improve usage tracking for plugins (#2576)

For developers:

* Fix: In forms, support dynamically added date inputs (#2573)
* New: Allow plugins to override renewal values (#2566)
* Tweak: Rename "licence" to "license" throughout codebase (#2554)
* Fix: More efficient license checking for core add-ons (#2552)

## 1.41.0 - 2023-06-10
* Enhancement: Introduce listing renewals #2422
* Enhancement: Allow searching multiple job locations at once #2433
* Fix: Add check for job listing limit being 0 #2362
* Fix: Trigger search when submitting search form #2457
* Fix: Always show search actions #2454
* Fix: Fix PHP 8.2 deprecations #2456
* Fix: Make forms responsive #2453
* Fix: Update $company_tagline check for PHP8+ #2430
* Fix:  Update notice string from 'c' to 's' license spelling #2429
* Change: Update WPJM logo #2482
* Change: Add hook in job-submitted template #2458
* Change: Run job_manager_job_submitted_content filter in every case #2474
* Change: Update historical notices to use the new render_notice method. #2409

## 1.40.2 - 2023-05-26
* Revert: Hide expired job listings from archives

## 1.40.1 - 2023-05-12
* Fix: Icons not displaying properly

## 1.40.0 - 2023-05-11
* Enhancement: Improve licensing page experience
* Enhancement: New email notifications setting
* Enhancement: Improve discoverability of addon updates
* Enhancement: New admin notices sytem with redesign
* Fix: "Hide Expired Listings Content" setting
* Fix: Hide expired job listings from archives
* Fix: Show license notice only to users who can manage licenses
* Fix: Select menu overlapping in Job Listing frontend
* Fix: Company video overlapping when no tagline set
* Change: Update tipTip library to use WooCommerce’s version
* Change: Bumped minimum PHP version from 7.2 to 7.4

