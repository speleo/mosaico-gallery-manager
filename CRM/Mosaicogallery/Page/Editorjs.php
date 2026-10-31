<?php
class CRM_Mosaicogallery_Page_Editorjs extends CRM_Core_Page {
  public function run() {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-store, private');
    if (!(CRM_Core_Permission::check('access CiviMail') || CRM_Core_Permission::check('administer CiviCRM'))) {
      http_response_code(403);
      CRM_Utils_System::civiExit();
    }
    $meta = CRM_Mosaicogallery_Util::metadata();
    $files = CRM_Mosaicogallery_Util::files();
    $entries = [];
    foreach ($files as $name => $file) {
      $entries[$name] = [
        'archived' => !empty($meta[$name]['archived']),
        'favourite' => !empty($meta[$name]['favourite']),
        'usage_count' => (int) ($meta[$name]['usage_count'] ?? 0),
        'mtime' => (int) $file['mtime'],
        'group_id' => (int) ($meta[$name]['group_id'] ?? 0),
      ];
    }
    $conf = [
      'entries' => $entries,
      'groups' => CRM_Mosaicogallery_Groups::all(),
      'managerUrl' => CRM_Utils_System::url('civicrm/mosaicogallery', 'reset=1', TRUE, NULL, FALSE, FALSE, TRUE),
      'canManage' => CRM_Core_Permission::check('administer CiviCRM'),
    ];
    // Quoted and escaped as a JavaScript literal, never executable user input.
    $json = json_encode($conf, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $source = file_get_contents(__DIR__ . '/../../../js/editor.js');
    echo str_replace('/*__GALLERY_DATA__*/null', $json, $source);
    CRM_Utils_System::civiExit();
  }
}
