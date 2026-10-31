(function () {
  'use strict';
  function init() {
    var root = document.getElementById('mosaicogallery-app');
    if (!root) { return; }
    var api = root.getAttribute('data-api');
    var token = root.getAttribute('data-token');
    var state = { page: 1, filter: 'active', sort: 'recent', search: '', busy: false };
    var grid = document.getElementById('mg-grid');
    var pages = document.getElementById('mg-pagination');
    var summary = document.getElementById('mg-summary');
    var status = document.getElementById('mg-status');
    var searchTimer;

    function message(text, error) {
      status.textContent = text || '';
      status.className = 'mg-status' + (error ? ' mg-error' : '');
    }
    function node(tag, className, text) {
      var el = document.createElement(tag);
      if (className) { el.className = className; }
      if (text !== undefined) { el.textContent = text; }
      return el;
    }
    function button(text, onClick, className) {
      var el = node('button', className || 'mg-button', text);
      el.type = 'button';
      el.addEventListener('click', onClick);
      return el;
    }
    async function request(action, data, method) {
      var body;
      var url = new URL(api, location.href);
      url.searchParams.set('action', action);
      if (method === 'POST') {
        body = new URLSearchParams(Object.assign({ token: token }, data || {}));
      } else {
        Object.entries(data || {}).forEach(function (pair) { url.searchParams.set(pair[0], pair[1]); });
      }
      var response = await fetch(url.toString(), {
        method: method || 'GET',
        credentials: 'same-origin',
        headers: method === 'POST' ? {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'} : {},
        body: body
      });
      var json;
      try { json = await response.json(); } catch (ignore) { throw new Error('Server did not return valid JSON. Check CiviCRM logs.'); }
      if (!response.ok || !json.ok) { throw new Error(json.error || 'Request failed.'); }
      return json.data;
    }
    async function load() {
      if (state.busy) { return; }
      state.busy = true;
      grid.setAttribute('aria-busy', 'true');
      grid.replaceChildren(node('p', 'mg-loading', 'Loading images…'));
      try {
        var data = await request('list', {
          filter: state.filter, sort: state.sort, search: state.search, page: String(state.page)
        });
        summary.replaceChildren();
        [['Total images', data.all], ['Active', data.active], ['Archived', data.archived], ['Matching', data.total]].forEach(function (pair) {
          var tile = node('div', 'mg-stat');
          tile.appendChild(node('strong', '', String(pair[1])));
          tile.appendChild(node('span', '', pair[0]));
          summary.appendChild(tile);
        });
        grid.replaceChildren();
        if (!data.items.length) { grid.appendChild(node('p', 'mg-empty', 'No images match these filters.')); }
        data.items.forEach(function (item) {
          var card = node('article', 'mg-card' + (item.archived ? ' mg-archived' : ''));
          var picture = node('div', 'mg-picture');
          var img = node('img');
          img.src = item.url;
          img.alt = item.name;
          img.loading = 'lazy';
          picture.appendChild(img);
          card.appendChild(picture);
          var info = node('div', 'mg-info');
          var filename = node('div', 'mg-name', item.name);
          filename.title = item.name;
          info.appendChild(filename);
          info.appendChild(node('div', 'mg-detail', 'Used in ' + item.usage_count + ' saved records'));
          info.appendChild(node('div', 'mg-detail', item.date));
          var actions = node('div', 'mg-actions');
          var fav = button(item.favourite ? '★ Favourite' : '☆ Favourite', function () {
            change(item, 'favourite', !item.favourite);
          }, 'mg-button mg-star');
          fav.setAttribute('aria-pressed', String(item.favourite));
          actions.appendChild(fav);
          var archive = button(item.archived ? 'Restore' : 'Archive', function () {
            change(item, 'archived', !item.archived);
          }, 'mg-button' + (item.archived ? ' mg-restore' : ''));
          actions.appendChild(archive);
          info.appendChild(actions);
          card.appendChild(info);
          grid.appendChild(card);
        });
        pages.replaceChildren();
        var prev = button('← Previous', function () { state.page--; load(); });
        prev.disabled = data.page <= 1;
        var next = button('Next →', function () { state.page++; load(); });
        next.disabled = data.page >= data.pages;
        pages.appendChild(prev);
        pages.appendChild(node('span', '', 'Page ' + data.page + ' of ' + data.pages));
        pages.appendChild(next);
      } catch (e) {
        grid.replaceChildren();
        message(e.message, true);
      } finally {
        state.busy = false;
        grid.removeAttribute('aria-busy');
      }
    }
    async function change(item, flag, enabled) {
      if (state.busy) { return; }
      state.busy = true;
      try {
        await request('setflag', {filename: item.name, flag: flag, enabled: enabled ? '1' : '0'}, 'POST');
        message((flag === 'archived' ? (enabled ? 'Archived ' : 'Restored ') : 'Updated favourite: ') + item.name + '. No files were moved or deleted.');
      } catch (e) { message(e.message, true); }
      finally { state.busy = false; load(); }
    }
    async function runAction(action, success) {
      if (state.busy) { return; }
      state.busy = true;
      message(action === 'usage' ? 'Counting references in saved templates and mailings…' : 'Scanning upload directory…');
      try {
        var result = await request(action, {}, 'POST');
        message(success(result));
      } catch (e) { message(e.message, true); }
      finally { state.busy = false; load(); }
    }
    document.getElementById('mg-filter').addEventListener('change', function () { state.filter = this.value; state.page = 1; load(); });
    document.getElementById('mg-sort').addEventListener('change', function () { state.sort = this.value; state.page = 1; load(); });
    document.getElementById('mg-search').addEventListener('input', function () {
      var text = this.value;
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () { state.search = text.trim(); state.page = 1; load(); }, 250);
    });
    document.getElementById('mg-sync').addEventListener('click', function () {
      runAction('sync', function (result) { return 'Scanned ' + result.total + ' images; found ' + result.added + ' new images.'; });
    });
    document.getElementById('mg-usage').addEventListener('click', function () {
      runAction('usage', function (result) { return 'Counted ' + result.sources + ' stored mailings/templates across ' + result.images + ' images.'; });
    });
    load();
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
  else { init(); }
})();
