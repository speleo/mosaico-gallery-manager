<?php
/**
 * Detect the active CiviCRM theme for the current request.
 *
 * The approach matches BEC Membership Tools: inspect CiviCRM's theme service
 * and the resolved theme inheritance order, never WordPress body classes.
 * The active theme can differ between admin and frontend requests.
 */
class CRM_Mosaicogallery_ThemeProfile {

  public static function detect($themeService = NULL) {
    $key = '';
    $meta = [];

    try {
      $themes = $themeService ?: Civi::service('themes');
      $key = (string) $themes->getActiveThemeKey();
      if (method_exists($themes, 'getAll')) {
        $all = $themes->getAll();
        if (is_array($all) && isset($all[$key]) && is_array($all[$key])) {
          $meta = $all[$key];
        }
      }
    }
    catch (\Throwable $e) {
      // The normal full-width gallery styling still applies on unknown themes.
    }

    return [
      'key' => $key ?: 'unknown',
      'profile' => self::profileFor($key, $meta),
    ];
  }

  public static function profileFor($key, array $meta = []) {
    $key = strtolower((string) $key);
    // The Island + River and other River-family theme keys.
    foreach (['river', 'walbrook', 'minetta', 'hackney', 'thames'] as $fragment) {
      if (strpos($key, $fragment) !== FALSE) {
        return 'river';
      }
    }

    // An inherited theme may use River even when its own key does not.
    $searchOrder = isset($meta['search_order']) && is_array($meta['search_order'])
      ? $meta['search_order'] : [];
    foreach ($searchOrder as $parentTheme) {
      if (!is_string($parentTheme)) {
        continue;
      }
      foreach (['river', 'walbrook', 'minetta', 'hackney', 'thames'] as $fragment) {
        if (stripos($parentTheme, $fragment) !== FALSE) {
          return 'river';
        }
      }
    }
    return 'legacy';
  }

}
