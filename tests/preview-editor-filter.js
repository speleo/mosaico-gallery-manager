// A lightweight test for editor response filter; run node tests/preview-editor-filter.js
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let handler;
const jquery = { ajaxPrefilter(fn) { handler = fn; } };
const config = {
  entries: {
    'old.jpg': {archived:true,mtime:1},
    'new.jpg': {archived:false,mtime:5,usage_count:1},
    'popular.jpg': {archived:false,mtime:2,usage_count:12},
  },
  managerUrl: '/civicrm/mosaicogallery', canManage:false,
};
const source = fs.readFileSync(__dirname + '/../js/editor.js','utf8').replace('/*__GALLERY_DATA__*/null', JSON.stringify(config));
const document = {readyState:'loading',addEventListener(){}};
vm.runInNewContext(source, {window:{jQuery:jquery}, document:document, localStorage:{getItem:()=> 'recent'}, console});
assert.equal(typeof handler, 'function');
let options = {type:'GET',url:'/civicrm/mosaico/upload'};
handler(options);
assert.equal(typeof options.dataFilter,'function');
let r=JSON.parse(options.dataFilter(JSON.stringify({files:[
  {name:'old.jpg',url:'/images/uploads/old.jpg'},
  {name:'popular.jpg',url:'/images/uploads/popular.jpg'},
  {name:'new.jpg',url:'/images/uploads/new.jpg'},
]}), 'json'));
assert.deepEqual(r.files.map(x=>x.name), ['new.jpg','popular.jpg']);
let post = {type:'POST',url:'/civicrm/mosaico/upload'};
handler(post);
assert.equal(post.dataFilter, undefined);
console.log('editor listing filtering test: PASS');
