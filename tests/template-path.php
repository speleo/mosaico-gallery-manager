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
  public static function url($path, $args, $absolute) { return '/index.php?q=' . $path; }
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
