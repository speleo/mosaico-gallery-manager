<?php
/**
 * Mosaico Gallery Manager for CiviCRM, org.bec.mosaicogallery.
 * No filesystem alterations and no edits to Mosaico itself.
 */

function mosaicogallery_civicrm_config(&$config) {
  static $configured = FALSE;
  if ($configured) {
    return;
  }
  $configured = TRUE;

  // CiviCRM's class autoloader needs PHP classes in the include path.
  set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());

  // Smarty uses a *separate* template search path. Without this registration,
  // CRM_Core_Page cannot load CRM/Mosaicogallery/Page/Gallery.tpl.
  // Use the public Smarty API on CiviCRM/Smarty 5 and fall back for older sites.
  $templateDir = __DIR__ . '/templates';
  $smarty = CRM_Core_Smarty::singleton();
  if (method_exists($smarty, 'getTemplateDir') && method_exists($smarty, 'addTemplateDir')) {
    $dirs = array_map(static function ($dir) {
      return rtrim((string) $dir, '/\\');
    }, (array) $smarty->getTemplateDir());
    if (!in_array(rtrim($templateDir, '/\\'), $dirs, TRUE)) {
      $smarty->addTemplateDir($templateDir);
    }
  }
  else {
    $dirs = (array) $smarty->template_dir;
    if (!in_array($templateDir, $dirs, TRUE)) {
      array_unshift($dirs, $templateDir);
      $smarty->template_dir = $dirs;
    }
  }
}

function mosaicogallery_civicrm_xmlMenu(&$files) {
  $files[] = __DIR__ . '/xml/Menu/mosaicogallery.xml';
}

function mosaicogallery_civicrm_install() {
  $_cfg = NULL;
  mosaicogallery_civicrm_config($_cfg);
  CRM_Core_DAO::executeQuery(<<<'SQL'
CREATE TABLE IF NOT EXISTS `civicrm_mosaicogallery_image` (
  `filename` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `archived` TINYINT(1) NOT NULL DEFAULT 0,
  `favourite` TINYINT(1) NOT NULL DEFAULT 0,
  `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_indexed` DATETIME NULL,
  PRIMARY KEY (`filename`),
  KEY `archived_favourite` (`archived`, `favourite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
  );
}

function mosaicogallery_civicrm_uninstall() {
  // Preserve records unless explicitly removed by the site administrator.
  // Avoid destructive effects from disabling/reinstalling this extension.
}

/**
 * Appends a non-destructive gallery sorter/filter to the Mosaico editor.
 * The script only processes GET responses from Mosaico's image gallery list.
 */
function mosaicogallery_civicrm_mosaicoScriptUrlsAlter(&$scriptUrls) {
  $scriptUrls[] = CRM_Utils_System::url('civicrm/mosaicogallery/editorjs', 'reset=1', TRUE);
}

/**
 * Add a shortcut to the standard CiviCRM Mailings navigation menu.
 *
 * Unlike xmlMenu() (which defines routes), this hook adds the visible menu
 * item. Assign stable navID and parentID fields so menu renderers on older
 * CiviCRM versions can locate the entry; never modify core navigation rows.
 */
function mosaicogallery_civicrm_navigationMenu(&$menu) {
  $maxNavID = 0;
  array_walk_recursive($menu, function ($value, $key) use (&$maxNavID) {
    if ($key === 'navID' && is_numeric($value)) {
      $maxNavID = max($maxNavID, (int) $value);
    }
  });

  foreach ($menu as $key => &$item) {
    $attributes = $item['attributes'] ?? [];
    // "Mailing" is CiviCRM's historical internal name; also accept "Mailings".
    if (!in_array($attributes['name'] ?? '', ['Mailing', 'Mailings'], TRUE)) {
      continue;
    }
    if (!isset($item['child']) || !is_array($item['child'])) {
      $item['child'] = [];
    }
    foreach ($item['child'] as $child) {
      if (($child['attributes']['name'] ?? '') === 'mosaico-gallery-manager') {
        return;
      }
    }

    $newID = $maxNavID + 1;
    $parentID = $attributes['navID'] ?? (is_numeric($key) ? (int) $key : NULL);
    $item['child'][$newID] = [
      'attributes' => [
        'label' => 'Mosaico Gallery Manager',
        'name' => 'mosaico-gallery-manager',
        'url' => 'civicrm/mosaicogallery',
        'permission' => 'administer CiviCRM',
        'operator' => 'AND',
        'parentID' => $parentID,
        'navID' => $newID,
        'active' => 1,
      ],
    ];
    return;
  }
}
