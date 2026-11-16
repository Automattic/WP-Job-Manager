# 0002 — The single listing template is rendered apart from the content filters

- **Status:** accepted
- **Date:** 2026-11-16

## Context

On a single listing page the plugin replaces the post content with the whole listing template,
from a `the_content` filter at priority 10. Everything the template prints is then handed to
the filters that run after it, as if it were post content: WordPress expands shortcodes at
priority 11, and themes, page builders and other plugins often expand them again later.

The template is not post content. It prints listing fields, the application area, forms added
by extensions, and sometimes values from the current request. Each of those had to be prepared
so that it would survive being processed as content, one field and one way of writing a bracket
at a time ([0001](0001-listing-content-trust.md)). That holds only as long as every template,
every extension and every theme override remembers to do it, for every form the text can take.

## Decision

**The rendered template does not pass through the other content filters.**

- `WP_Job_Manager_Post_Types::job_content()` renders the template, holds the result, and
  returns a placeholder element named with a random token made for that one render. The later
  filters see the placeholder. A filter at the last priority
  (`restore_single_job_content()`) puts the rendered template in its place.
- **Only the description of a trusted listing is searched for shortcodes**, and only once.
  `wpjm_the_job_description()` expands them after the description has been through the
  listing's HTML filter, which is the order they were processed in before. A template that
  passes the description through `the_job_description` itself, as older templates did, gets
  them expanded by that filter. Trust is decided as in 0001.
- Nothing else the template prints is searched for shortcodes, **whoever wrote the listing**.
  A trusted author's company name is text, and so is anything an extension prints.
- What the later filters did that is still wanted is done directly on the rendered template:
  the media pass for untrusted listings, and `wp_filter_content_tags()` for images.
- The field-level preparation from 0001 stays. Templates are also printed by shortcodes, in
  lists and in widgets, and a site can turn this decision off.
- `job_manager_isolate_single_job_content` turns it off for a site or a listing. The template
  then passes through the content filters as it did before 2.4.12.

## Consequences

- A field, a form or a request value that is printed in the template can't be run as a
  shortcode, without the code that prints it having to do anything.
- **Shortcodes outside the description are no longer expanded on the single listing page.**
  This includes shortcodes typed into the fields of a listing by a trusted author, written
  into a theme's copy of the template, or printed from a hook such as
  `single_job_listing_end`. Code that wants a shortcode expanded there calls `do_shortcode()`
  on its own text.
- Filters that change post content after priority 10 no longer change the listing template.
  Content they add before or after it is kept in place.
- A filter that replaces the content, such as one that restricts access, removes the
  placeholder with it, and the listing is not shown, as before. A filter that keeps part of
  the content keeps the placeholder or drops it whole.

## Keeping it true

- `tests/php/tests/security/test_class.single-listing-content-processing.php` checks that
  nothing but a trusted description is expanded, for listings by every kind of author, with
  text from fields, from the request, from hooks and from a later filter that expands
  shortcodes again.
- `tests/php/tests/security/test_class.listing-field-sweep.php` checks every field of the
  submission form on every template.
