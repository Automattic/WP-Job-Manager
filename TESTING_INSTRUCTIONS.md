# Testing Instructions — Issue #2414

**Fix:** Made `job_listing_category` and `job_listing_type` taxonomies always
public (`public => true`) so third-party plugins can discover them via
`get_taxonomies(['public' => true])`.

## Requirements

- WordPress 6.4+
- PHP 7.4+

## Install

1. Upload and activate the plugin zip as usual.
2. Install any third-party plugin that lists available taxonomies
   (e.g. FakerPress, CPT UI, ACF).

## Test Steps

### 1. Verify taxonomies visible to third-party plugins

1. Install FakerPress (or similar).
2. Go to FakerPress > Generate Content.
3. Click on the Post Type/Taxonomy selector.
4. **Expected:** `job_listing_category` and `job_listing_type` appear in the
   taxonomy list (previously they were missing).
5. Generate content and confirm terms are assigned correctly.

### 2. Verify no regression — frontend URLs (no theme support)

1. Use a theme that does NOT declare `add_theme_support('job-manager-templates')`.
2. Visit a job listing category or type archive URL directly
   (e.g. `/job-category/example/`).
3. **Expected:** Returns 404 (same as before — `publicly_queryable` is false
   without theme support).

### 3. Verify no regression — frontend URLs (with theme support)

1. Switch to a theme that declares `add_theme_support('job-manager-templates')`.
2. Visit a job listing category or type archive URL.
3. **Expected:** Works as before — page loads normally.

### 4. Verify admin taxonomies still work

1. Go to Job Listings > Categories (or Types).
2. Add, edit, delete terms.
3. **Expected:** All operations work as before.

### 5. Verify no nav menu clutter

1. Go to Appearance > Menus.
2. Check the taxonomy panels under "Add menu items".
3. **Expected:** Job Categories and Job Types do NOT appear as nav menu options
   (controlled by `show_in_nav_menus => false`).
