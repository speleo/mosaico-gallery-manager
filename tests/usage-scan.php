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
  // Assigned image has NO corresponding inventory row at test start.
  // The old syncInventory wrongly treated this as an existing usage row.
  public static function assignments() {return ['annual photo.jpg' => 1];}
  public static function available() {return TRUE;}
  public static function all() {return [['id' => 1, 'title' => 'Events']];}
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
  public static array $images = ['logo.png' => 0];
  public static array $scanned = [];
  public static int $updated = 0;
  public static bool $breakTemplate = FALSE;
  public static bool $silentNoop = FALSE;
  public static array $archived = ['logo.png' => 1];
  public static array $favourite = ['logo.png' => 1];
  public static function executeQuery($sql, $p = []) {
    if (str_starts_with($sql, 'SELECT filename, archived, favourite, usage_count ')) {
      return new FakeRows(array_map(fn($name) => [
        'filename' => $name, 'archived' => self::$archived[$name] ?? 0,
        'favourite' => self::$favourite[$name] ?? 0,
        'usage_count' => self::$images[$name]
      ], array_keys(self::$images)));
    }
    if (str_starts_with($sql, 'SELECT filename FROM civicrm_mosaicogallery_image')) {
      return new FakeRows(array_map(fn($name) => ['filename' => $name], array_keys(self::$images)));
    }
    if (str_starts_with($sql, 'SELECT filename, usage_count FROM civicrm_mosaicogallery_image')) {
      return new FakeRows(array_map(fn($name, $count) => [
        'filename' => $name, 'usage_count' => $count
      ], array_keys(self::$images), array_values(self::$images)));
    }
    if (str_starts_with($sql, 'INSERT IGNORE INTO civicrm_mosaicogallery_image')) {
      self::$images[$p[1][0]] = self::$images[$p[1][0]] ?? 0;
      return new FakeRows([]);
    }
    if (str_contains($sql, 'FROM civicrm_mailing')) {
      if (!str_contains($sql, 'template_options')) {
        throw new RuntimeException('Mailing editable template_options must be scanned as well as rendered HTML.');
      }
      return new FakeRows([
        ['document' => '<img src="https://example.org/images/uploads/logo.png"><img src="/images/uploads/logo.png">'],
        // Mosaico editable mailing content persisted under template_options.
        ['document' => '{"mosaicoContent":"{\\"src\\":\\"https:\\/\\/example.org\\/images\\/uploads\\/annual%20photo.jpg\\"}"}'],
        // A/B designs are a single CiviMail record: only one usage per file.
        ['document' => '{"mosaicoContent":{"a":"https://example.org/images/uploads/logo.png","b":"https://example.org/images/uploads/logo.png"}}'],
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
    if (str_starts_with($sql, 'INSERT INTO civicrm_mosaicogallery_image ') &&
        str_contains($sql, 'ON DUPLICATE KEY UPDATE')) {
      $name = $p[1][0];
      if (self::$silentNoop && $name === 'logo.png') {
        // Database accepted a statement but silently wrote no row.
        return new FakeRows([]);
      }
      self::$images[$name] = $p[2][0];
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
$expected = ['logo.png' => 3, 'annual photo.jpg' => 2, 'rénunion.jpg' => 1, 'never-used.gif' => 0];
ok(!isset(CRM_Core_DAO::$images['annual photo.jpg']),
  'a grouped image begins without an inventory row');
$initial = CRM_Mosaicogallery_Util::syncInventory();
ok($initial['added'] === 3 && array_key_exists('annual photo.jpg', CRM_Core_DAO::$images),
  'inventory must add grouped images with missing real database rows');
$result = CRM_Mosaicogallery_Util::refreshUsage();
ksort(CRM_Core_DAO::$images);
ksort($expected);
ok($result['sources'] === 6, 'six saved records should be scanned');
ok($result['matched_images'] === 3 && $result['unmatched_images'] === 1,
  'scan reports three referenced images and one genuinely unmatched image');
ok(CRM_Core_DAO::$images === $expected, 'usage counts must match actual references, including genuine zero. Got: ' . var_export(CRM_Core_DAO::$images, TRUE) . ' expected: ' . var_export($expected, TRUE));
ok(count(array_unique(CRM_Core_DAO::$scanned)) === 1 &&
  current(CRM_Core_DAO::$scanned) === CRM_Mosaicogallery_Util::USAGE_INDEX_VERSION, 'version persisted');
$visible = CRM_Mosaicogallery_Util::metadata();
ok($visible['annual photo.jpg']['usage_count'] === 2,
  'gallery metadata must display saved counts on grouped images');
ok($visible['annual photo.jpg']['group_id'] === 1,
  'repairing usage counts must preserve group assignment');
ok($visible['logo.png']['archived'] && $visible['logo.png']['favourite'],
  'usage recount must preserve archive/favourite flags');
$gallery = CRM_Mosaicogallery_Util::listing('all', 'recent', '', 1, 60);
$shownReferenced = count(array_filter($gallery['items'], static function ($image) {
  return $image['usage_count'] > 0;
}));
ok($shownReferenced === $result['matched_images'],
  'scan summary must agree with gallery card counts');
ok(count($gallery['items']) - $shownReferenced === $result['unmatched_images'],
  'the gallery must show exactly the unmatched image count reported');

$previous = CRM_Core_DAO::$images;
// The old implementation reported success from memory even when SQL UPDATE
// changed zero rows. Verify the persisted data before reporting success.
CRM_Core_DAO::$images['logo.png'] = 0;
CRM_Core_DAO::$silentNoop = TRUE;
try {
  CRM_Mosaicogallery_Util::refreshUsage();
  throw new RuntimeException('Expected failure for silently ignored usage write');
}
catch (RuntimeException $e) {
  ok(str_contains($e->getMessage(), 'verification failed'),
    'silent persistence failures must be reported instead of claiming counts');
}
CRM_Core_DAO::$silentNoop = FALSE;
CRM_Core_DAO::$images = $previous;
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
