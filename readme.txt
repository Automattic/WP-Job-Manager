=== WP Job Manager ===
Contributors: mikejolley
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=mike.jolley@me.com&currency_code=&amount=&return=&item_name=Buy+me+a+coffee+for+A+New+Job+Board+Plugin+for+WordPress
Tags: job listing, job board, job, jobs, company
Requires at least: 3.5
Tested up to: 3.5
Stable tag: 1.0.2

Manage job listings from the WordPress admin panel, and allow users to post jobs directly to your site.

== Description ==

WP Job Manager is a _lightweight_ plugin for adding job-board functionality to your WordPress site.  Being shortcode based, it can work with any theme (given a bit of CSS styling) and is really simple to setup.

= Features =

* Add, manage and categorise job listings using the familiar WordPress UI.
* Searchable & filterable ajax powered job listings added through shortcodes.
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

Add-ons, such as __simple paid listings__ can be [found here](http://mikejolley.com/projects/wp-job-manager/add-ons/). Take a look!

= Contributing and reporting bugs =

You can contribute code and localizations to this plugin via GitHub: [https://github.com/mikejolley/wp-job-manager](https://github.com/mikejolley/wp-job-manager)

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

== Screenshots ==

1. The submit job form.
2. Submit job preview.
3. A single job listing.
4. Job dashboard.
5. Job listings and filters.
6. Job listings in admin.

== Changelog ==

= 1.0.2 =
* Action in update_job_data() to allow saving of extra fields.
* Added German translation by Chris Penning

= 1.0.1 =
* Slight tweak to listing field filters in admin.
* 'attributes' argument for admin settings.

= 1.0.0 =
* First stable release.
