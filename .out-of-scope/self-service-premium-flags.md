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

## The supported escape hatch

Sites that legitimately want to expose Featured (or any other field) on the frontend form for *their own* trusted users (e.g. an in-house editorial team submitting via the frontend instead of `/wp-admin/`) can already do this via the `submit_job_form_fields` filter:

```php
add_filter( 'submit_job_form_fields', function ( $fields ) {
    $fields['job']['featured'] = [
        'label'       => __( 'Featured', 'mytheme' ),
        'type'        => 'checkbox',
        'required'    => false,
        'priority'    => 11,
    ];
    return $fields;
} );

// And save it on submission (capability-gated):
add_action( 'job_manager_update_job_data', function ( $job_id, $values ) {
    if ( ! current_user_can( 'edit_others_posts' ) ) {
        return;
    }
    if ( ! empty( $values['job']['featured'] ) ) {
        update_post_meta( $job_id, '_featured', 1 );
    }
}, 10, 2 );
```

Capability-gating the save (as above) is essential — it prevents the free-tier abuse path while supporting the internal-team use case the requesters typically have in mind.

## Prior requests

- [#2327](https://github.com/Automattic/WP-Job-Manager/issues/2327) — "Request Featured Checkbox on the Front End Post Page"
