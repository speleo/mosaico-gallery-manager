<?php
/**
 * Tests that automatic usage indexing runs only for unindexed, new or stale
 * *existing* image files. Old orphaned metadata must never cause a loop.
 */
class UsageRows {
  private array $rows;
  public function __construct(array $rows) { $this->rows = $rows; }
  public function fetch() {
    if (!$this->rows) { return FALSE; }
    foreach (array_shift($this->rows) as $key => $value) {
      $this->$key = $value;
    }
    return TRUE;
  }
}
class CRM_Core_DAO {
  public static array $rows = [];
  public static int $queries = 0;
  public static function executeQuery($sql) {
    if (!str_contains($sql, 'last_indexed') || !str_contains($sql, '24 HOUR')) {
      throw new RuntimeException('Unexpected usage freshness SQL: ' . $sql);
    }
    self::$queries++;
    return new UsageRows(self::$rows);
  }
}
require_once dirname(__DIR__) . '/CRM/Mosaicogallery/Util.php';
function verify($condition, $message) {
  if (!$condition) { throw new RuntimeException($message); }
}
$files = ['photo.jpg' => [], 'logo.png' => []];
verify(!CRM_Mosaicogallery_Util::usageIndexDue([]), 'empty directory needs no scan');
verify(CRM_Core_DAO::$queries === 0, 'empty directory avoids query');

verify(CRM_Mosaicogallery_Util::usageIndexDue($files), 'new files require initial indexing');
CRM_Core_DAO::$rows = [
  ['filename' => 'photo.jpg', 'stale' => 0],
  ['filename' => 'logo.png', 'stale' => 0],
];
verify(!CRM_Mosaicogallery_Util::usageIndexDue($files), 'fresh persisted counts are reused');

CRM_Core_DAO::$rows[1]['stale'] = 1;
verify(CRM_Mosaicogallery_Util::usageIndexDue($files), '24-hour-old counts trigger refresh');

CRM_Core_DAO::$rows = [
  ['filename' => 'photo.jpg', 'stale' => 0],
];
verify(CRM_Mosaicogallery_Util::usageIndexDue($files), 'new upload without a row triggers refresh');

CRM_Core_DAO::$rows = [
  ['filename' => 'photo.jpg', 'stale' => 0],
  ['filename' => 'logo.png', 'stale' => 0],
  ['filename' => 'deleted.jpg', 'stale' => 1],
];
verify(!CRM_Mosaicogallery_Util::usageIndexDue($files), 'old metadata for missing files cannot trigger endless refreshes');
echo "Automatic usage index freshness checks passed.\n";
