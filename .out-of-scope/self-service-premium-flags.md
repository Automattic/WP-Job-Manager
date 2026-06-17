# Self-Service Premium Flags

This project does not, by default, expose premium / paid-tier listing flags (Featured, Promoted, etc.) as toggles on the *frontend* job submission form.

## Why this is out of scope

Premium flags exist in WP Job Manager as plain post-meta in core (e.g. `_featured` on `job_listing`), but the **paid-tier business model is enforced at the UI layer** — by which surfaces expose the toggle to which users. The intended flow is:

- **Backend / `edit_others_posts`-capable users** (site admins, editors) can toggle Featured directly on the post edit screen.
- **Submitter-facing flows** that need Featured as a *paid* upgrade go through the [WC Paid Listings add-on](https://wpjobmanager.com/add-ons/wc-paid-listings/), which mediates the upgrade as a WooCommerce product purchase.

Adding a default "Featured" checkbox to the frontend submission form would:

1. Let any submitter on a free install get the Featured perk (top-of-list ranking, highlighting) at no cost, undermining the paid-upgrade boundary that the WC Paid Listings add-on depends on.
2. Conflict with WC Paid Listings on commercial sites — the add-on assumes Featured is something a submitter *purchases*, not something they tick.
3. Push the premium boundary into core code rather than keeping it as an add-on concern.

The same reasoning applies to other premium flags such as Promoted Jobs — surfacing them as a free toggle on submitter-facing forms would defeat their purpose.

## What we won't ship in core

- A built-in Featured (or Promoted) checkbox on the frontend submission form.
- A core settings toggle that exposes premium flags to submitters.
- Documentation or examples that help end-users bypass the WC Paid Listings purchase flow.

Sites with a *genuine* internal need (e.g. an in-house editorial team submitting jobs via the frontend instead of `/wp-admin/` because they don't have admin access) should add the field themselves with capability checks matched to their team's roles — they don't need it documented here, and we won't ship a recipe for it.

## Prior requests

- [#2327](https://github.com/Automattic/WP-Job-Manager/issues/2327) — "Request Featured Checkbox on the Front End Post Page"
