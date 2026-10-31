<?php
class CRM_Mosaicogallery_Page_Gallery extends CRM_Core_Page {
  public function run() {
    if (!CRM_Core_Permission::check('administer CiviCRM')) {
      CRM_Core_Error::fatal('Permission denied.');
    }
    $session = CRM_Core_Session::singleton();
    $token = bin2hex(random_bytes(24));
    $session->set('mosaicogallery_csrf', $token);
    $this->assign('galleryApiUrl', CRM_Utils_System::url('civicrm/mosaicogallery/api', 'reset=1', TRUE));
    $this->assign('galleryCsrfToken', $token);
    // Register the extension template only when this page is requested.
    // CiviCRM 6.x wraps Smarty and forwards methods through __call(), so
    // method_exists($smarty, 'getTemplateDir') is NOT a valid capability test.
    // Access via public getTemplateDir/addTemplateDir APIs, never template_dir.
    $smarty = CRM_Core_Smarty::singleton();
    $templateDir = dirname(__DIR__, 3) . '/templates';
    $registered = array_map(static function ($dir) {
      return rtrim((string) $dir, '/\\');
    }, (array) $smarty->getTemplateDir());
    if (!in_array(rtrim($templateDir, '/\\'), $registered, TRUE)) {
      $smarty->addTemplateDir($templateDir);
    }

    $resources = CRM_Core_Resources::singleton();
    $resources->addStyleFile('org.bec.mosaicogallery', 'css/admin.css');
    $resources->addScriptFile('org.bec.mosaicogallery', 'js/admin.js');
    parent::run();
  }
}
