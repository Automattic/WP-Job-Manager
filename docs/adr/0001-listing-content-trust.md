# 0001 — Listing content trust follows the author's listing-editing capability

- **Status:** accepted
- **Date:** 2026-11-06

## Context

Job listings are written in two very different ways. Site staff create and edit them in
wp-admin, and anyone a site allows — employers, subscribers, or logged-out visitors — can
create them through the frontend submission forms.

Displayed post content is powerful in WordPress: shortcodes are expanded, blocks are
rendered, and media elements build interactive players out of their markup. For a listing
written by the site's own staff these are features. For a listing submitted by an arbitrary
visitor they are a way to run site functionality the visitor was never granted — a frontend
submitter should end up with formatted text, not with the capabilities of the templates
their text happens to pass through.

WordPress's native boundary for raw markup is the `unfiltered_html` capability, but it is a
poor fit here: it is unavailable to everyone but super admins on multisite, is often removed
with `DISALLOW_UNFILTERED_HTML`, and does not describe the people who actually manage
listings. The plugin needed one predictable rule for when a listing's content may use
shortcodes, blocks, and media.

## Decision

**A listing's content is trusted when its author can edit job listings.**

- The check is `user_can( $post->post_author, 'edit_job_listings' )`
  (`WP_Job_Manager_Post_Types::CAP_EDIT_LISTINGS`), implemented in
  `WP_Job_Manager_Post_Types::is_trusted_listing()`. `edit_job_listings` is the same
  capability that gates managing listings elsewhere in the plugin, so the people who can
  curate the board are the people whose markup is honored.
- Sites can widen or narrow the boundary with the
  `job_manager_job_description_allow_shortcodes` filter. The filter also runs at submission
  time with a null post, where the check is on the submitting user.
- Untrusted content is **restricted, both where it is displayed and when it is submitted**
  (`WP_Job_Manager_Post_Types::restrict_content_html()`): it is limited to a kses allowlist
  without media elements or iframes, block delimiters are dropped, and bracket characters
  are encoded so nothing left in the text is expanded later.
- Shortcodes in untrusted content are **unwrapped, never passed to `strip_shortcodes()`**
  (`job_manager_unwrap_shortcodes()`): the tags are removed but the text inside enclosing
  shortcodes is kept. Stripping deletes that inner text, and at submission time the loss
  would be permanent.
- Short submitter-controlled fields shown by the templates (company name, tagline,
  location) are displayed as the text that was entered when the listing is untrusted
  (`wpjm_esc_listing_text()`).

## Consequences

- Listings authored by users who can edit job listings keep their shortcodes, blocks, and
  media everywhere they render, including feeds and the REST API.
- Untrusted listings show that markup as plain text. Author-written media markup is reduced
  to links; the company video player, which the site itself builds from a plain URL field,
  is still rendered.
- Trust is evaluated against the listing's **current** author, so changing a listing's
  author changes how its stored content renders.
- Content restricted at submission time stays restricted text even if its author is later
  granted the capability; the author can re-save the content once it is trusted.
- Every add-on form that extends `WP_Job_Manager_Form` inherits the submission-time
  restriction for its textarea and editor fields.
- A site that wants frontend submitters to use shortcodes opts in explicitly through the
  filter, accepting the exposure for its own configuration.
