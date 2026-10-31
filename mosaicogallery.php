<?php
/**
 * Mosaico Gallery Manager for CiviCRM, org.bec.mosaicogallery.
 * No filesystem alterations and no edits to Mosaico itself.
 */

function mosaicogallery_civicrm_config(&$config) {
  static $configured = FALSE;
  if (!$configured) {
    $configured = TRUE;
    set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
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

/** Add a shortcut to Mailings where the standard navigation node exists. */
function mosaicogallery_civicrm_navigationMenu(&$menu) {
  foreach ($menu as &$item) {
    if (($item['attributes']['name'] ?? '') !== 'Mailing') { continue; }
    if (!isset($item['child'])) { $item['child'] = []; }
    foreach ($item['child'] as $child) {
      if (($child['attributes']['name'] ?? '') === 'mosaico-gallery-manager') { return; }
    }
    $item['child'][] = [
      'attributes' => [
        'label' => 'Mosaico Gallery Manager',
        'name' => 'mosaico-gallery-manager',
        'url' => 'civicrm/mosaicogallery',
        'permission' => 'administer CiviCRM',
        'operator' => 'AND',
        'active' => 1,
      ],
    ];
    break;
  }
}
