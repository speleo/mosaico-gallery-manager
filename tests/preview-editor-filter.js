// Verify embedded Mosaico image filters, live Knockout updates, and no POST interception.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const config = {
  groups: [{id: 1, title: 'People'}, {id: 2, title: 'Events'}],
  entries: {
    'old.jpg': {archived: true, mtime: 1, group_id: 1},
    'new.jpg': {archived: false, mtime: 5, usage_count: 1, group_id: 1},
    'popular.jpg': {archived: false, mtime: 2, usage_count: 12, group_id: 0},
    'event.jpg': {archived: false, mtime: 4, usage_count: 2, group_id: 2},
  },
  managerUrl: '/civicrm/mosaicogallery', canManage: true,
};
const files = ['old.jpg','popular.jpg','new.jpg','event.jpg'].map(name => ({
  name, url: '/images/uploads/' + name
}));
const source = fs.readFileSync(__dirname + '/../js/editor.js','utf8')
  .replace('/*__GALLERY_DATA__*/null', JSON.stringify(config));

class Element {
  constructor(tag) {
    this.tag = tag; this.children = []; this.events = {}; this.attrs = {};
    this.textContent = ''; this.value = ''; this.style = {}; this.id = '';
  }
  appendChild(child) {this.children.push(child); return child;}
  insertBefore(child) {this.children.unshift(child); return child;}
  get firstChild() {return this.children[0] || null;}
  addEventListener(name, fn) {this.events[name] = fn;}
  setAttribute(name, value) {this.attrs[name] = value;}
  querySelector(selector) {
    return this.children.find(child => child.id === selector.slice(1)) || null;
  }
}

function gallery(group) {
  let prefilter;
  const jquery = {ajaxPrefilter(fn) {prefilter = fn;}};
  const panel = new Element('div');
  const document = {
    readyState: 'complete',
    body: new Element('body'),
    getElementById: id => id === 'toolimagesgallery' ? panel : null,
    createElement: tag => new Element(tag),
    createTextNode: text => ({textContent: text}),
  };
  const stored = {};
  const localStorage = {
    getItem(key) {return key === 'mosaicogallery-group' ? group : null;},
    setItem(key, value) {stored[key] = value;},
  };
  const window = {
    jQuery: jquery,
    // The Mosaico plugin hook supplies the VM even without window.ko.
    requestAnimationFrame(fn) {fn();},
  };
  vm.runInNewContext(source, {window, document, localStorage, console});
  assert.equal(typeof prefilter, 'function');
  assert.equal(panel.children.length, 1, 'filters are embedded in the gallery itself');
  assert.equal(panel.children[0].id, 'mg-gallery-filters');
  assert.equal(document.body.children.length, 0, 'no floating desktop toolbar');

  const options = {type:'GET',url:'/civicrm/mosaico/upload'};
  prefilter(options);
  const initial = JSON.parse(options.dataFilter(JSON.stringify({files}), 'json')).files;
  const post = {type:'POST',url:'/civicrm/mosaico/upload'};
  prefilter(post);
  assert.equal(post.dataFilter, undefined, 'must not filter upload POSTs');

  let live = [], galleryCount = -1, firstPage = 0;
  const liveGallery = function (value) {if (value) {live = value;} return live;};
  liveGallery.moveFirst = function () {firstPage++;};
  const viewModel = {
    galleryRemote: liveGallery,
    galleryLoaded(value) {if (value !== undefined) {galleryCount = value;} return galleryCount;},
    remoteFileProcessor(item) {return item;},
  };
  assert.equal(typeof window.mosaicogalleryBindViewModel, 'function');
  // Mosaico initially reverses the JSON list before assigning galleryRemote.
  liveGallery(initial.slice().reverse());
  window.mosaicogalleryBindViewModel(viewModel);
  const filterBar = panel.children[0], filterRow = filterBar.children[1];
  const groupSelect = filterRow.children[0].children[1];
  const orderSelect = filterRow.children[1].children[1];
  return {
    list: () => Array.from(live, item => item.name),
    initial: initial.map(item => item.name),
    group: value => {groupSelect.value = value; groupSelect.events.change();},
    order: value => {orderSelect.value = value; orderSelect.events.change();},
    count: () => galleryCount,
    first: () => firstPage,
    stored,
    panel,
  };
}

const all = gallery('all');
assert.deepEqual(all.list(), ['new.jpg','event.jpg','popular.jpg']);
all.group('1');
assert.deepEqual(all.list(), ['new.jpg'], 'changing group updates gallery immediately');
assert.equal(all.count(), 1);
all.group('ungrouped');
assert.deepEqual(all.list(), ['popular.jpg']);
all.group('all');
all.order('popular');
assert.deepEqual(all.list(), ['popular.jpg','event.jpg','new.jpg'], 'sort applies immediately');
assert.ok(all.first() >= 3, 'group/order changes reset gallery paging');
assert.equal(all.stored['mosaicogallery-order'], 'popular');
assert.equal(all.stored['mosaicogallery-group'], 'all');

assert.deepEqual(gallery('1').list(), ['new.jpg']);
assert.deepEqual(gallery('2').list(), ['event.jpg']);
assert.deepEqual(gallery('ungrouped').list(), ['popular.jpg']);
assert.deepEqual(gallery('99').list(), ['new.jpg','event.jpg','popular.jpg'], 'deleted group resets to all');
console.log('Embedded dynamic Mosaico gallery filters: PASS');
