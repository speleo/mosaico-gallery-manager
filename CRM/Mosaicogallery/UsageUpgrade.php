<?php
/**
 * Schema migration for persistent image reference index version.
 * Existing usage counts are not destroyed or changed by the migration.
 */
class CRM_Mosaicogallery_UsageUpgrade {
  public static function needed() {
    return !CRM_Core_BAO_SchemaHandler::checkIfFieldExists(
      'civicrm_mosaicogallery_image', 'usage_index_version'
    );
  }

  public static function upgrade($taskContext = NULL) {
    if (self::needed()) {
      CRM_Core_DAO::executeQuery(
        'ALTER TABLE civicrm_mosaicogallery_image ' .
        'ADD COLUMN usage_index_version SMALLINT UNSIGNED NOT NULL DEFAULT 0'
      );
    }
    return TRUE;
  }
}
