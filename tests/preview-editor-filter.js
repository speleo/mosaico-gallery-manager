// Editor gallery response filter, including custom group headings.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const config = {
  groups: [{id: 1, title: 'Club logos'}, {id: 2, title: 'Events'}],
  entries: {
    'old.jpg': {archived: true, mtime: 1, group_id: 1},
    'new.jpg': {archived: false, mtime: 5, usage_count: 1, group_id: 1},
    'popular.jpg': {archived: false, mtime: 2, usage_count: 12, group_id: 0},
    'event.jpg': {archived: false, mtime: 4, usage_count: 2, group_id: 2},
  },
  managerUrl: '/civicrm/mosaicogallery',
  canManage: false,
};
const files = ['old.jpg','popular.jpg','new.jpg','event.jpg'].map(name => ({
  name, url: '/images/uploads/' + name
}));
const source = fs.readFileSync(__dirname + '/../js/editor.js', 'utf8')
  .replace('/*__GALLERY_DATA__*/null', JSON.stringify(config));

function gallery(group) {
  let handler;
  const jquery = { ajaxPrefilter(fn) { handler = fn; } };
  const document = {readyState:'loading', addEventListener(){}};
  vm.runInNewContext(source, {
    window: {jQuery: jquery}, document, console,
    localStorage: {getItem: key => key === 'mosaicogallery-group' ? group : 'recent'},
  });
  assert.equal(typeof handler, 'function');
  const options = {type:'GET', url:'/civicrm/mosaico/upload'};
  handler(options);
  const filtered = JSON.parse(options.dataFilter(JSON.stringify({files}), 'json'));
  const post = {type:'POST',url:'/civicrm/mosaico/upload'};
  handler(post);
  assert.equal(post.dataFilter, undefined, 'Must never intercept upload POSTs');
  return filtered.files.map(x=>x.name);
}
assert.deepEqual(gallery('all'), ['new.jpg','event.jpg','popular.jpg']);
assert.deepEqual(gallery('1'), ['new.jpg'], 'Custom Club logos group');
assert.deepEqual(gallery('2'), ['event.jpg'], 'Custom Events group');
assert.deepEqual(gallery('ungrouped'), ['popular.jpg'], 'Ungrouped images');
assert.deepEqual(gallery('99'), ['new.jpg','event.jpg','popular.jpg'], 'Deleted group falls back to all');
console.log('Editor image groups and archive filters: PASS');
