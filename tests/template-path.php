<?php
/**
 * CiviCRM 6/Smarty 5 regression: the Smarty wrapper delegates APIs via
 * __call() and does not expose protected template_dir.
 */
class MockSmartyBackend {
  protected $template_dir = ['/civicrm/core/templates'];
  public $adds = 0;
  public function getTemplateDir() { return $this->template_dir; }
  public function addTemplateDir($path) { $this->template_dir[] = $path; $this->adds++; }
}
class CRM_Core_Smarty {
  private static $instance;
  private $backend;
  public function __construct() { $this->backend = new MockSmartyBackend(); }
  public static function singleton() { return self::$instance ?? (self::$instance = new self()); }
  public function __call($method, $args) { return $this->backend->$method(...$args); }
  public function __get($name) { throw new RuntimeException('Unsafe property access: ' . $name); }
  public function additions() { return $this->backend->adds; }
}
class CRM_Core_Page {
  protected static $assignments = [];
  public function assign($key, $value) { self::$assignments[$key] = $value; }
  public function getAssigned($key) { return self::$assignments[$key] ?? NULL; }
  public function run() { return 'rendered'; }
}
class CRM_Core_Permission {
  public static function check($p) { return TRUE; }
}
class CRM_Core_Session {
  public static function singleton() { return new self(); }
  public function set($k, $v) {}
}
class CRM_Utils_System {
  public static function url($path, $args, $absolute, $fragment = NULL, $htmlize = TRUE, $frontend = FALSE, $forceBackend = FALSE) {
    if (!$forceBackend) {
      throw new RuntimeException('Admin-only gallery URLs must force WordPress backend routing');
    }
    $url = 'https://example.org/wp-admin/admin.php?page=CiviCRM&q=' . rawurlencode($path) . '&' . $args;
    return $htmlize ? str_replace('&', '&amp;', $url) : $url;
  }
}
class CRM_Core_Resources {
  public static function singleton() { return new self(); }
  public function addStyleFile($ext, $path) {}
  public function addScriptFile($ext, $path) {}
}
require_once dirname(__DIR__) . '/mosaicogallery.php';
$config = new stdClass();
mosaicogallery_civicrm_config($config);
if (CRM_Core_Smarty::singleton()->additions() !== 0) {
  throw new RuntimeException('Config hook should not access Smarty');
}
require_once dirname(__DIR__) . '/CRM/Mosaicogallery/Page/Gallery.php';
$page = new CRM_Mosaicogallery_Page_Gallery();
$page->run();
$page->run();

$apiUrl = $page->getAssigned('galleryApiUrl');
// Smarty escapes the attribute when producing markup; the browser decodes it
// once. If the URL was *already* HTML-escaped, amp;q will be the parsed key.
$renderedAttribute = htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8');
$browserValue = html_entity_decode($renderedAttribute, ENT_QUOTES, 'UTF-8');
parse_str((string) parse_url($browserValue, PHP_URL_QUERY), $queryParts);
if (($queryParts['q'] ?? '') !== 'civicrm/mosaicogallery/api' ||
    ($queryParts['page'] ?? '') !== 'CiviCRM' ||
    ($queryParts['reset'] ?? '') !== '1' ||
    isset($queryParts['amp;q']) ||
    str_contains($apiUrl, '&amp;')) {
  throw new RuntimeException('The WordPress API URL was double HTML-escaped');
}
$scriptUrls = [];
mosaicogallery_civicrm_mosaicoScriptUrlsAlter($scriptUrls);
parse_str((string) parse_url($scriptUrls[0] ?? '', PHP_URL_QUERY), $scriptParts);
if (($scriptParts['q'] ?? '') !== 'civicrm/mosaicogallery/editorjs' ||
    isset($scriptParts['amp;q'])) {
  throw new RuntimeException('Editor script URL was double HTML-escaped');
}

$smarty = CRM_Core_Smarty::singleton();
$dirs = $smarty->getTemplateDir();
$expected = dirname(__DIR__) . '/templates';
if (!in_array($expected, $dirs, TRUE) || $smarty->additions() !== 1) {
  throw new RuntimeException('Template directory was not added exactly once');
}
if (!is_file($expected . '/CRM/Mosaicogallery/Page/Gallery.tpl')) {
  throw new RuntimeException('Gallery template not found in registered directory');
}
echo "Smarty wrapper page registration passed.\n";
