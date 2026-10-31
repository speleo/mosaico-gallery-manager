<?php
/** Regression checks for the hook, independent of CiviCRM runtime. */
require_once dirname(__DIR__) . '/mosaicogallery.php';

function check($ok, $label) {
  if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
}

foreach (['Mailing', 'Mailings'] as $rootName) {
  $menu = [
    5 => ['attributes' => ['name' => $rootName, 'navID' => 5], 'child' => [
      6 => ['attributes' => ['name' => 'other', 'navID' => 6, 'parentID' => 5]],
    ]],
    15 => ['attributes' => ['name' => 'Reports', 'navID' => 15]],
  ];
  mosaicogallery_civicrm_navigationMenu($menu);
  $added = $menu[5]['child'][16]['attributes'] ?? NULL;
  check(is_array($added), 'created a keyed child entry');
  check($added['navID'] === 16, 'allocated globally unique nav ID');
  check($added['parentID'] === 5, 'attached to Mailings parent');
  check($added['url'] === 'civicrm/mosaicogallery', 'correct route');
  check($added['permission'] === 'administer CiviCRM', 'restricted to admins');
  mosaicogallery_civicrm_navigationMenu($menu);
  check(count($menu[5]['child']) === 2, 'idempotent on repeat calls');
}

$withoutMailings = [1 => ['attributes' => ['name' => 'Administer', 'navID' => 1]]];
mosaicogallery_civicrm_navigationMenu($withoutMailings);
check(count($withoutMailings) === 1, 'missing Mailings parent does not inject stray menu');

echo "Navigation menu regression checks passed.\n";
