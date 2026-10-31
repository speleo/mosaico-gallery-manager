<?php
/** Versioned usage counts persist without daily scanning. */
class UsageRows {
  private array $rows;
  public function __construct(array $rows) {$this->rows = $rows;}
  public function fetch() {
    if (!$this->rows) {return FALSE;}
    foreach (array_shift($this->rows) as $key => $value) {$this->$key = $value;}
    return TRUE;
  }
}
class CRM_Core_BAO_SchemaHandler {
  public static bool $versionColumn = TRUE;
  public static function checkIfFieldExists($table, $field) {
    if ($table !== 'civicrm_mosaicogallery_image' || $field !== 'usage_index_version') {
      throw new RuntimeException('Unexpected schema lookup');
    }
    return self::$versionColumn;
  }
}
class CRM_Core_DAO {
  public static array $rows = [];
  public static int $queries = 0;
  public static function executeQuery($sql) {
    if (!str_contains($sql, 'usage_index_version') || !str_contains($sql, 'last_indexed')) {
      throw new RuntimeException('Unexpected usage freshness SQL: ' . $sql);
    }
    self::$queries++;
    return new UsageRows(self::$rows);
  }
}
require_once dirname(__DIR__) . '/CRM/Mosaicogallery/Util.php';
function verify($condition, $message) {
  if (!$condition) {throw new RuntimeException($message);}
}
$files = ['photo.jpg' => [], 'logo.png' => []];
verify(!CRM_Mosaicogallery_Util::usageIndexDue([]), 'empty directory needs no scan');
verify(CRM_Core_DAO::$queries === 0, 'empty directory avoids query');

verify(CRM_Mosaicogallery_Util::usageIndexDue($files), 'new files require initial indexing');
CRM_Core_DAO::$rows = [
  ['filename' => 'photo.jpg', 'last_indexed' => '2026-01-01 12:00:00', 'usage_index_version' => 3],
  ['filename' => 'logo.png', 'last_indexed' => '2026-01-01 12:00:00', 'usage_index_version' => 3],
];
verify(!CRM_Mosaicogallery_Util::usageIndexDue($files), 'old-but-valid saved counts reused indefinitely');

CRM_Core_DAO::$rows[1]['usage_index_version'] = 1;
verify(CRM_Mosaicogallery_Util::usageIndexDue($files), 'previous algorithm version forces recount once');
CRM_Core_DAO::$rows[1]['usage_index_version'] = 3;
CRM_Core_DAO::$rows[1]['last_indexed'] = NULL;
verify(CRM_Mosaicogallery_Util::usageIndexDue($files), 'unindexed image forces refresh');

CRM_Core_DAO::$rows = [
  ['filename' => 'photo.jpg', 'last_indexed' => '2026-01-01 12:00:00', 'usage_index_version' => 3],
];
verify(CRM_Mosaicogallery_Util::usageIndexDue($files), 'new upload without index row triggers refresh');

CRM_Core_DAO::$rows = [
  ['filename' => 'photo.jpg', 'last_indexed' => '2026-01-01 12:00:00', 'usage_index_version' => 3],
  ['filename' => 'logo.png', 'last_indexed' => '2026-01-01 12:00:00', 'usage_index_version' => 3],
  ['filename' => 'deleted.jpg', 'last_indexed' => NULL, 'usage_index_version' => 0],
];
verify(!CRM_Mosaicogallery_Util::usageIndexDue($files), 'missing files do not cause endless rescan');
CRM_Core_BAO_SchemaHandler::$versionColumn = FALSE;
try {
  CRM_Mosaicogallery_Util::usageIndexDue($files);
  throw new RuntimeException('Expected explicit upgrade message for missing version column');
}
catch (RuntimeException $e) {
  verify(str_contains($e->getMessage(), 'database upgrade'), 'upgrade required message');
}
echo "Persistent versioned usage-index checks passed.\n";
