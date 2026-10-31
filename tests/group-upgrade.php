<?php
/** Exercise existing-install upgrade queue for groups and usage version. */
class CRM_Core_BAO_SchemaHandler {
  public static bool $usageVersion = FALSE;
  public static function checkIfFieldExists($table, $field) {
    return self::$usageVersion;
  }
}
class CRM_Core_DAO {
  public static bool $tableReady = FALSE;
  public static function checkTableExists($name) {return self::$tableReady;}
  public static function executeQuery($sql) {
    if (str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS')) {
      self::$tableReady = TRUE;
    }
    elseif (str_starts_with($sql, 'ALTER TABLE civicrm_mosaicogallery_image')) {
      CRM_Core_BAO_SchemaHandler::$usageVersion = TRUE;
    }
    else {
      throw new RuntimeException('Unexpected migration SQL');
    }
    return TRUE;
  }
}
class CRM_Queue_Task {
  public $callback;
  public $arguments;
  public function __construct($callback, $arguments = [], $title = NULL) {
    $this->callback = $callback;
    $this->arguments = $arguments;
  }
}
class MockQueue {
  public array $tasks = [];
  public function createItem($task) {$this->tasks[] = $task;}
}
require_once dirname(__DIR__) . '/CRM/Mosaicogallery/Groups.php';
require_once dirname(__DIR__) . '/CRM/Mosaicogallery/UsageUpgrade.php';
require_once dirname(__DIR__) . '/mosaicogallery.php';
if (mosaicogallery_civicrm_upgrade('check') !== [TRUE]) {
  throw new RuntimeException('Missing database schema not detected');
}
$q = new MockQueue();
mosaicogallery_civicrm_upgrade('enqueue', $q);
if (count($q->tasks) !== 2 ||
    $q->tasks[0]->callback !== ['CRM_Mosaicogallery_Groups', 'upgrade'] ||
    $q->tasks[1]->callback !== ['CRM_Mosaicogallery_UsageUpgrade', 'upgrade']) {
  throw new RuntimeException('Both migration tasks must be queued');
}
foreach ($q->tasks as $task) {
  call_user_func($task->callback, NULL);
}
if (mosaicogallery_civicrm_upgrade('check') !== [FALSE]) {
  throw new RuntimeException('Completed schema migration still flagged as pending');
}
$q = new MockQueue();
mosaicogallery_civicrm_upgrade('enqueue', $q);
if ($q->tasks) {throw new RuntimeException('Migrations must be idempotent');}
echo "Existing install group and usage database upgrade queue: PASS\n";
