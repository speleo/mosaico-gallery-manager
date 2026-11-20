<?php
/**
 * Match BEC Membership Tools' per-request CiviCRM theme detection.
 * No CiviCRM installation is required to run these assertions.
 */
require_once dirname(__DIR__) . '/CRM/Mosaicogallery/ThemeProfile.php';

class GalleryTestThemes {
  private $key;
  private $all;
  public function __construct($key, array $all = []) {
    $this->key = $key;
    $this->all = $all;
  }
  public function getActiveThemeKey() { return $this->key; }
  public function getAll() { return $this->all; }
}

$cases = [
  ['theisland', [], 'legacy'],
  ['theisland_legacy', [], 'legacy'],
  ['theislandriver', [], 'river'],
  ['riverlea_walbrook', [], 'river'],
  ['custom_bec', ['search_order' => ['custom_bec', 'riverlea', '_fallback_']], 'river'],
  ['custom_bec', ['search_order' => ['custom_bec', 'theisland_legacy']], 'legacy'],
  ['theisland', ['search_order' => ['theisland', 'riverlea']], 'river'],
];

foreach ($cases as [$key, $metadata, $expected]) {
  $context = CRM_Mosaicogallery_ThemeProfile::detect(
    new GalleryTestThemes($key, [$key => $metadata])
  );
  if ($context['profile'] !== $expected || $context['key'] !== $key) {
    throw new RuntimeException(
      "Theme resolution failed for {$key}: " . json_encode($context)
    );
  }
}

// Missing/unknown theme must retain safe shared gallery geometry.
$fallback = CRM_Mosaicogallery_ThemeProfile::detect(new GalleryTestThemes('', []));
if ($fallback !== ['key' => 'unknown', 'profile' => 'legacy']) {
  throw new RuntimeException('Unknown theme did not use legacy fallback.');
}
echo "Gallery theme profiles: " . count($cases) . " classifications + fallback passed.\n";
