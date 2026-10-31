/* Regression: WordPress admin.php?page=CiviCRM must survive gallery pagination. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '../js/admin.js'), 'utf8');
const php = fs.readFileSync(path.join(__dirname, '../CRM/Mosaicogallery/Page/Api.php'), 'utf8');
assert.match(php, /\$_GET\['mg_page'\]/, 'PHP API must accept mg_page');
assert.doesNotMatch(php, /\$_GET\['page'\]/, 'PHP API must not read WordPress page route');

class Element {
  constructor(tag) {
    this.tagName = tag;
    this.children = [];
    this.attrs = {};
    this.events = {};
    this.textContent = '';
    this.className = '';
  }
  getAttribute(name) { return this.attrs[name] ?? null; }
  setAttribute(name, value) { this.attrs[name] = value; }
  removeAttribute(name) { delete this.attrs[name]; }
  appendChild(child) { this.children.push(child); return child; }
  replaceChildren(...children) { this.children = children; }
  addEventListener(event, fn) { this.events[event] = fn; }
}

const ids = new Map();
for (const id of ['mosaicogallery-app', 'mg-grid', 'mg-pagination', 'mg-summary',
  'mg-status', 'mg-filter', 'mg-sort', 'mg-search', 'mg-sync', 'mg-usage',
  'mg-group-filter', 'mg-group-count', 'mg-create-group', 'mg-new-group', 'mg-group-list',
  'mg-bulk-group', 'mg-bulk-count', 'mg-bulk-apply', 'mg-select-all']) {
  ids.set(id, new Element('div'));
}
const root = ids.get('mosaicogallery-app');
// Intentionally give the gallery a broken legacy API URL: it should reuse
// the working manager route, not create another WordPress admin page.
root.setAttribute('data-api', '/wp/wp-admin/admin.php?page=1&q=civicrm%2Fmosaicogallery%2Fapi');
root.setAttribute('data-token', 'fake');
const original = 'https://bec-cave.org.uk/wp/wp-admin/admin.php?page=CiviCRM&q=civicrm%2Fmosaicogallery&reset=1';
const requests = [];
const posted = [];
const context = {
  document: {
    readyState: 'complete',
    getElementById: id => ids.get(id),
    createElement: tag => new Element(tag),
  },
  location: {href: original},
  URL, URLSearchParams,
  setTimeout, clearTimeout,
  fetch: async (url, options) => {
    const parsed = new URL(url);
    requests.push(parsed);
    if (options.method === 'POST') {
      posted.push(new URLSearchParams(options.body));
      return {
        ok: true, status: 200, headers: {get: () => 'application/json'},
        text: async () => JSON.stringify({ok: true, data: {updated: 1}}),
      };
    }
    const page = Number(parsed.searchParams.get('mg_page'));
    return {
      ok: true,
      status: 200,
      headers: {get: () => 'application/json'},
      text: async () => JSON.stringify({ok: true, data: {
        items: [{name: 'test.jpg', url: '/images/uploads/test.jpg', usage_count: 2,
          date: '2026-10-31', archived: false, favourite: false, group_id: 0}],
        active: 120, archived: 0, all: 120, total: 120, page, pages: 2,
        groups: [{id: 1, title: 'Club logos', count: 1}], group_schema_ready: true,
      }}),
    };
  },
};
vm.runInNewContext(script, context);
function settle() { return new Promise(resolve => setTimeout(resolve, 5)); }
(async () => {
  await settle();
  assert.equal(requests.length, 1);
  assert.equal(requests[0].searchParams.get('page'), 'CiviCRM');
  assert.equal(requests[0].searchParams.get('mg_page'), '1');
  assert.equal(requests[0].searchParams.get('q'), 'civicrm/mosaicogallery');
  assert.equal(requests[0].searchParams.get('mg_ajax'), '1');
  const next = ids.get('mg-pagination').children[2];
  assert.equal(next.textContent, 'Next →');
  next.events.click();
  await settle();
  assert.equal(requests.length, 2);
  assert.equal(requests[1].searchParams.get('page'), 'CiviCRM');
  assert.equal(requests[1].searchParams.get('mg_page'), '2');
  assert.equal(requests[1].searchParams.getAll('page').length, 1);
  const filter = ids.get('mg-group-filter');
  filter.value = '1';
  filter.events.change.call(filter);
  await settle();
  assert.equal(requests[2].searchParams.get('page'), 'CiviCRM');
  assert.equal(requests[2].searchParams.get('group'), '1');
  assert.equal(requests[2].searchParams.get('mg_page'), '1');

  const card = ids.get('mg-grid').children[0];
  const groupRow = card.children[1].children[3];
  const select = groupRow.children[1];
  select.value = '1';
  select.events.change.call(select);
  await settle();
  assert.equal(posted.length, 1, 'one group assignment request');
  assert.equal(posted[0].get('group_id'), '1');
  assert.equal(posted[0].get('filenames'), JSON.stringify(['test.jpg']));
  assert.equal(requests[3].searchParams.get('page'), 'CiviCRM');
  console.log('WordPress routing and gallery pagination collision checks passed.');
})().catch(err => { console.error(err); process.exit(1); });
