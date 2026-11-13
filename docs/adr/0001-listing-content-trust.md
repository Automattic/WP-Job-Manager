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
  location, salary, salary currency, X / Twitter username) are displayed as the text that
  was entered when the listing is untrusted (`wpjm_esc_listing_text()`).
- Brackets are encoded in the zero-padded form, `&#091;` and `&#093;`
  (`WP_Job_Manager_Post_Types::encode_brackets()`). WordPress uses the unpadded form as its
  own placeholder while it expands shortcodes and turns it back into a bracket afterwards,
  so text encoded that way does not stay encoded.
- Inside HTML tags, brackets are percent-encoded instead
  (`restrict_content_html()`). A character reference only holds in text: the HTML filter
  decodes attribute values and writes them back, and WordPress expands shortcodes it finds
  in attribute values. Restricted content keeps being processed after it is restricted, so
  the restriction has to survive the HTML filter, embeds, and more than one shortcode pass.
- **A URL that a media player or embed is built from is listing data, whoever builds the
  markup around it.** Players read the URL back from the page and build their own interface
  from it, so escaping it for the attribute is not enough: it must be a plain URL however
  it is decoded. This does not depend on trust, because a URL that fails is never a working
  media URL:
  - the URL is checked where it is saved and again where it is read
    (`WP_Job_Manager_Post_Types::sanitize_media_url()`, used by `get_the_company_video()`);
  - markup built from listing data is printed, and kept through the media pass, only when
    nothing in its media and URL attributes decodes to markup
    (`WP_Job_Manager_Post_Types::is_inert_media_markup()`, enforced inside
    `protect_listing_media_output()`). Keeping generated markup through the media pass is an
    exemption from a safety check, so the exemption carries its own check.
- For an untrusted listing, embeds come only from the providers WordPress already knows,
  for the company video and for addresses in the description alike. The site does not fetch
  a URL the listing's author chose in order to discover an embed, and an embed that was
  discovered and cached earlier is shown as a link.

## Consequences

- Listings authored by users who can edit job listings keep their shortcodes, blocks, and
  media everywhere they render, including feeds and the REST API.
- Untrusted listings show that markup as plain text. Author-written media markup is reduced
  to links; the company video player, which the site itself builds from a plain URL field,
  is still rendered when that field holds a plain URL.
- A company video URL that is not a plain URL shows no player, for any listing. A company
  video on an untrusted listing that relied on embed discovery (a site that is not one of
  WordPress's known providers) shows no embed.
- Trust is evaluated against the listing's **current** author, so changing a listing's
  author changes how its stored content renders.
- Content restricted at submission time stays restricted text even if its author is later
  granted the capability; the author can re-save the content once it is trusted.
- Every add-on form that extends `WP_Job_Manager_Form` inherits the submission-time
  restriction for its textarea and editor fields.
- A site that wants frontend submitters to use shortcodes opts in explicitly through the
  filter, accepting the exposure for its own configuration.

## Keeping it true

Two misses led to this section: a field was left out when the others were covered, and
generated markup was exempted from a check on the grounds that the plugin had built it.
Both are the kind of mistake a reviewer cannot be relied on to catch, so a test does it:

- `tests/php/tests/security/test_class.listing-field-sweep.php` reads the fields from the
  submission form, fills every one with text that would be processed if it were printed
  unprepared, and renders the listing through every template. A new field is covered
  without a new test. A new template that prints listing fields should be added to its list.
- `tests/php/tests/security/test_class.listing-description-processing.php` checks the
  restricted description after the steps that follow the restriction, not only the output
  of the restriction itself.
- Anything new that builds markup from a listing field and needs to survive the media pass
  goes through `protect_listing_media_output()`, and so through its check. Do not add a
  second way around the media pass.

