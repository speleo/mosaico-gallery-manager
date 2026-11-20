/**
 * Static contract for theme-aware full-width gallery image sizing.
 * This detects accidental removal/weakening of the River override without
 * needing WordPress or a browser from the CI runner.
 */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.join(__dirname, '..');

const css = fs.readFileSync(path.join(root, 'css/gallery-image-parity-0.2.8.css'), 'utf8');
const template = fs.readFileSync(path.join(root, 'templates/CRM/Mosaicogallery/Page/Gallery.tpl'), 'utf8');
const page = fs.readFileSync(path.join(root, 'CRM/Mosaicogallery/Page/Gallery.php'), 'utf8');
const admin = fs.readFileSync(path.join(root, 'js/admin.js'), 'utf8');

const noComments = css.replace(/\/\*[\s\S]*?\*\//g, '');
const rules = Array.from(noComments.matchAll(/([^{}]+)\{([^{}]+)\}/g),
  ([, selector, declarations]) => [selector.trim(), declarations]);
assert.equal(rules.length, 5, 'one card, base button, base image, River button and River image rule');
assert.ok(rules.every(([selector]) => selector.startsWith('#mosaicogallery-app[data-mg-style-profile')),
  'parity styles must never affect other CRM/Mosaico image or button controls');

const forRule = (selector) => {
  const matches = rules.filter(([name]) => name.includes(selector));
  assert.ok(matches.length, 'required rule absent: ' + selector);
  return matches.map(([, declarations]) => declarations).join('\n');
};

const button = forRule('[data-mg-style-profile] .mg-grid > article.mg-card > button.mg-picture.mg-picture-select');
assert.match(button, /width:\s*100%\s*!important/, 'thumbnail button fills card');
assert.match(button, /aspect-ratio:\s*4\s*\/\s*3\s*!important/, 'all thumbnails have uniform 4:3 ratio');
assert.match(button, /position:\s*relative\s*!important/, 'image has positioning context');
assert.match(button, /padding:\s*0\s*!important/, 'theme button padding cannot shrink image');
assert.match(button, /min-height:\s*0\s*!important/, 'theme button minimum height reset');

const img = forRule('[data-mg-style-profile] .mg-grid > article.mg-card > button.mg-picture.mg-picture-select > img');
assert.match(img, /position:\s*absolute\s*!important/, 'image fills the frame without affecting its height');
assert.match(img, /width:\s*100%\s*!important/, 'image fills full tile width');
assert.match(img, /height:\s*100%\s*!important/, 'image fills full 4:3 picture area');
assert.match(img, /max-width:\s*none\s*!important/, 'theme maximum image width removed');
assert.match(img, /object-fit:\s*cover\s*!important/, 'images fill area without distortion');

const riverButton = forRule('[data-mg-style-profile="river"] .mg-grid > article.mg-card > button.mg-picture.mg-picture-select');
const riverImage = forRule('[data-mg-style-profile="river"] .mg-grid > article.mg-card > button.mg-picture.mg-picture-select > img');
assert.match(riverButton, /inline-size:\s*100%\s*!important/);
assert.match(riverImage, /inline-size:\s*100%\s*!important/);
assert.match(riverImage, /object-fit:\s*cover\s*!important/);

assert.match(page, /CRM_Mosaicogallery_ThemeProfile::detect\(\)/,
  'active CiviCRM theme must be resolved for each Gallery Manager request');
assert.match(page, /assign\('galleryThemeProfile',\s*\$theme\['profile'\]\)/,
  'resolved profile must reach the template');
assert.ok(page.indexOf('css/admin.css') < page.indexOf('css/gallery-image-parity-0.2.8.css'),
  'parity rules must load AFTER the base CSS');
assert.match(template, /data-mg-style-profile="\{\$galleryThemeProfile\|escape:'html'\}"/,
  'CiviCRM theme profile must be scoped to Gallery Manager root');
assert.match(admin, /picture\.appendChild\(img\)/, 'image must remain inside its tile-width button');

console.log('Gallery thumbnail parity: full tile width, 4:3 aspect, River detection and CSS isolation passed.');
