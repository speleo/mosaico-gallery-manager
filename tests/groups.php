<?php
/** Unit-level group model checks using an in-memory CiviCRM DAO double. */
class GroupRows {
  private array $items;
  public function __construct(array $items) {$this->items = array_values($items);}
  public function fetch() {
    if (!$this->items) {return FALSE;}
    foreach (array_shift($this->items) as $name => $value) {$this->$name = $value;}
    return TRUE;
  }
}
class CRM_Core_DAO {
  public static array $groups = [];
  public static array $assignments = [];
  public static bool $schema = FALSE;
  public static function checkTableExists($table) { return self::$schema; }
  public static function executeQuery($sql, $params = []) {
    if (str_starts_with($sql, 'CREATE TABLE')) {self::$schema = TRUE; return new GroupRows([]);}
    if (str_starts_with($sql, 'SELECT id, title FROM civicrm_mosaicogallery_group')) {
      ksort(self::$groups); return new GroupRows(array_map(
        fn($id, $title) => ['id' => $id, 'title' => $title],
        array_keys(self::$groups), array_values(self::$groups)
      ));
    }
    if (str_starts_with($sql, 'INSERT INTO civicrm_mosaicogallery_group ')) {
      $id = $params[1][0]; $title = $params[2][0];
      if (isset(self::$groups[$id]) || in_array(strtolower($title), array_map('strtolower', self::$groups), TRUE)) {
        throw new RuntimeException('Duplicate group');
      }
      self::$groups[$id] = $title; return new GroupRows([]);
    }
    if (str_starts_with($sql, 'UPDATE civicrm_mosaicogallery_group SET title')) {
      self::$groups[$params[2][0]] = $params[1][0]; return new GroupRows([]);
    }
    if (str_starts_with($sql, 'DELETE FROM civicrm_mosaicogallery_group ')) {
      unset(self::$groups[$params[1][0]]); return new GroupRows([]);
    }
    if (str_starts_with($sql, 'DELETE FROM civicrm_mosaicogallery_image_group WHERE group_id')) {
      self::$assignments = array_filter(self::$assignments, fn($group) => $group != $params[1][0]);
      return new GroupRows([]);
    }
    if (str_starts_with($sql, 'DELETE FROM civicrm_mosaicogallery_image_group WHERE filename')) {
      unset(self::$assignments[$params[1][0]]); return new GroupRows([]);
    }
    if (str_starts_with($sql, 'INSERT INTO civicrm_mosaicogallery_image_group ')) {
      self::$assignments[$params[1][0]] = $params[2][0]; return new GroupRows([]);
    }
    if (str_starts_with($sql, 'SELECT g.filename, g.group_id FROM civicrm_mosaicogallery_image_group')) {
      $out = [];
      foreach (self::$assignments as $filename => $group) {
        if (isset(self::$groups[$group])) {$out[] = ['filename' => $filename, 'group_id' => $group];}
      }
      return new GroupRows($out);
    }
    throw new RuntimeException('Unhandled SQL in test: ' . $sql);
  }
}
class CRM_Mosaicogallery_Util {
  public static function files() {return ['header.jpg' => [], 'photo.png' => []];}
}
require_once dirname(__DIR__) . '/CRM/Mosaicogallery/Groups.php';
function check($condition, $msg) {if (!$condition) {throw new RuntimeException($msg);}}
function expectFail($callback, $message) {
  try {$callback();} catch (InvalidArgumentException $e) {return;}
  throw new RuntimeException('Expected validation failure: ' . $message);
}
check(!CRM_Mosaicogallery_Groups::available(), 'schema should start absent');
CRM_Mosaicogallery_Groups::createTables();
check(CRM_Mosaicogallery_Groups::available(), 'schema should be available');

for ($n = 1; $n <= 10; $n++) {
  $group = CRM_Mosaicogallery_Groups::create("Group $n");
  check($group['id'] === $n, 'IDs should be slots 1–10');
}
expectFail(fn() => CRM_Mosaicogallery_Groups::create('Eleventh'), 'hard ten-group limit');
expectFail(fn() => CRM_Mosaicogallery_Groups::create('group 1'), 'duplicate heading');

CRM_Mosaicogallery_Groups::rename(1, 'Club logos');
check(CRM_Mosaicogallery_Groups::all()[0]['title'] === 'Club logos', 'rename');
check(CRM_Mosaicogallery_Groups::assign(['header.jpg', 'photo.png'], 1)['updated'] === 2, 'batch assignment');
check(CRM_Mosaicogallery_Groups::assignments()['header.jpg'] === 1, 'assignment persisted');
CRM_Mosaicogallery_Groups::assign(['photo.png'], '');
check(!isset(CRM_Mosaicogallery_Groups::assignments()['photo.png']), 'ungrouping');
expectFail(fn() => CRM_Mosaicogallery_Groups::assign(['../bad.jpg'], 1), 'invalid filename');
expectFail(fn() => CRM_Mosaicogallery_Groups::assign(['header.jpg'], 11), 'invalid group');

CRM_Mosaicogallery_Groups::remove(1);
check(!isset(CRM_Core_DAO::$assignments['header.jpg']), 'removing group only unassigns');
check(count(CRM_Mosaicogallery_Groups::all()) === 9, 'group removed');
$group = CRM_Mosaicogallery_Groups::create('Reused slot');
check($group['id'] === 1, 'freed slot reused');
echo "Image groups: ten-group limit, rename, assign, ungroup, remove passed.\n";
