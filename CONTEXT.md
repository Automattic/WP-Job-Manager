# WP Job Manager — domain context

Core vocabulary for this codebase. Decisions live in [`docs/adr/`](docs/adr/); agent
conventions in [`AGENTS.md`](AGENTS.md).

## Glossary

- **Job listing** — the `job_listing` custom post type (`WP_Job_Manager_Post_Types::PT_LISTING`).
  Registers two custom statuses: `expired` and `preview`.
- **Listing author** — the WordPress user in `post_author`. `0` for listings submitted by
  logged-out visitors on sites that allow it.
- **Submission form** — the frontend form (`[submit_job_form]`,
  `WP_Job_Manager_Form_Submit_Job`, extending the abstract `WP_Job_Manager_Form`) through
  which visitors create and edit listings without wp-admin. Add-ons extend the same
  abstract for their own forms.
- **Trusted listing** — a listing whose content may use shortcodes, blocks, and media
  because its author can `edit_job_listings`. See
  [ADR 0001](docs/adr/0001-listing-content-trust.md); the boundary is filterable via
  `job_manager_job_description_allow_shortcodes`.
- **Restricted content** — how an untrusted listing's content is stored and displayed:
  limited kses allowlist, shortcodes unwrapped to their inner text, block delimiters
  dropped, brackets encoded. Produced by
  `WP_Job_Manager_Post_Types::restrict_content_html()`.
- **Listing fields** — the structured meta shown by the templates (`_company_name`,
  `_job_location`, `_application`, …), distinct from the listing's post content. Template
  functions in `wp-job-manager-template.php` display them.
- **Expired listing** — a listing past its expiry. By default
  (`job_manager_hide_expired_content`) its body is withheld from visitors everywhere it is
  served; the title remains visible.
