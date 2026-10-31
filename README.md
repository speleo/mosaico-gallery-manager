# Mosaico Gallery Manager (CiviCRM / WordPress)

**Version:** 0.2.7-beta8 — staging release; do not deploy on a production CiviCRM installation without testing against its exact version.

Manage the existing CiviCRM Mosaico image directory without deleting, renaming, or moving images. Works independently of the WordPress Media Library; it manages assets that Mosaico already references.

## Features

- Browser-based image gallery with name search, 60-image pages, active/archived/favourites views.
- Sort newest first, most used, favourites first, or filename.
- One-click archive/restore and favourite/unfavourite — **database flags only**.
- Reference-index refresh counts distinct stored `civicrm_mailing` HTML and `civicrm_mosaico_template` HTML/metadata references. It does not count individual email recipients, opens, or clicks.
- The editor integration uses Mosaico's documented `hook_civicrm_mosaicoScriptUrlsAlter` and a jQuery AJAX **response filter**, applying archive flags and chosen sort to the GET image list. It leaves upload POSTs, image renderers, and existing mailing HTML unmodified.
- In the Mosaico editor, choose group and sort order from controls **inside the Gallery tab**, directly above images; changing either refreshes the gallery's live thumbnails and pager immediately. Admins can open the management page in a new tab.
- Existing sent emails and template images continue to use their original URLs.

## Requirements

- CiviCRM with Mosaico extension enabled, running on WordPress (also designed to work on other CiviCRM CMSs).
- PHP 7.4+ (PHP 8.1+ recommended), MySQL/MariaDB (uses `utf8mb4`), CiviCRM 5.70+.
- A standard Mosaico upload catalogue returning `{ "files": [...] }` through jQuery. This part **must be tested** with your site's installed Mosaico version.
- `administer CiviCRM` permission to use management page, `access CiviMail` or administrator permission for the editor integration.

## Install in CiviCRM on WordPress

1. **Back up the database and the entire Mosaico image directory**, and test on a staging clone first.
2. Expand the ZIP. Upload the enclosed `org.bec.mosaicogallery` folder to CiviCRM's configured **Extensions Directory** (typically `wp-content/uploads/civicrm/ext/`). This is a **CiviCRM extension, not a WordPress plugin**; do not upload it via `Plugins → Add Plugin`.
3. CiviCRM → **Administer → System Settings → Extensions → Refresh**, find **Mosaico Gallery Manager**, and **Install** (or Enable).
4. Choose **Mailings → Mosaico Gallery Manager** (if the normal Mailings menu is enabled), or go to the site's CiviCRM base URL and append `/mosaicogallery` (usually `https://example.org/civicrm/mosaicogallery`). Alternatively use CiviCRM routing: `civicrm/mosaicogallery?reset=1`.
5. Open **Mosaico Gallery Manager**. The gallery now checks and automatically populates missing or outdated usage statistics before displaying thumbnails. The first scan can take a while on large archives; the **Refresh usage counts** button remains available to force a new scan.
6. Archive an image and open a **new** Mosaico editor session. Verify it is hidden in the image selector and that the image still works in an old mailing. Test Restore too.

If CiviCRM's menu cache does not recognize a new route after enablement, run **Administer → System Settings → Cleanup Caches and Update Paths**.

## Behaviour and limitations

- **Zero file deletion.** No delete feature; disabling or uninstalling the extension also leaves files untouched. Database metadata is retained on uninstall.
- Existing emails retain links, including references to archived images.
- Archiving affects the editor's **GET gallery response**, not the image URL. Some Mosaico/WordPress gallery integrations replace the native gallery or use `fetch()` instead of jQuery; those may require an adapter and **will not be filtered** by this beta. Do not install the third-party WordPress media gallery swap alongside this beta without testing.
- Editor group/order controls update the existing Knockout gallery immediately; creating or renaming groups and changing archive flags in another tab still requires reopening/reloading the editor to receive updated metadata.
- Usage counts cover saved mailings, editable Mosaico templates (HTML, metadata and content JSON), and CiviCRM message templates in the local database. Deleted records, external emails and actual email recipients are not counted. A template and a mailing derived from it count as separate saved records.
- File date is filesystem modification time (may change during restores or migrations), not a guaranteed upload timestamp.
- Paths are taken from `CRM_Mosaico_Utils::getConfig()`; there is no hardcoded uploads directory.
- The first scan indexes existing files. Uploads made while the extension runs are listed with defaults until rescanned.
- Filename search only in this version. No physical duplicate detection in beta.

## Reverting

Disable the extension from the CiviCRM Extensions screen and clear caches. Mosaico returns to its original image listing because the JS hook no longer runs. No image links or files are changed. The extra metadata table is intentionally preserved (`civicrm_mosaicogallery_image`).

## Safety / security

- Admin page and API require `administer CiviCRM`.
- Write operations require POST and a per-session CSRF token.
- All DB values from image filenames are parameterized. The only editable flags are archive/favourite.
- Admin UI builds DOM elements with `textContent`, not unsanitized HTML.
- No user-controlled filesystem path may be submitted; image changes are allowed only for filenames in the configured Mosaico upload directory.

## Suggested live acceptance checks

- Archive an image used in an already-sent BEC email: the email must still show it.
- Create a new mailing: the archived image should not appear in the image selector.
- Restore the image: it returns after reloading the editor.
- Check sorting (newest, most used, favourites, filename), including filenames with spaces.
- Re-run usage count and confirm it agrees with a manually sampled mailing/template.
- Make sure uploading a fresh image still works and shows up in the selector.
- Test with a non-admin editor and an administrator.

## Troubleshooting: the Mailings menu link does not appear

1. Confirm that **Mosaico Gallery Manager** is **Enabled** in **Administer → System Settings → Extensions**. An extension uploaded only to GitHub is **not automatically deployed** to the WordPress server.
2. In **Administer → System Settings → Cleanup Caches and Update Paths**, clear the CiviCRM system and navigation caches. When using the CLI, run `cv flush` from the CiviCRM root (if `cv` is installed).
3. Open the route directly while logged in as a CiviCRM administrator. On WordPress, the admin route is often `https://YOUR-SITE/wp-admin/admin.php?page=CiviCRM&q=civicrm/mosaicogallery&reset=1`. With front-end clean URLs enabled, `https://YOUR-SITE/civicrm/mosaicogallery?reset=1` may also work. Use the actual URL format from an existing CiviCRM link on your site.
4. If direct access works but the navigation item does not, the cause is the navigation cache/menu configuration. If you receive *Page Not Found*, the extension route is not registered: verify the extension folder name, activation and run the CiviCRM cache/route rebuild. If you receive *Permission Denied*, ensure you have **administer CiviCRM** permission. If an internal server error occurs, inspect the CiviCRM log.
5. You can alternatively add a manual link via **Administer → Customize Data and Screens → Navigation Menu**, setting the URL to `civicrm/mosaicogallery`, but prefer allowing the extension's navigation hook to register it.

**Updating an existing installation:** deploy the new source files to your configured CiviCRM extensions folder (the same directory as the original files), refresh extension metadata if needed, and clear CiviCRM caches. Pushing a change to GitHub will not update files on a WordPress server by itself. Do not uninstall/reinstall: flags and archive metadata must be preserved.

## Smarty template not found

If the page reports `Unable to load file:CRM/Mosaicogallery/Page/Gallery.tpl`, update to 0.2.7-beta8 or newer. The extension now registers its own `templates/` folder with CiviCRM Smarty. Run `git pull --ff-only` in the extension folder, then clear CiviCRM caches (`cv flush` if available). Do not copy the template into core CiviCRM directories.

## Recovery from Smarty 5 fatal error in beta3

Version 0.1.2-beta3 could crash the WordPress CiviCRM admin by reading Smarty's protected `template_dir` property from the global configuration hook. Version 0.2.7-beta8 removes that global Smarty access. Template registration now happens only on the gallery page, via public `getTemplateDir()` and `addTemplateDir()` APIs (which CiviCRM's Smarty wrapper forwards through `__call()`).

If the site is already inaccessible, temporarily restore the extension's previous main PHP file from the commit before `d1dc4c76` with `git show d1dc4c76^:mosaicogallery.php > mosaicogallery.php`, then run `git pull --ff-only origin main` **only after restoring the file to Git's tracked state** (using `git restore mosaicogallery.php` after updating the remote reference, or using a temporary file). The safe alternative is to clone a fresh extension copy after moving the old folder outside the extensions directory. Do not uninstall the CiviCRM extension or delete any image files.

## WordPress gallery API returns HTML instead of JSON (beta5)

The API URL is inserted into an HTML attribute and already receives HTML escaping from Smarty. Earlier beta versions supplied an HTML-escaped URL from `CRM_Utils_System::url()`, causing double escaping on WordPress (the URL could contain `amp;q=` instead of `q=`). The latest release requests a **raw** URL using `htmlize=FALSE` and forces WP admin routing for the manager API and editor script URL.

If the browser still reports a non-JSON response, inspect the HTTP code, content type, and any redirection shown in the error. An HTML login page indicates the browser was redirected; a 404 or 403 likely points to a routing or permissions problem. Check the browser's Network tab for the `civicrm/mosaicogallery/api` request. The tool deliberately does not copy server HTML into the error message because it can contain private data.

## WordPress admin 403: page=1

WordPress may reject a CiviCRM API URL containing `wp-admin/admin.php?page=1` with `Sorry, you are not allowed to access this page`. The manager now constructs the API request using the working WordPress admin entry point, using `page=CiviCRM` where the generated URL incorrectly returned `page=1`. On update, clear browser cache or force reload the page. Verify in Network that the API request is to `admin.php?page=CiviCRM&q=civicrm%2Fmosaicogallery%2Fapi`.

## WordPress 403 on a separate gallery API route (beta7)

The image-manager JavaScript now sends its JSON requests to the same URL that successfully served the gallery page, adding `mg_ajax=1` and `action=...`. The gallery page forwards such requests to the existing permission-checked JSON handler before rendering Smarty content. This avoids the separate WordPress admin-page route that was generating a 403 on some installations. The JSON API still requires `administer CiviCRM`; POST operations still require the per-session CSRF token. No files are moved or deleted.

## WordPress gallery HTTP 403: reserved `page` query parameter (beta8)

Root cause: WordPress uses `admin.php?page=CiviCRM` to decide which admin screen to authorize. Previous versions of the gallery sent `page=1` to request the first page of images, overwriting the WordPress admin router's `page=CiviCRM`. WordPress rejected the request with 403 before CiviCRM ran. This occurred even after the AJAX request had been moved onto the working gallery URL.

The fix sends the gallery's pagination parameter as `mg_page` and reads `mg_page` server-side. WordPress's `page=CiviCRM` is now preserved. Regression test: `node tests/pagination-route.js`. The request should look like `wp-admin/admin.php?page=CiviCRM&q=civicrm%2Fmosaicogallery&mg_ajax=1&action=list&mg_page=1` (query order may vary).

## Image groups (0.2.7-beta8)

- Create **up to ten** groups with your own headings (1–80 Unicode characters).
- Rename or remove groups. Removing a group puts its images back into **Ungrouped**; files, thumbnails, historical email HTML and URLs are unchanged.
- An image belongs to **one group at a time**, or **Ungrouped**. No virtual folders are created on disk.
- From the manager, select a group to filter images. Change an image's group from its card, or select several images on the current page and click **Apply group** for bulk assignment.
- In the Mosaico editor, use **Group** and **Sort by** directly in the Gallery tab above thumbnails. Selections update immediately without reopening. Archived images remain hidden in every group.
- The manager shows the number of images assigned to each group (including archived images still on disk). Group headings are managed by CiviCRM administrators.

### Important: database upgrade for existing installations

This release adds two **extension-owned database tables**: `civicrm_mosaicogallery_group` and `civicrm_mosaicogallery_image_group`. The existing `civicrm_mosaicogallery_image` table is unchanged, retaining archive flags and usage counts.

**Back up the CiviCRM database before upgrading.** After deploying with Git, run:

```bash
cd /home/bertie/public_html/wp/wp-content/uploads/civicrm/ext/mosaico-gallery-manager-main
git pull --ff-only origin main

cd /home/bertie/public_html/wp
cv ext:upgrade-db
cv flush
```

Alternatively, apply pending database upgrades in **CiviCRM → Administer → System Settings → Extensions**. Run the upgrade before creating or assigning groups. The gallery still lists ungrouped images if the migration has not run, but group actions will be disabled and it will display an upgrade reminder.

**Regression tests** (no live database changes):
```bash
php tests/groups.php
php tests/group-upgrade.php
node tests/pagination-route.js
node tests/preview-editor-filter.js
```

Test on a staging clone first. Existing archived images remain archived, and changing or deleting groups never deletes or relocates images.

## Gallery integration and multi-select improvements (0.2.7-beta8)

The lower-right floating Mosaico toolbar is removed. Its custom **Group** and **Sort by** selectors now appear directly in Mosaico's existing **Gallery** pane, above the native image list. Changes re-filter/re-sort the live gallery immediately using Mosaico's documented plugin hook (`hook_civicrm_mosaicoPlugin`) and Knockout gallery model, without editing or replacing Mosaico core files. Stock gallery upload behavior, image drag/drop and thumbnail selection remain native.

The management page now makes **Bulk image grouping** prominent above the thumbnails. Click any thumbnail (or its checkbox), select all images on the current page, and retain selections when paging and filtering. Choose a group and select **Assign selected**; the app sends groups of up to 100 filenames per API request. Choose **Ungrouped** to remove assignments, or **Clear selection** to reset. A selected image has a blue border and badge. You can still assign images individually from each card.

No database change is required when updating from `0.2.0-beta1`, only `git pull --ff-only` and a browser refresh. The previous database migration is still required if upgrading from an older version. Test in a draft mailing before using in production.

## Compact embedded image-gallery filters (0.2.7-beta8)

The inline Gallery filter panel has been reduced to a slim, single-row control with a **Group** dropdown, **Sort by** dropdown, and discreet **Manage** link. The separate "Find images" header, image count and footer were removed, labels now sit close to the selectors, and padding/margins were tightened.

**Native thumbnail dimensions are not changed by this extension.** Mosaico's original `src/css/style_mosaico_tools.less` defines `.draggable` thumbnail frames at `width: 100px; height: 100px`. This extension adds only filter controls and changes the order/selection of gallery records; it does not add image-size CSS, replace the gallery image template, or modify `thumbnailUrl`. Individual live browser rendering may depend on the site's Mosaico version and CSS.

Updating from 0.2.1-beta2 requires only `git pull --ff-only origin main`, a CiviCRM cache flush, and a hard reload of the Mosaico editor. No new database upgrade is required.

## Gallery management settings cog (0.2.7-beta8)

The compact Mosaico Gallery panel now shows a **settings cog (⚙)** immediately to the right of the **Sort by** dropdown. It replaces the dim "Manage" text that previously sat beside the caption. The cog is shown only to CiviCRM administrators, opens the Gallery Manager in a new tab, and includes an accessible label and hover tooltip. The group and sort selectors, image dimensions, live filtering and image management features are otherwise unchanged.

Update with `git pull --ff-only origin main`, clear the CiviCRM cache and open a fresh Mosaico editor session or hard reload. No database upgrade is needed.

## Automatic image usage counts on opening (0.2.7-beta8)

The extension's Gallery Manager page no longer repeats its title below the CiviCRM page heading.

When the page first loads, it checks the database's existing `last_indexed` timestamps for **current image files**. If any current image has never been indexed, was recently uploaded without an index record, or has usage counts older than 24 hours, the browser starts an authenticated, CSRF-protected `POST action=usage` using the existing reference indexer. It waits for this calculation to finish before showing thumbnails, so the initial display and **Most used** sorting use populated counts. This can take longer on the first visit or after a full day; a short loading message is shown.

If all current images have been indexed within the last 24 hours, the saved counts appear directly without rescanning mailings. Orphaned database records for removed images are ignored in the freshness check. A failed automatic refresh displays an error message but still loads the gallery with whatever counts were previously cached. **Refresh usage counts** remains as the manual override.

The read-only freshness check does not alter records; the existing POST action performs all writes after successful scans. No new tables or migration are needed.

## Reference counting and persistent cache correction (0.2.7-beta8)

**Why some images incorrectly showed zero:** The previous implementation scanned `civicrm_mosaico_template.html` and `metadata` but **omitted `content`**, which is where Mosaico keeps its editable image references. It also overlooked `civicrm_msg_template.msg_html` and some filenames containing spaces, non-ASCII characters or doubly URL-encoded paths. It counted no reference in those cases even when the image was used. The new scanner checks all these forms, counts **each saved record at most once per image**, and retains a true zero when no stored reference is found. The display now says "No saved references found" rather than suggesting every zero means an image was definitely never used.

**How the cache is stored:** `civicrm_mosaicogallery_image` holds one record per filename with `usage_count` and `last_indexed`. The scanner executes a parameterized `UPDATE civicrm_mosaicogallery_image SET usage_count = ..., last_indexed = NOW(), usage_index_version = 2 WHERE filename = ...` after its source scans complete successfully. Normal page views read this persisted value from the database; no client-side storage or re-scan is required.

**Update requires a small CiviCRM extension database upgrade:** a `usage_index_version SMALLINT UNSIGNED NOT NULL DEFAULT 0` column is added to the existing image-index table (no image deletion and no reset of archive/favourite flags). The previous algorithm's cached counts have version `0`; the first Gallery Manager visit after upgrading triggers **one** corrected reindex, saving version `2`. Future visits reuse the saved totals **without the previous automatic 24-hour rescan**. A new image without a saved index triggers a scan. To account for changes to previously saved mailings, click **Refresh usage counts**, which rewrites the stored totals.

**Install:** Back up the CiviCRM database, then pull the update. Apply the pending **Mosaico Gallery Manager** database upgrade from CiviCRM → Administer → System Settings → Extensions (or run `cv ext:upgrade-db` after confirming which other extensions have pending upgrades), flush caches, and reload Gallery Manager. The gallery remains available if the upgrade has not been applied, but will display an upgrade warning and will not automatically index until the column exists.

**Verification SQL (read-only):**
```sql
SELECT filename, usage_count, last_indexed, usage_index_version
FROM civicrm_mosaicogallery_image
ORDER BY usage_count ASC, filename
LIMIT 30;
```

A zero after the corrected, successful scan means no matching image URLs were found in the scanned *saved records*. It does **not** prove the image was never in a deleted mailing, external system, unsaved draft or other source not indexed by this extension.

CI exercises `php tests/usage-scan.php` (including realistic escaped/double-encoded URLs) and `php tests/usage-freshness.php` (persistent cache and version invalidation).

## Fix for missing CiviMail/Mosaico saved references (0.2.7-beta8)

**Root cause:** Individual CiviMail mailing records save their Mosaico editable design JSON in `civicrm_mailing.template_options`, frequently under `mosaicoContent` (and potentially multiple A/B variants). Earlier usage indexing only inspected `civicrm_mailing.body_html`, which can omit images that still appear in a saved editable design. The scanner now searches **both fields within the same saved mailing record**, deduplicating filenames per record. It continues to scan `civicrm_mosaico_template.html/metadata/content` and `civicrm_msg_template.msg_html`.

The usage index algorithm version is now `3`. After pulling the update, the next Gallery Manager visit performs one recount of existing images (provided the `usage_index_version` database column from 0.2.5-beta6 has been installed). The corrected counts and version are persisted to `civicrm_mosaicogallery_image`. Subsequent visits use saved values with no daily rescan. Manual **Refresh usage counts** recounts after mailings are added or edited.

The management page now reports how many saved records were scanned and how many images have at least one matching reference. Zeros remain possible for genuinely unused uploads, references in deleted/external/unsaved content, or sources outside the supported tables. The tool never invents a usage count of one.

**Checks:** `php tests/usage-scan.php` includes a saved mailing with a Mosaico `template_options` reference, an A/B record using the same image twice, and a genuinely unused image. `php tests/usage-freshness.php` confirms older algorithm versions trigger exactly one recount.

No further database schema change is needed from v0.2.5-beta6. Earlier installations must first apply the existing usage-index version column upgrade. Back up the database before schema upgrades, and test on staging if possible.

## GitHub validation and email notifications

GitHub Actions validates PHP syntax, JavaScript syntax, and the image manager's regression tests. To prevent the old flood of **Run failed: Syntax checks** emails during development, the workflow now runs:

- **For pull requests into `main`**: checks the complete proposed changes before merging.
- **For a deliberate release/version change to `info.xml` on `main`**: tests the release before it is installed on the server.
- **When the workflow file itself changes**: validates the changed checks.
- **On demand**: GitHub → Actions → Extension validation → Run workflow.

Individual code, test, or documentation commits pushed directly to `main` **do not automatically trigger this workflow** unless they also change `info.xml`. Therefore, **make functional changes on a feature branch and open a pull request**; merge only once its checks pass. Keep the version bump as the final commit when releasing directly to main. Superseded in-progress checks are cancelled to avoid redundant notifications. Genuine failures on pull requests or release commits continue to report errors normally. The workflow is not disabled and existing tests have not been weakened.

The numerous failure emails from 31 October 2026 were for intermediate commits while tests and new classes were being added one at a time. The latest complete main commit was green before this workflow change. The historic notification emails cannot be recalled by editing GitHub Actions.

## Database persistence and image-count consistency fix (0.2.7-beta8)

**Symptom:** A completed scan could say "190 of 196 images have references" while many thumbnail cards still said "No saved references found".

**Cause:** `metadata()` creates placeholder metadata for images assigned to gallery groups even when the `civicrm_mosaicogallery_image` usage-index table has **no actual row** for the image. `syncInventory()` incorrectly used those placeholders to decide whether a usage-index row existed. The scanner then used `UPDATE ... WHERE filename = ...`; SQL accepts an UPDATE matching zero rows, so the scan completed and its **in-memory** counts were reported as successful, while the gallery read zeros from absent rows.

**Fix:** `syncInventory()` now checks genuine rows in the usage-index table rather than merged group metadata. Saving usage counts uses `INSERT ... ON DUPLICATE KEY UPDATE`, creating a missing usage row when necessary without modifying group assignments or an existing row's archived/favourite flags. Finally, the scan **reads every count back from the database**, checks it against the computed result and returns the *persisted* matched/unmatched totals. If a write is silently ignored, the request explicitly fails rather than displaying an inaccurate success message.

The indexing version is now **4**. Opening Gallery Manager after pulling this update automatically recalculates and saves all counts **once**, using the already-installed usage-index-version column, then reuses persisted values on future visits. **No additional database schema migration is necessary** if the v0.2.5-beta6 upgrade was applied. This does not move or delete any images, delete saved mailings, or change group assignments.

**Read-only SQL diagnostic (optional):** To see if existing group assignments lack a matching usage-index row:

```sql
SELECT g.filename, g.group_id
FROM civicrm_mosaicogallery_image_group g
LEFT JOIN civicrm_mosaicogallery_image i ON i.filename = g.filename
WHERE i.filename IS NULL
LIMIT 25;
```

After a successful recount, these images should have their own usage-index rows. The scan's reported count and the gallery thumbnails will then come from the same persisted source. This is covered by the integration test `php tests/usage-scan.php`, which deliberately starts with a grouped image missing from the usage-index table and verifies that it is created and correctly displayed; it also simulates a silent SQL failure to ensure it cannot report false success.
