=== WP Job Manager ===
Contributors: mikejolley, automattic, adamkheckler, alexsanford1, annezazu, cena, chaselivingston, csonnek, davor.altman, donnapep, donncha, drawmyface, erania-pinnera, fjorgemota, jacobshere, jakeom, jeherve, jenhooks, jgs, jonryan, kraftbj, lamdayap, lschuyler, macmanx, nancythanki, orangesareorange, rachelsquirrel, renathoc, ryanc413, richardmtl, scarstocea
Tags: jobs, careers, company, hiring, job board
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.4.7
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Create a careers page for your company website, or build a public job board for your community. 

== Description ==

WP Job Manager is a **lightweight** job listing plugin for adding job board functionality to your WordPress site. Being shortcode based, it can work with any theme (given a bit of CSS styling) and is really simple to setup.

= Features =

* Add, manage, and categorize job listings using the familiar WordPress UI.
* Searchable & filterable ajax powered job listings added to your pages via shortcodes.
* Frontend forms for guests and registered users to submit & manage job listings.
* Allow job listers to preview their listing before it goes live. The preview matches the appearance of a live job listing.
* Each listing can be tied to an email or website address so that job seekers can apply to the jobs.
* Searches also display RSS links to allow job seekers to be alerted to new jobs matching their search.
* Allow logged in employers to view, edit, mark filled, or delete their active job listings.
* Job statistics for employers about job listing views and search impressions.
* Developer friendly code - Custom post types, endpoints & template files.

The plugin comes with several shortcodes to output jobs in various formats, and since its built with Custom Post Types you are free to extend it further through themes.

[Read more about WP Job Manager](https://wpjobmanager.com/).

= Documentation =

Documentation for the core plugin and extensions can be found [on the docs site here](https://wpjobmanager.com/documentation/). Please take a look before requesting support because it covers all frequently asked questions!

= Demo =

For a real-life example site, check out [jobs.blog](https://jobs.blog), built by the WP Job Manager team! To try out the plugin in an expendable demo site, click the Live Preview button above.

= Extensions =

The core WP Job Manager plugin is free and always will be. It covers all functionality we consider 'core' to running a simple job board site.

Additional, advanced functionality is available through extensions. Not only do these extend the usefulness of the core plugin, they also help fund the development and support of core.

You can browse available extensions after installing the plugin by going to `Job Manager > Marketplace`. Our popular extensions include:

**[Applications](https://wpjobmanager.com/add-ons/applications/)**

Allow candidates to apply to jobs using a form & employers to view and manage the applications from their job dashboard.

**[WooCommerce Paid Listings](https://wpjobmanager.com/add-ons/wc-paid-listings/)**

Paid listing functionality powered by WooCommerce. Create custom job packages which can be purchased or redeemed during job submission. Requires the WooCommerce plugin.

**[Resume Manager](https://wpjobmanager.com/add-ons/resume-manager/)**

Resume Manager is a plugin built on top of WP Job Manager which adds a resume submission form to your site and resume listings, all manageable from WordPress admin.

**[Job Alerts](https://wpjobmanager.com/add-ons/job-alerts/)**

Allow registered users to save their job searches and create alerts which send new jobs via email daily, weekly or fortnightly.

**[Job Manager Pro Bundle](https://wpjobmanager.com/add-ons/bundle/)**

You can get the above extensions and several others at discount with our [WPJM Pro Bundle](https://wpjobmanager.com/add-ons/bundle/). Take a look!

= Contributing and reporting bugs =

You can contribute code to this plugin via GitHub: [https://github.com/Automattic/WP-Job-Manager](https://github.com/Automattic/WP-Job-Manager) and localizations via [https://translate.wordpress.org/projects/wp-plugins/wp-job-manager](https://translate.wordpress.org/projects/wp-plugins/wp-job-manager)

Thanks to all of our contributors.

= Support =

Use the WordPress.org forums for community support where we try to help all users. If you spot a bug, you can log it (or fix it) on [Github](https://github.com/Automattic/WP-Job-Manager) where we can act upon them more efficiently.

If you need help with one of our extensions, [please raise a ticket in our help desk](https://wpjobmanager.com/support/).

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

If you'd like to learn about WordPress filters, here is a great place to start: [https://developer.wordpress.org/plugins/hooks/filters/](https://developer.wordpress.org/plugins/hooks/filters/)

= How can I be notified of new jobs via email? =
If you wish to be notified of new postings on your site you can use a plugin such as [Post Status Notifier](http://wordpress.org/plugins/post-status-notifier-lite/).

= What language files are available? =
You can view (and contribute) translations via the [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wp-job-manager).

= How do I add the Recent Jobs or Featured Jobs widgets on a block theme? =
Block themes have no classic Widgets screen, but you can still add the Recent Jobs and Featured Jobs widgets through the Site Editor. Go to **Appearance → Editor**, open a template or template part, add the **Legacy Widget** block from the inserter, and choose **Recent Jobs** or **Featured Jobs**. The widget's settings and preview appear right in the editor, just as they do on a classic theme.

== Screenshots ==

1. The submit job form.
2. Submit job preview.
3. A single job listing.
4. Job dashboard.
5. Job listings and filters.
6. Job listings in admin.

== Changelog ==

### 2.4.7 - 2026-09-03
* Fix company logo rendering at full size in the Job Listings admin table on WordPress 7.1. (#3099)
* Indicate WordPress 7.1 compatibility. (#3097)
* Fix JobPosting structured data (JSON-LD) being HTML-escaped, so job titles and descriptions are read correctly by search engines. (#3101)
* Fix a race condition that could allow the job submission limit to be exceeded. (#3013)
* Apply the View Job Capability to the promoted-jobs REST endpoints.
* Apply the View Job Capability to sitemaps.
* Restrict frontend logo attachments to the uploads directory.
* Clear unusable attachment values before re-rendering the job submission form.
* Update developer dependencies.

### 2.4.6 - 2026-08-19
* Allow reusing a submitter's saved company logo on a new listing (#3082)
* Allow reusing a listing's existing logo on edit when authored by another user (#3060)
* Fix notice dismiss button colliding with core styles (#3036)
* Fix unreadable notice-banner button text on Settings (#3029)
* Decode HTML entities in plain-text job title contexts (#3026)
* Indicate WordPress 7.0 compatibility (#3015)
* Verify the TLS certificate on the geocoding request (#3014)
* Exclude password-protected listings from the promoted jobs feed (#3012)
* Only accept a manual listing expiry from the gated admin edit (#3011)
* Prevent the edit-job form from creating a new listing for an unauthorized or logged-out request.

* Enforce company-logo attachment ownership when saving a job listing as a draft.

* Apply the post-password check to the `[job]` and `[job_apply]` shortcodes.

### 2.4.5 - 2026-07-01
* Escape URLs in two sprintf href templates with esc_url() (#2996)
* Validate attachment ownership when reusing a company logo by ID (#2995)
* Honor restrictive filter_post_status=publish for logged-out visitors (#2994)

### 2.4.4 - 2026-06-24
* Fix: Restore the frontend styles and scripts that were missing from the 2.4.3 package. The 2.4.3 release was built without its compiled CSS and JavaScript, leaving the employer job dashboard unstyled and breaking frontend features such as the job application form. (#2990)

### 2.4.3 - 2026-06-24
* Apply the View Job Capability to WordPress search and oEmbed output.
* Apply the View Job Capability to the REST search endpoint (#2984)
* Fail closed correctly when an author filter resolves to no valid IDs (#2983)
* Apply the View Job Capability to job listing feeds (#2982)
* Enforce listing submission limit when publishing a previewed listing (#2981)
* Restore PHP 8.4 test coverage — modernise bootstrap error handler (#2970)
* Fix: Promote button `data-href` attribute now correctly quoted in job listings table.
* Fix: Promote URL in inline script now escaped with `esc_js()`.
* Author filter follow-up: centralize parsing, close AJAX array bypass (#2958)
* Add square-format hint and configurable max size for company logo uploads (#2957)
* "Remove "See what's new in 2.3" from Job Statistics Banner"
* New: The `[jobs]` shortcode now supports an `author` attribute to filter listings by user ID (e.g. `[jobs author="42"]` or `[jobs author="1,2,3"]`)
