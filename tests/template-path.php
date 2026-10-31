<?php
/**
 * Test that CiviCRM's Smarty template search path receives our extension path.
 * Run with: php tests/template-path.php modern
 *           php tests/template-path.php legacy
 */
$legacy = ($argv[1] ?? 'modern') === 'legacy';
if ($legacy) {
  class CRM_Core_Smarty {
    public $template_dir = ['/civicrm/templates'];
    private static $instance;
    public static function singleton() {
      return self::$instance ?? (self::$instance = new self());
    }
  }
}
else {
  class CRM_Core_Smarty {
    private $dirs = ['/civicrm/templates'];
    private static $instance;
    public $addCalls = 0;
    public static function singleton() {
      return self::$instance ?? (self::$instance = new self());
    }
    public function getTemplateDir() { return $this->dirs; }
    public function addTemplateDir($dir) {
      $this->addCalls++;
      $this->dirs[] = $dir;
    }
  }
}

require_once dirname(__DIR__) . '/mosaicogallery.php';
$config = NULL;
mosaicogallery_civicrm_config($config);
mosaicogallery_civicrm_config($config);

$smarty = CRM_Core_Smarty::singleton();
$dirs = $legacy ? $smarty->template_dir : $smarty->getTemplateDir();
$expected = dirname(__DIR__) . '/templates';
if (count(array_filter($dirs, function($dir) use ($expected) {return $dir === $expected;})) !== 1) {
  fwrite(STDERR, "Template directory not registered exactly once: $expected\n");
  exit(1);
}
if (!is_file($expected . '/CRM/Mosaicogallery/Page/Gallery.tpl')) {
  fwrite(STDERR, "Gallery Smarty template file is missing\n");
  exit(1);
}
if (!$legacy && $smarty->addCalls !== 1) {
  fwrite(STDERR, "The modern Smarty addTemplateDir API was not called exactly once\n");
  exit(1);
}
echo "Smarty template registration ($argv[1]) passed.\n";
