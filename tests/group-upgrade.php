<?php
/** Exercise the existing-install upgrade queue contract. */
class CRM_Core_DAO {
  public static bool $tableReady = FALSE;
  public static function checkTableExists($name) { return self::$tableReady; }
  public static function executeQuery($sql) {
    if (!str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS')) {
      throw new RuntimeException('Unexpected migration SQL');
    }
    self::$tableReady = TRUE;
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
require_once dirname(__DIR__) . '/mosaicogallery.php';
if (mosaicogallery_civicrm_upgrade('check') !== [TRUE]) {
  throw new RuntimeException('Missing database schema not detected');
}
$q = new MockQueue();
mosaicogallery_civicrm_upgrade('enqueue', $q);
if (count($q->tasks) !== 1 || $q->tasks[0]->callback !== ['CRM_Mosaicogallery_Groups', 'upgrade']) {
  throw new RuntimeException('Group schema upgrade task not queued correctly');
}
call_user_func($q->tasks[0]->callback, NULL);
if (mosaicogallery_civicrm_upgrade('check') !== [FALSE]) {
  throw new RuntimeException('Completed schema migration still flagged as pending');
}
echo "Existing install group database upgrade queue: PASS\n";
