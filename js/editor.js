/* Mosaico Gallery Manager — configuration is inserted server-side. */
(function () {
  'use strict';
  var config = /*__GALLERY_DATA__*/null;
  if (!config || !window.jQuery || !window.jQuery.ajaxPrefilter) { return; }

  var $ = window.jQuery;
  var currentOrder = 'recent';
  var currentGroup = 'all';
  var fullFiles = null;
  var galleryNode = null;
  var galleryVM = null;
  var observer = null;
  var mountQueued = false;
  var groupIds = (config.groups || []).map(function (g) { return String(g.id); });

  try {
    currentOrder = localStorage.getItem('mosaicogallery-order') || 'recent';
    currentGroup = localStorage.getItem('mosaicogallery-group') || 'all';
  } catch (ignore) {}
  if (['recent', 'popular', 'favourites', 'name'].indexOf(currentOrder) < 0) { currentOrder = 'recent'; }
  if (currentGroup !== 'all' && currentGroup !== 'ungrouped' && groupIds.indexOf(currentGroup) === -1) {
    currentGroup = 'all';
  }

  function eligible(item) {
    if (!item || typeof item.name !== 'string') { return false; }
    var state = config.entries[item.name] || {};
    if (state.archived) { return false; }
    var id = Number(state.group_id || 0);
    return currentGroup === 'all' ||
      (currentGroup === 'ungrouped' ? id === 0 : id === Number(currentGroup));
  }

  // Returned order for the live Knockout array: newest/highest usage first.
  function visibleFiles(files) {
    return files.filter(eligible).slice().sort(function (a, b) {
      var am = config.entries[a.name] || {};
      var bm = config.entries[b.name] || {};
      if (currentOrder === 'popular' && bm.usage_count !== am.usage_count) {
        return (Number(bm.usage_count) || 0) - (Number(am.usage_count) || 0);
      }
      if (currentOrder === 'favourites' && !!bm.favourite !== !!am.favourite) {
        return Number(!!bm.favourite) - Number(!!am.favourite);
      }
      if (currentOrder === 'name') { return a.name.localeCompare(b.name); }
      return ((Number(bm.mtime) || 0) - (Number(am.mtime) || 0)) ||
        a.name.localeCompare(b.name);
    });
  }

  // Standard Mosaico gallery downloads the full catalog with jQuery GET and
  // reverses the response before assigning it to galleryRemote. Preserve
  // unfiltered source files so group/order changes can update immediately.
  // Never intercept POST uploads or other unrelated requests.
  $.ajaxPrefilter(function (options) {
    var url = String(options.url || '');
    var method = String(options.type || options.method || 'GET').toUpperCase();
    if (method !== 'GET' || !/mosaico/i.test(url) || !/(upload|file)/i.test(url)) { return; }
    var previous = options.dataFilter;
    options.dataFilter = function (raw, type) {
      if (typeof previous === 'function') { raw = previous.call(this, raw, type); }
      var parsed;
      try { parsed = JSON.parse(raw); } catch (ignore) { return raw; }
      if (!parsed || !Array.isArray(parsed.files)) { return raw; }
      if (parsed.files.length &&
          !parsed.files.some(function (item) { return item && /images\/uploads\//.test(item.url || ''); })) {
        return raw;
      }

      fullFiles = parsed.files.slice();
      // The stock Mosaico loadGallery() calls .reverse() after receiving JSON.
      parsed.files = visibleFiles(fullFiles).reverse();
      return JSON.stringify(parsed);
    };
  });

  // CiviCRM invokes this through hook_civicrm_mosaicoPlugin as Mosaico
  // constructs its editor. It works even when Knockout is not on window.
  window.mosaicogalleryBindViewModel = function (vm) {
    if (!vm || typeof vm.galleryRemote !== 'function') { return; }
    galleryVM = vm;
    scheduleMount();
    if (fullFiles) { applyNow(); }
  };

  function findViewModel() {
    if (!galleryNode || !window.ko || typeof window.ko.contextFor !== 'function') { return null; }
    try {
      var context = window.ko.contextFor(galleryNode);
      var model = context && context.$root;
      if (model && typeof model.galleryRemote === 'function') { return model; }
    } catch (ignore) {}
    return null;
  }

  function applyNow() {
    var vm = galleryVM || findViewModel();
    galleryVM = vm;
    if (!vm) { return; }
    if (!fullFiles) {
      // The gallery has not been loaded yet. The AJAX prefilter will apply
      // the active selection on its first load.
      return;
    }
    if (typeof vm.remoteFileProcessor !== 'function') { return; }
    // Clone before Mosaico decorates URLs, preserving original sources.
    var selected = visibleFiles(fullFiles).map(function (file) {
      return vm.remoteFileProcessor(Object.assign({}, file));
    });
    vm.galleryRemote(selected);
    if (typeof vm.galleryLoaded === 'function') { vm.galleryLoaded(selected.length); }
    if (typeof vm.galleryRemote.moveFirst === 'function') { vm.galleryRemote.moveFirst(); }
  }

  function makeSelect(labelText, value, values, changed, manageUrl) {
    // Compact controls: a single-line caption (with optional manage link)
    // immediately above a native-size select. No additional heading/footer.
    var wrapper = document.createElement('div');
    wrapper.style.cssText = 'display:flex;flex-direction:column;gap:1px;flex:1 1 0;min-width:0;text-align:left;';
    var caption = document.createElement('div');
    caption.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:4px;min-height:12px;line-height:12px;';
    var label = document.createElement('label');
    var id = labelText === 'Group' ? 'mg-editor-group' : 'mg-editor-sort';
    label.setAttribute('for', id);
    label.textContent = labelText;
    label.style.cssText = 'font:11px/12px Arial,sans-serif;';
    caption.appendChild(label);

    if (manageUrl) {
      var link = document.createElement('a');
      link.href = manageUrl;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.textContent = 'Manage';
      link.style.cssText = 'font:10px/12px Arial,sans-serif;white-space:nowrap;';
      link.setAttribute('aria-label', 'Manage gallery images');
      caption.appendChild(link);
    }
    wrapper.appendChild(caption);

    var select = document.createElement('select');
    select.id = id;
    select.style.cssText = 'width:100%;height:27px;min-height:27px;min-width:0;max-width:100%;box-sizing:border-box;margin:0;padding:2px 4px;border:1px solid #a9b8c3;border-radius:4px;background:#fff;color:#263747;font:12px Arial,sans-serif;';
    select.setAttribute('aria-label', labelText);
    values.forEach(function (entry) {
      var option = document.createElement('option');
      option.value = entry[0];
      option.textContent = entry[1];
      select.appendChild(option);
    });
    select.value = value;
    select.addEventListener('change', function () { changed(select.value); });
    wrapper.appendChild(select);
    return wrapper;
  }

  function mountGalleryControls() {
    var target = document.getElementById('toolimagesgallery');
    if (!target) { return; }

    if (target !== galleryNode) {
      galleryNode = target;
      galleryVM = null;
    }
    galleryVM = galleryVM || findViewModel();

    // Mosaico rebuilds this panel when it opens/closes: remount once per
    // panel instance, not at a fixed position on the desktop.
    if (target.querySelector('#mg-gallery-filters')) { return; }

    var bar = document.createElement('div');
    bar.id = 'mg-gallery-filters';
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', 'Filter image gallery');
    bar.style.cssText = 'position:relative;display:block;text-align:left;box-sizing:border-box;padding:5px 6px;margin:3px 0 5px;background:#f5f8fa;border:1px solid #d6e0e7;border-radius:6px;color:#273a4b;';

    var row = document.createElement('div');
    row.style.cssText = 'display:flex;flex-wrap:nowrap;gap:6px;align-items:end;';
    row.appendChild(makeSelect('Group', currentGroup, [
      ['all', 'All groups'], ['ungrouped', 'Ungrouped']
    ].concat((config.groups || []).map(function (group) {
      return [String(group.id), group.title];
    })), function (value) {
      currentGroup = value;
      try { localStorage.setItem('mosaicogallery-group', value); } catch (ignore) {}
      applyNow();
    }));
    row.appendChild(makeSelect('Sort by', currentOrder, [
      ['recent', 'Newest'], ['popular', 'Most used'],
      ['favourites', 'Favourites first'], ['name', 'Name']
    ], function (value) {
      currentOrder = value;
      try { localStorage.setItem('mosaicogallery-order', value); } catch (ignore) {}
      applyNow();
    }, config.canManage ? config.managerUrl : null));
    bar.appendChild(row);
    target.insertBefore(bar, target.firstChild);
    // For a gallery that was loaded while this UI was mounting, apply the
    // current selection without another request.
    applyNow();
  }

  function scheduleMount() {
    if (mountQueued) { return; }
    mountQueued = true;
    // Avoid repeated DOM work on Knockout's rapid gallery/pager updates.
    var defer = window.requestAnimationFrame || function (callback) { setTimeout(callback, 0); };
    defer(function () {
      mountQueued = false;
      mountGalleryControls();
    });
  }

  function init() {
    scheduleMount();
    if (typeof MutationObserver === 'function' && document.body) {
      observer = new MutationObserver(function () {
        // Respond when Mosaico creates/replaces the gallery panel.
        scheduleMount();
      });
      observer.observe(document.body, {childList: true, subtree: true});
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
