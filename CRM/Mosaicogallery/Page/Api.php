<?php
class CRM_Mosaicogallery_Page_Api extends CRM_Core_Page {
  public function run() {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    try {
      if (!CRM_Core_Permission::check('administer CiviCRM')) {
        http_response_code(403);
        throw new RuntimeException('Permission denied.');
      }
      $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
      $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? $_REQUEST['action'] : 'list';
      if ($method !== 'GET') {
        if ($method !== 'POST') {
          http_response_code(405);
          throw new RuntimeException('Method not allowed.');
        }
        $token = isset($_POST['token']) && is_string($_POST['token']) ? $_POST['token'] : '';
        $expected = CRM_Core_Session::singleton()->get('mosaicogallery_csrf');
        if (!$expected || !hash_equals($expected, $token)) {
          http_response_code(403);
          throw new RuntimeException('Security token expired. Reload the manager and retry.');
        }
      }
      switch ($action) {
        case 'list':
          if ($method !== 'GET') { throw new InvalidArgumentException('Use GET.'); }
          $filter = isset($_GET['filter']) && is_string($_GET['filter']) ? $_GET['filter'] : 'active';
          $sort = isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort'] : 'recent';
          $search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
          if (!in_array($filter, ['active', 'archived', 'all', 'favourites'], TRUE)) { $filter = 'active'; }
          if (!in_array($sort, ['recent', 'popular', 'name', 'favourites'], TRUE)) { $sort = 'recent'; }
          $out = CRM_Mosaicogallery_Util::listing($filter, $sort, substr($search, 0, 255), (int) ($_GET['page'] ?? 1));
          break;
        case 'sync':
          if ($method !== 'POST') { throw new InvalidArgumentException('Use POST.'); }
          $out = CRM_Mosaicogallery_Util::syncInventory();
          break;
        case 'usage':
          if ($method !== 'POST') { throw new InvalidArgumentException('Use POST.'); }
          $out = CRM_Mosaicogallery_Util::refreshUsage();
          break;
        case 'setflag':
          if ($method !== 'POST') { throw new InvalidArgumentException('Use POST.'); }
          $file = isset($_POST['filename']) && is_string($_POST['filename']) ? $_POST['filename'] : '';
          $flag = isset($_POST['flag']) && is_string($_POST['flag']) ? $_POST['flag'] : '';
          $enabled = ($_POST['enabled'] ?? '') === '1';
          CRM_Mosaicogallery_Util::setFlag($file, $flag, $enabled);
          $out = ['updated' => TRUE];
          break;
        default:
          throw new InvalidArgumentException('Unsupported action.');
      }
      echo json_encode(['ok' => TRUE, 'data' => $out], JSON_UNESCAPED_SLASHES);
    }
    catch (Throwable $e) {
      if (http_response_code() === 200) { http_response_code(400); }
      echo json_encode(['ok' => FALSE, 'error' => $e->getMessage()]);
    }
    CRM_Utils_System::civiExit();
  }
}
