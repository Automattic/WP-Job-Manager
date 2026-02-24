# Fix: Unauthenticated Sensitive Data Exposure via `filter_post_status`

## Context

The `job_manager_get_listings` AJAX action (registered on `wp_ajax_nopriv_*`) accepts a `filter_post_status` parameter from the request without any authorization check. An unauthenticated attacker can pass `filter_post_status[]=draft&filter_post_status[]=pending&filter_post_status[]=preview` to retrieve unpublished listings.

The parameter flows unchecked through:
1. `class-wp-job-manager-ajax.php:129` — reads from `$_REQUEST`
2. `class-wp-job-manager-ajax.php:155` — passes to query args
3. `wp-job-manager-functions.php:50-56` — uses it as-is in `WP_Query`

## Legitimate use

The `post_status` shortcode attribute (e.g. `[jobs post_status="expired"]`) allows site admins to display expired listings on the frontend. The JS reads this from a `data-post_status` attribute and sends it back as `filter_post_status`. So there are legitimate cases where non-`publish` statuses are requested — but only `publish` and `expired`.

## Fix

**File: `includes/class-wp-job-manager-ajax.php`** (~line 129)

After reading `$filter_post_status` from the request, restrict it to publicly-visible statuses for unauthenticated/unprivileged users:

```php
$filter_post_status = isset( $_REQUEST['filter_post_status'] ) ? array_filter( array_map( 'sanitize_title', wp_unslash( (array) $_REQUEST['filter_post_status'] ) ) ) : null;

if ( ! is_null( $filter_post_status ) ) {
    $allowed_statuses = [ 'publish', 'expired' ];

    /**
     * Filters the post statuses allowed in public listing queries.
     *
     * @since 2.4.1
     *
     * @param string[] $allowed_statuses Statuses allowed for unauthenticated/unprivileged requests.
     */
    $allowed_statuses = apply_filters( 'job_manager_public_query_post_statuses', $allowed_statuses );

    $filter_post_status = array_intersect( $filter_post_status, $allowed_statuses );

    if ( empty( $filter_post_status ) ) {
        $filter_post_status = null;
    }
}
```

This:
- Allows only `publish` and `expired` through for all users (matching the only legitimate frontend use case)
- Provides a filter (`job_manager_public_query_post_statuses`) for extensions that may need to expose additional statuses
- Falls back to default behavior (which is `publish`, or `publish` + `expired` depending on settings) when no valid statuses remain

## Why not check `current_user_can`?

The shortcode's `post_status` attribute is a site-admin design choice baked into page content — it doesn't correspond to the requesting user's capabilities. The correct fix is to allowlist the statuses that are safe to show publicly, regardless of who is requesting. `publish` and `expired` are the only statuses that should ever appear on the public frontend.

## Verification

1. Activate the plugin, create a draft job listing with sensitive content
2. As a logged-out user, request: `?action=job_manager_get_listings&filter_post_status[]=draft`
3. Confirm draft listings are **not** returned
4. Confirm `?action=job_manager_get_listings` (no filter) still returns published listings
5. Confirm `?action=job_manager_get_listings&filter_post_status[]=expired` returns expired listings (when the "hide expired" setting is off)
6. Run existing tests: `make test` or `phpunit`
