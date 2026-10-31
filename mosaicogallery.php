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

  // Class autoloading only. Never inspect or alter Smarty in this global
  // configuration hook: a failure here can disable the entire WordPress admin.
  set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
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
  CRM_Mosaicogallery_Groups::createTables();
}

/**
 * Install group tables for existing installs when their administrator applies
 * pending CiviCRM extension database upgrades.
 */
function mosaicogallery_civicrm_upgrade($op, $queue = NULL) {
  $missing = !CRM_Mosaicogallery_Groups::available();
  if ($op === 'check') {
    return [$missing];
  }
  if ($op === 'enqueue' && $missing && $queue !== NULL) {
    $queue->createItem(new CRM_Queue_Task(
      ['CRM_Mosaicogallery_Groups', 'upgrade'],
      [],
      'Create Mosaico Gallery image group tables'
    ));
  }
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
  $scriptUrls[] = CRM_Utils_System::url('civicrm/mosaicogallery/editorjs', 'reset=1', TRUE, NULL, FALSE, FALSE, TRUE);
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
