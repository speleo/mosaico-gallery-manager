/* Mosaico Gallery Manager — dynamic data is inserted server-side. */
(function () {
  'use strict';
  var config = /*__GALLERY_DATA__*/null;
  if (!config || !window.jQuery || !window.jQuery.ajaxPrefilter) { return; }
  var $ = window.jQuery;
  var key = 'mosaicogallery-order';
  var current = 'recent';
  try { current = localStorage.getItem(key) || 'recent'; } catch (ignore) {}

  // Mosaico uses jQuery File Upload to request its image catalogue. We do
  // not intercept uploads, image rendering, or any HTML for existing mailings.
  $.ajaxPrefilter(function (options) {
    var url = String(options.url || '');
    var method = String(options.type || options.method || 'GET').toUpperCase();
    if (method !== 'GET' || !/mosaico/i.test(url) || !/(upload|file)/i.test(url)) { return; }
    var oldFilter = options.dataFilter;
    options.dataFilter = function (raw, dataType) {
      if (typeof oldFilter === 'function') { raw = oldFilter.call(this, raw, dataType); }
      var data;
      try { data = JSON.parse(raw); } catch (ignore) { return raw; }
      if (!data || !Array.isArray(data.files)) { return raw; }
      // Guard against affecting unrelated responses.
      if (data.files.length && !data.files.some(function (f) { return f && /images\/uploads\//.test(f.url || ''); })) { return raw; }
      data.files = data.files.filter(function (item) {
        var state = config.entries[item.name];
        return !state || !state.archived;
      });
      data.files.sort(function (a, b) {
        var am = config.entries[a.name] || {}, bm = config.entries[b.name] || {};
        var au = am.usage_count || 0, bu = bm.usage_count || 0;
        var ad = am.mtime || 0, bd = bm.mtime || 0;
        if (current === 'popular' && bu !== au) { return bu - au; }
        if (current === 'favourites' && !!bm.favourite !== !!am.favourite) { return Number(!!bm.favourite) - Number(!!am.favourite); }
        if (current === 'name') { return String(a.name).localeCompare(String(b.name)); }
        return (bd - ad) || String(a.name).localeCompare(String(b.name));
      });
      return JSON.stringify(data);
    };
  });

  function addToolbar() {
    if (document.getElementById('mg-editor-toolbar')) { return; }
    var bar = document.createElement('div');
    bar.id = 'mg-editor-toolbar';
    bar.style.cssText = 'position:fixed;z-index:900;right:16px;bottom:16px;background:#fff;color:#333;box-shadow:0 2px 12px #0003;border:1px solid #ccc;border-radius:8px;padding:9px 12px;font:13px Arial,sans-serif;';
    var label = document.createElement('label');
    label.textContent = 'Gallery order: ';
    var select = document.createElement('select');
    select.setAttribute('aria-label', 'Gallery ordering');
    [['recent','Newest'],['popular','Most used'],['favourites','Favourites'],['name','Name']].forEach(function (entry) {
      var option = document.createElement('option');
      option.value = entry[0]; option.textContent = entry[1]; select.appendChild(option);
    });
    select.value = current;
    select.addEventListener('change', function () {
      current = select.value;
      try { localStorage.setItem(key, current); } catch (ignore) {}
      hint.textContent = 'Reopen gallery to apply';
    });
    label.appendChild(select);
    bar.appendChild(label);
    var hint = document.createElement('small');
    hint.style.cssText = 'display:block;color:#667; margin-top:4px;';
    hint.textContent = 'Archived images are hidden';
    bar.appendChild(hint);
    if (config.canManage) {
      var a = document.createElement('a');
      a.href = config.managerUrl; a.target = '_blank'; a.rel = 'noopener noreferrer';
      a.textContent = 'Manage gallery'; a.style.marginLeft = '10px';
      bar.appendChild(a);
    }
    document.body.appendChild(bar);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addToolbar);
  } else { addToolbar(); }
})();
