# Mosaico Gallery Manager (CiviCRM / WordPress)

**Version:** 0.1.0-beta1 — staging release; do not deploy on a production CiviCRM installation without testing against its exact version.

Manage the existing CiviCRM Mosaico image directory without deleting, renaming, or moving images. Works independently of the WordPress Media Library; it manages assets that Mosaico already references.

## Features

- Browser-based image gallery with name search, 60-image pages, active/archived/favourites views.
- Sort newest first, most used, favourites first, or filename.
- One-click archive/restore and favourite/unfavourite — **database flags only**.
- Reference-index refresh counts distinct stored `civicrm_mailing` HTML and `civicrm_mosaico_template` HTML/metadata references. It does not count individual email recipients, opens, or clicks.
- The editor integration uses Mosaico's documented `hook_civicrm_mosaicoScriptUrlsAlter` and a jQuery AJAX **response filter**, applying archive flags and chosen sort to the GET image list. It leaves upload POSTs, image renderers, and existing mailing HTML unmodified.
- In the editor, choose gallery order from the small floating control; reopen the gallery to see the new order. Admins can launch the management page in a new tab.
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
5. Click **Rescan image directory**, then **Refresh usage counts**. For a large archive, the first refresh can take a while; use off-peak time.
6. Archive an image and open a **new** Mosaico editor session. Verify it is hidden in the image selector and that the image still works in an old mailing. Test Restore too.

If CiviCRM's menu cache does not recognize a new route after enablement, run **Administer → System Settings → Cleanup Caches and Update Paths**.

## Behaviour and limitations

- **Zero file deletion.** No delete feature; disabling or uninstalling the extension also leaves files untouched. Database metadata is retained on uninstall.
- Existing emails retain links, including references to archived images.
- Archiving affects the editor's **GET gallery response**, not the image URL. Some Mosaico/WordPress gallery integrations replace the native gallery or use `fetch()` instead of jQuery; those may require an adapter and **will not be filtered** by this beta. Do not install the third-party WordPress media gallery swap alongside this beta without testing.
- The editor order control only affects the next gallery load (reopen gallery). Cached tabs need reloading after archive changes.
- Usage counts cover saved mailings and Mosaico templates in the local CiviCRM DB, not external emails or deleted records. Template references may be counted separately from a mailing created from the template.
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
