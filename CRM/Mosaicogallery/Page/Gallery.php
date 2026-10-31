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
    $resources = CRM_Core_Resources::singleton();
    $resources->addStyleFile('org.bec.mosaicogallery', 'css/admin.css');
    $resources->addScriptFile('org.bec.mosaicogallery', 'js/admin.js');
    parent::run();
  }
}
