<?php
/** Integration-style fixture: count saved records and persist the exact totals. */
$dir = sys_get_temp_dir() . '/mosaicogallery-usage-' . getmypid();
mkdir($dir);
register_shutdown_function(function() use ($dir) {
  foreach (glob($dir . '/*') ?: [] as $file) {unlink($file);}
  rmdir($dir);
});
foreach (['logo.png', 'annual photo.jpg', 'rénunion.jpg', 'never-used.gif'] as $name) {
  file_put_contents($dir . '/' . $name, 'image');
}
class CRM_Mosaico_Utils {
  public static $dir;
  public static function getConfig() {
    return ['BASE_DIR' => self::$dir, 'UPLOADS_DIR' => '/',
      'BASE_URL' => 'https://example.org/', 'UPLOADS_URL' => 'images/uploads/'];
  }
}
CRM_Mosaico_Utils::$dir = $dir;
class CRM_Mosaicogallery_Groups {
  public static function assignments() {return [];}
}
class FakeRows {
  private array $rows;
  public function __construct(array $rows) {$this->rows = $rows;}
  public function fetch() {
    if (!$this->rows) {return FALSE;}
    foreach (array_shift($this->rows) as $field => $value) {$this->$field = $value;}
    return TRUE;
  }
}
class CRM_Core_DAO {
  public static array $images = [];
  public static array $scanned = [];
  public static int $updated = 0;
  public static bool $breakTemplate = FALSE;
  public static function executeQuery($sql, $p = []) {
    if (str_starts_with($sql, 'SELECT filename, archived, favourite, usage_count ')) {
      return new FakeRows(array_map(fn($name) => [
        'filename' => $name, 'archived' => 0, 'favourite' => 0, 'usage_count' => 0
      ], array_keys(self::$images)));
    }
    if (str_starts_with($sql, 'INSERT IGNORE INTO civicrm_mosaicogallery_image')) {
      self::$images[$p[1][0]] = self::$images[$p[1][0]] ?? NULL;
      return new FakeRows([]);
    }
    if (str_contains($sql, 'SELECT body_html AS document FROM civicrm_mailing')) {
      return new FakeRows([
        ['document' => '<img src="https://example.org/images/uploads/logo.png"><img src="/images/uploads/logo.png">']
      ]);
    }
    if (str_contains($sql, 'SELECT CONCAT_WS') && str_contains($sql, 'civicrm_mosaico_template')) {
      if (self::$breakTemplate) {throw new RuntimeException('Simulated broken template source');}
      if (!str_contains($sql, 'content')) {
        throw new RuntimeException('Mosaico content JSON must be included');
      }
      return new FakeRows([
        ['document' => '{"src":"https:\/\/example.org\/images\/uploads\/annual%20photo.jpg"}'],
        ['document' => '{"src":"https%253A%252F%252Fexample.org%252Fimages%252Fuploads%252Fr%25C3%25A9nunion.jpg"}']
      ]);
    }
    if (str_contains($sql, 'SELECT msg_html AS document FROM civicrm_msg_template')) {
      return new FakeRows([['document' => '<img src="/images/uploads/static/logo.png">']]);
    }
    if (str_starts_with($sql, 'UPDATE civicrm_mosaicogallery_image SET usage_count')) {
      $name = $p[2][0];
      self::$images[$name] = $p[1][0];
      self::$scanned[$name] = $p[3][0];
      self::$updated++;
      return new FakeRows([]);
    }
    throw new RuntimeException('Unexpected SQL in usage fixture: ' . $sql);
  }
}
require_once dirname(__DIR__) . '/CRM/Mosaicogallery/Util.php';
function ok($condition, $message) {
  if (!$condition) {throw new RuntimeException($message);}
}
$expected = ['logo.png' => 2, 'annual photo.jpg' => 1, 'rénunion.jpg' => 1, 'never-used.gif' => 0];
$result = CRM_Mosaicogallery_Util::refreshUsage();
ok($result['sources'] === 4, 'four saved records should be scanned');
ok(CRM_Core_DAO::$images === $expected, 'usage counts must match actual references, including genuine zero. Got: ' . var_export(CRM_Core_DAO::$images, TRUE) . ' expected: ' . var_export($expected, TRUE));
ok(count(array_unique(CRM_Core_DAO::$scanned)) === 1 &&
  current(CRM_Core_DAO::$scanned) === CRM_Mosaicogallery_Util::USAGE_INDEX_VERSION, 'version persisted');
$previous = CRM_Core_DAO::$images;
CRM_Core_DAO::$updated = 0;
CRM_Core_DAO::$breakTemplate = TRUE;
try {
  CRM_Mosaicogallery_Util::refreshUsage();
  throw new RuntimeException('Refresh should refuse incomplete scan');
}
catch (RuntimeException $e) {
  ok(str_contains($e->getMessage(), 'Usage indexing incomplete'), 'partial scan error must be reported');
}
ok(CRM_Core_DAO::$updated === 0 && CRM_Core_DAO::$images === $previous, 'failed scan does not change stored counts');
echo "Reference count extraction, saved records, persisted versions and failure safety: PASS\n";
