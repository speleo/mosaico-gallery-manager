<?php
/**
 * File inventory and references index. Intentionally read-only for files.
 */
class CRM_Mosaicogallery_Util {
  const USAGE_INDEX_VERSION = 2;
  public static function config() {
    if (!class_exists('CRM_Mosaico_Utils')) {
      throw new RuntimeException('Mosaico is not enabled (CRM_Mosaico_Utils unavailable).');
    }
    $cfg = CRM_Mosaico_Utils::getConfig();
    $base = realpath($cfg['BASE_DIR'] . $cfg['UPLOADS_DIR']);
    if (!$base || !is_dir($base) || !is_readable($base)) {
      throw new RuntimeException('Cannot access the configured Mosaico uploads directory.');
    }
    $cfg['_dir'] = $base;
    $cfg['_url'] = rtrim($cfg['BASE_URL'], '/') . '/' . trim($cfg['UPLOADS_URL'], '/') . '/';
    return $cfg;
  }

  public static function files() {
    $cfg = self::config();
    $items = [];
    $iterator = new DirectoryIterator($cfg['_dir']);
    foreach ($iterator as $entry) {
      if ($entry->isDot() || !$entry->isFile() || $entry->isLink()) {
        continue;
      }
      $filename = $entry->getFilename();
      if (!preg_match('/\.(?:jpe?g|png|gif|webp)$/iD', $filename)) {
        continue;
      }
      $items[$filename] = [
        'name' => $filename,
        'url' => $cfg['_url'] . rawurlencode($filename),
        'mtime' => $entry->getMTime(),
        'size' => $entry->getSize(),
      ];
    }
    return $items;
  }

  public static function metadata() {
    $rows = [];
    $dao = CRM_Core_DAO::executeQuery('SELECT filename, archived, favourite, usage_count FROM civicrm_mosaicogallery_image');
    while ($dao->fetch()) {
      $rows[$dao->filename] = [
        'archived' => (bool) $dao->archived,
        'favourite' => (bool) $dao->favourite,
        'usage_count' => (int) $dao->usage_count,
      ];
    }
    foreach (CRM_Mosaicogallery_Groups::assignments() as $name => $groupId) {
      if (!isset($rows[$name])) {
        $rows[$name] = ['archived' => FALSE, 'favourite' => FALSE, 'usage_count' => 0];
      }
      $rows[$name]['group_id'] = $groupId;
    }
    return $rows;
  }

  public static function syncInventory() {
    $files = self::files();
    $known = self::metadata();
    $added = 0;
    foreach (array_keys($files) as $name) {
      if (!isset($known[$name])) {
        CRM_Core_DAO::executeQuery(
          'INSERT IGNORE INTO civicrm_mosaicogallery_image (filename) VALUES (%1)',
          [1 => [$name, 'String']]
        );
        $added++;
      }
    }
    // Do NOT delete records for missing files: the file may return after a restore.
    return ['added' => $added, 'total' => count($files)];
  }

  public static function listing($filter, $sort, $search, $page = 1, $limit = 60, $groupFilter = 'all') {
    $files = self::files();
    $meta = self::metadata();
    $groups = CRM_Mosaicogallery_Groups::all();
    $validGroupIds = array_column($groups, 'id');
    if ($groupFilter !== 'all' && $groupFilter !== 'ungrouped') {
      $groupId = ctype_digit((string) $groupFilter) ? (int) $groupFilter : 0;
      if (!$groupId || !in_array($groupId, $validGroupIds, TRUE)) {
        throw new InvalidArgumentException('Unknown image group filter.');
      }
      $groupFilter = (string) $groupId;
    }
    $allCount = count($files);
    $activeCount = 0;
    $archivedCount = 0;
    $groupCounts = [];
    $matched = [];
    foreach ($files as $name => $file) {
      $row = $meta[$name] ?? ['archived' => FALSE, 'favourite' => FALSE, 'usage_count' => 0];
      $file += $row;
      $file['group_id'] = (int) ($file['group_id'] ?? 0);
      if ($file['group_id']) {
        $groupCounts[$file['group_id']] = ($groupCounts[$file['group_id']] ?? 0) + 1;
      }
      $file['date'] = gmdate('Y-m-d H:i', $file['mtime']) . ' UTC';
      $file['archived'] ? $archivedCount++ : $activeCount++;
      if ($filter === 'active' && $file['archived']) { continue; }
      if ($filter === 'archived' && !$file['archived']) { continue; }
      if ($filter === 'favourites' && !$file['favourite']) { continue; }
      if ($groupFilter === 'ungrouped' && $file['group_id'] !== 0) { continue; }
      if ($groupFilter !== 'all' && $groupFilter !== 'ungrouped' && $file['group_id'] !== (int) $groupFilter) { continue; }
      if ($search !== '' && stripos($name, $search) === FALSE) { continue; }
      $matched[] = $file;
    }
    foreach ($groups as &$group) {
      $group['count'] = $groupCounts[$group['id']] ?? 0;
    }
    unset($group);
    usort($matched, static function ($a, $b) use ($sort) {
      if ($sort === 'popular') {
        $v = $b['usage_count'] <=> $a['usage_count'];
        if ($v) { return $v; }
      }
      if ($sort === 'name') {
        return strnatcasecmp($a['name'], $b['name']);
      }
      if ($sort === 'favourites') {
        $v = $b['favourite'] <=> $a['favourite'];
        if ($v) { return $v; }
      }
      return ($b['mtime'] <=> $a['mtime']) ?: strnatcasecmp($a['name'], $b['name']);
    });
    $count = count($matched);
    $page = max(1, (int) $page);
    $limit = max(1, min(100, (int) $limit));
    return [
      'items' => array_slice($matched, ($page - 1) * $limit, $limit),
      'groups' => $groups,
      'group_schema_ready' => CRM_Mosaicogallery_Groups::available(),
      'total' => $count,
      'active' => $activeCount,
      'archived' => $archivedCount,
      'all' => $allCount,
      'page' => $page,
      'pages' => max(1, (int) ceil($count / $limit)),
    ];
  }

  public static function setFlag($filename, $flag, $enabled) {
    if (!in_array($flag, ['archived', 'favourite'], TRUE)) {
      throw new InvalidArgumentException('Unknown flag.');
    }
    if (!array_key_exists($filename, self::files())) {
      throw new InvalidArgumentException('Image does not exist in the current gallery.');
    }
    CRM_Core_DAO::executeQuery(
      'INSERT INTO civicrm_mosaicogallery_image (filename, ' . $flag . ') VALUES (%1, %2) ON DUPLICATE KEY UPDATE ' . $flag . ' = VALUES(' . $flag . ')',
      [1 => [$filename, 'String'], 2 => [(int) $enabled, 'Integer']]
    );
  }

  /**
   * Persisted usage_count values are reused until a new image appears or the
   * matching algorithm version changes. No daily rescan on page opening.
   */
  public static function usageIndexDue($files = NULL) {
    $files = $files === NULL ? self::files() : $files;
    if (!$files) {
      return FALSE;
    }
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists(
      'civicrm_mosaicogallery_image', 'usage_index_version'
    )) {
      throw new RuntimeException('Gallery usage index upgrade required. Apply the pending Mosaico Gallery Manager database upgrade.');
    }
    $fresh = [];
    $dao = CRM_Core_DAO::executeQuery(
      'SELECT filename, last_indexed, usage_index_version FROM civicrm_mosaicogallery_image'
    );
    while ($dao->fetch()) {
      $fresh[(string) $dao->filename] =
        !empty($dao->last_indexed) && (int) $dao->usage_index_version === self::USAGE_INDEX_VERSION;
    }
    foreach ($files as $filename => $_file) {
      if (empty($fresh[$filename])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Count distinct stored mailing/template records referencing each image.
   * Counts are NOT number of recipients or opens. Missing tables are reported.
   */
  public static function refreshUsage() {
    self::syncInventory();
    $files = self::files();
    $counts = array_fill_keys(array_keys($files), 0);
    // Each record contributes at most one hit per image, regardless of the
    // number of references in that record or in its component fields.
    $tables = [
      ['civicrm_mailing', 'body_html'],
      ['civicrm_mosaico_template', 'CONCAT_WS(" ", html, metadata, content)'],
      ['civicrm_msg_template', 'msg_html'],
    ];
    $sources = 0;
    $errors = [];
    foreach ($tables as $table) {
      [$name, $expr] = $table;
      try {
        $dao = CRM_Core_DAO::executeQuery('SELECT ' . $expr . ' AS document FROM ' . $name);
        while ($dao->fetch()) {
          $sources++;
          $found = self::extractNames((string) ($dao->document ?? ''));
          foreach ($found as $filename) {
            if (isset($counts[$filename])) {
              $counts[$filename]++;
            }
          }
        }
      }
      catch (Throwable $e) {
        $errors[] = $name . ': ' . $e->getMessage();
      }
    }
    if ($errors) {
      // Never wipe previously calculated statistics on a partial failure.
      throw new RuntimeException('Usage indexing incomplete; counts unchanged. ' . implode('; ', $errors));
    }
    // Database writes happen only after all source scans succeeded.
    foreach ($counts as $filename => $count) {
      CRM_Core_DAO::executeQuery(
        'UPDATE civicrm_mosaicogallery_image SET usage_count = %1, last_indexed = NOW(), usage_index_version = %3 WHERE filename = %2',
        [1 => [$count, 'Integer'], 2 => [$filename, 'String'], 3 => [self::USAGE_INDEX_VERSION, 'Integer']]
      );
    }
    return ['sources' => $sources, 'images' => count($counts)];
  }

  public static function extractNames($document) {
    // Handle HTML/CSS URLs, Mosaico editable JSON and nested URL encoding.
    // Do not treat an arbitrary filename mention as proof of image usage.
    $document = str_replace(['\/', '\u002f', '\u002F'], '/', $document);
    $document = html_entity_decode($document, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $names = [];
    $pattern = <<<'REGEX'
~images/uploads/(?:static/|thumbnails/)?([^/?#&"'<>\\\x00-\x1F]+?\.(?:jpe?g|png|gif|webp))(?=$|[/?#&"'<>\\\s])~iu
REGEX;
    for ($pass = 0; $pass < 3; $pass++) {
      $matches = [];
      preg_match_all($pattern, $document, $matches);
      foreach ($matches[1] ?? [] as $name) {
        $names[$name] = TRUE;
      }
      $decoded = rawurldecode($document);
      if ($decoded === $document) {
        break;
      }
      $document = $decoded;
    }
    return array_keys($names);
  }
}
