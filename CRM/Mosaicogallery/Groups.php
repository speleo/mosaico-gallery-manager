<?php
/**
 * Database-only image grouping. Groups are slots 1–10, so the limit remains
 * enforceable even with simultaneous create requests. Image URLs never change.
 */
class CRM_Mosaicogallery_Groups {

  public static function available() {
    return CRM_Core_DAO::checkTableExists('civicrm_mosaicogallery_group')
      && CRM_Core_DAO::checkTableExists('civicrm_mosaicogallery_image_group');
  }

  public static function createTables() {
    CRM_Core_DAO::executeQuery(<<<'SQL'
CREATE TABLE IF NOT EXISTS `civicrm_mosaicogallery_group` (
  `id` TINYINT UNSIGNED NOT NULL,
  `title` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
    );
    CRM_Core_DAO::executeQuery(<<<'SQL'
CREATE TABLE IF NOT EXISTS `civicrm_mosaicogallery_image_group` (
  `filename` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `group_id` TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (`filename`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
    );
    return TRUE;
  }

  public static function upgrade($taskContext = NULL) {
    return self::createTables();
  }

  private static function requireTables() {
    if (!self::available()) {
      throw new RuntimeException('Gallery groups need a database upgrade. Run cv ext:upgrade-db or apply pending upgrades in CiviCRM Extensions.');
    }
  }

  public static function all() {
    if (!self::available()) {
      return [];
    }
    $items = [];
    $dao = CRM_Core_DAO::executeQuery('SELECT id, title FROM civicrm_mosaicogallery_group ORDER BY id');
    while ($dao->fetch()) {
      $items[] = ['id' => (int) $dao->id, 'title' => (string) $dao->title];
    }
    return $items;
  }

  private static function checkTitle($title) {
    $title = trim($title);
    if ($title === '' || !preg_match('//u', $title) || preg_match_all('/./us', $title) > 80) {
      throw new InvalidArgumentException('Enter a group heading of 1–80 characters.');
    }
    if (preg_match('/[\\x00-\\x1F\\x7F]/', $title)) {
      throw new InvalidArgumentException('Group headings cannot contain control characters.');
    }
    return $title;
  }

  public static function create($title) {
    self::requireTables();
    $title = self::checkTitle($title);
    // Fixed IDs 1..10 make 10 a hard limit, even under concurrent requests.
    $existing = self::all();
    foreach ($existing as $group) {
      if (strcasecmp($group['title'], $title) === 0) {
        throw new InvalidArgumentException('A group already has that heading.');
      }
    }
    for ($id = 1; $id <= 10; $id++) {
      try {
        CRM_Core_DAO::executeQuery(
          'INSERT INTO civicrm_mosaicogallery_group (id, title) VALUES (%1, %2)',
          [1 => [$id, 'Integer'], 2 => [$title, 'String']]
        );
        return ['id' => $id, 'title' => $title];
      }
      catch (Throwable $e) {
        // Retry a slot only if another request has occupied that slot.
        $rows = self::all();
        foreach ($rows as $row) {
          if (strcasecmp($row['title'], $title) === 0) {
            throw new InvalidArgumentException('A group already has that heading.');
          }
          if ($row['id'] === $id) {
            continue 2;
          }
        }
        throw $e;
      }
    }
    throw new InvalidArgumentException('Maximum of 10 groups reached. Rename or remove an existing group first.');
  }

  private static function validId($groupId) {
    if (!is_numeric($groupId) || (string) (int) $groupId !== (string) $groupId || (int) $groupId < 1 || (int) $groupId > 10) {
      throw new InvalidArgumentException('Invalid group.');
    }
    return (int) $groupId;
  }

  public static function requireGroup($groupId) {
    self::requireTables();
    $groupId = self::validId($groupId);
    foreach (self::all() as $group) {
      if ($group['id'] === $groupId) {
        return $groupId;
      }
    }
    throw new InvalidArgumentException('That group no longer exists. Reload the gallery.');
  }

  public static function rename($groupId, $title) {
    $groupId = self::requireGroup($groupId);
    $title = self::checkTitle($title);
    foreach (self::all() as $group) {
      if ($group['id'] !== $groupId && strcasecmp($group['title'], $title) === 0) {
        throw new InvalidArgumentException('A group already has that heading.');
      }
    }
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_mosaicogallery_group SET title = %1 WHERE id = %2',
      [1 => [$title, 'String'], 2 => [$groupId, 'Integer']]
    );
    return ['id' => $groupId, 'title' => $title];
  }

  public static function remove($groupId) {
    $groupId = self::requireGroup($groupId);
    // Removing a label only ungroups images; it never touches physical files.
    CRM_Core_DAO::executeQuery(
      'DELETE FROM civicrm_mosaicogallery_image_group WHERE group_id = %1',
      [1 => [$groupId, 'Integer']]
    );
    CRM_Core_DAO::executeQuery(
      'DELETE FROM civicrm_mosaicogallery_group WHERE id = %1',
      [1 => [$groupId, 'Integer']]
    );
    return TRUE;
  }

  public static function assignments() {
    if (!self::available()) {
      return [];
    }
    $rows = [];
    $dao = CRM_Core_DAO::executeQuery(
      'SELECT g.filename, g.group_id FROM civicrm_mosaicogallery_image_group g ' .
      'INNER JOIN civicrm_mosaicogallery_group c ON c.id = g.group_id'
    );
    while ($dao->fetch()) {
      $rows[(string) $dao->filename] = (int) $dao->group_id;
    }
    return $rows;
  }

  public static function assign($filenames, $groupId) {
    self::requireTables();
    if (!is_array($filenames) || !$filenames || count($filenames) > 100) {
      throw new InvalidArgumentException('Select between 1 and 100 images.');
    }
    if ($groupId !== '' && $groupId !== NULL) {
      $groupId = self::requireGroup($groupId);
    }
    else {
      $groupId = NULL; // Ungrouped.
    }
    $files = CRM_Mosaicogallery_Util::files();
    foreach ($filenames as $filename) {
      if (!is_string($filename) || !array_key_exists($filename, $files)) {
        throw new InvalidArgumentException('At least one selected image is no longer in the gallery.');
      }
    }
    foreach (array_unique($filenames) as $filename) {
      if ($groupId === NULL) {
        CRM_Core_DAO::executeQuery(
          'DELETE FROM civicrm_mosaicogallery_image_group WHERE filename = %1',
          [1 => [$filename, 'String']]
        );
      }
      else {
        CRM_Core_DAO::executeQuery(
          'INSERT INTO civicrm_mosaicogallery_image_group (filename, group_id) VALUES (%1, %2) ' .
          'ON DUPLICATE KEY UPDATE group_id = VALUES(group_id)',
          [1 => [$filename, 'String'], 2 => [$groupId, 'Integer']]
        );
      }
    }
    return ['updated' => count(array_unique($filenames))];
  }
}
