(function () {
  'use strict';
  function init() {
    var root = document.getElementById('mosaicogallery-app');
    if (!root) { return; }
    var api = root.getAttribute('data-api');
    var token = root.getAttribute('data-token');
    var state = { page: 1, filter: 'active', group: 'all', sort: 'recent', search: '', busy: false, groups: [], selected: new Set(), ready: false };
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
      // Reuse the precise URL that successfully loaded the manager page.
      // WordPress installations can reject separately generated admin routes
      // with HTTP 403 (for example admin.php?page=1).
      var url = new URL(location.href);
      url.searchParams.set('mg_ajax', '1');
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
      // Read once and report diagnostics without including response HTML,
      // which may contain private CiviCRM information.
      var raw = await response.text();
      var json;
      try {
        json = JSON.parse(raw);
      } catch (ignore) {
        var kind = /^\s*</.test(raw) ? 'HTML' : 'non-JSON content';
        var type = response.headers.get('content-type') || 'unknown content type';
        var redirected = response.redirected ? ', redirected' : '';
        throw new Error('Gallery API returned ' + kind + ' instead of JSON (HTTP ' +
          response.status + ', ' + type + redirected + '). Check the API URL and CiviCRM logs.');
      }
      if (!response.ok || !json || !json.ok) { throw new Error((json && json.error) || 'Gallery API request failed (HTTP ' + response.status + ').'); }
      return json.data;
    }

    function groupSelect(select, options, desiredValue) {
      if (!select) { return; }
      select.replaceChildren();
      options.forEach(function (option) {
        var item = node('option', '', option.label);
        item.value = option.value;
        select.appendChild(item);
      });
      select.value = desiredValue;
    }

    function refreshBulk() {
      var selected = state.selected.size;
      var counter = document.getElementById('mg-bulk-count');
      var apply = document.getElementById('mg-bulk-apply');
      var allBox = document.getElementById('mg-select-all');
      if (counter) { counter.textContent = selected + ' selected'; }
      if (apply) { apply.disabled = !selected || state.busy || !state.ready; }
      if (allBox) {
        var choices = document.querySelectorAll ? document.querySelectorAll('#mg-grid input.mg-select-image') : [];
        allBox.checked = !!choices.length && selected === choices.length;
      }
    }

    function renderGroups() {
      var all = [{ value: 'all', label: 'All groups' }, { value: 'ungrouped', label: 'Ungrouped' }];
      var assignable = [{ value: '', label: 'Ungrouped' }];
      state.groups.forEach(function (group) {
        all.push({ value: String(group.id), label: group.title });
        assignable.push({ value: String(group.id), label: group.title });
      });
      groupSelect(document.getElementById('mg-group-filter'), all, state.group);
      groupSelect(document.getElementById('mg-bulk-group'), assignable, '');
      var count = document.getElementById('mg-group-count');
      if (count) { count.textContent = state.groups.length + ' of 10 groups'; }
      var buttonCreate = document.getElementById('mg-create-group');
      var inputCreate = document.getElementById('mg-new-group');
      if (buttonCreate) { buttonCreate.disabled = state.groups.length >= 10 || !state.ready; }
      if (inputCreate) { inputCreate.disabled = state.groups.length >= 10 || !state.ready; }
      var holder = document.getElementById('mg-group-list');
      if (!holder) { return; }
      holder.replaceChildren();
      state.groups.forEach(function (group) {
        var item = node('div', 'mg-group-item');
        item.appendChild(node('strong', '', group.title));
        item.appendChild(button('Rename', function () {
          var title = window.prompt('New heading for this group:', group.title);
          if (title !== null && title.trim()) {
            mutateGroup('rename_group', {group_id: String(group.id), title: title.trim()}, 'Group renamed.');
          }
        }));
        item.appendChild(button('Remove', function () {
          if (window.confirm('Remove the group "' + group.title + '"? Images will become Ungrouped, but none will be deleted.')) {
            if (state.group === String(group.id)) { state.group = 'all'; }
            mutateGroup('delete_group', {group_id: String(group.id)}, 'Group removed. Images were not deleted.');
          }
        }));
        holder.appendChild(item);
      });
      if (!state.ready) {
        holder.appendChild(node('p', 'mg-error', 'Group database upgrade pending. Run cv ext:upgrade-db or apply pending CiviCRM extension upgrades.'));
      }
    }

    async function mutateGroup(action, payload, success) {
      if (state.busy) { return; }
      state.busy = true;
      try {
        await request(action, payload, 'POST');
        message(success);
      }
      catch (e) { message(e.message, true); }
      finally { state.busy = false; load(); }
    }

    async function moveImages(names, groupId) {
      if (state.busy || !names.length) { return; }
      state.busy = true;
      try {
        await request('assign_group', {filenames: JSON.stringify(names), group_id: groupId}, 'POST');
        state.selected.clear();
        message(names.length + ' image(s) assigned. Image files and URLs were not changed.');
      }
      catch (e) { message(e.message, true); }
      finally { state.busy = false; load(); }
    }

    async function load() {
      if (state.busy) { return; }
      state.busy = true;
      grid.setAttribute('aria-busy', 'true');
      state.selected.clear();
      grid.replaceChildren(node('p', 'mg-loading', 'Loading images…'));
      try {
        var data = await request('list', {
          filter: state.filter, group: state.group, sort: state.sort, search: state.search, mg_page: String(state.page)
        });
        state.groups = data.groups || [];
        state.ready = !!data.group_schema_ready;
        renderGroups();
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
          var groupRow = node('div', 'mg-assignment');
          var check = node('input');
          check.type = 'checkbox';
          check.className = 'mg-select-image';
          check.setAttribute('aria-label', 'Select ' + item.name);
          check.setAttribute('data-name', item.name);
          check.addEventListener('change', function () {
            if (check.checked) { state.selected.add(item.name); }
            else { state.selected.delete(item.name); }
            refreshBulk();
          });
          groupRow.appendChild(check);
          var select = node('select');
          select.setAttribute('aria-label', 'Group for ' + item.name);
          var choices = [{value: '', label: 'Ungrouped'}];
          state.groups.forEach(function (group) { choices.push({value: String(group.id), label: group.title}); });
          groupSelect(select, choices, item.group_id ? String(item.group_id) : '');
          select.disabled = !state.ready;
          select.addEventListener('change', function () { moveImages([item.name], select.value); });
          groupRow.appendChild(select);
          info.appendChild(groupRow);
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
        refreshBulk();
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
        refreshBulk();
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
    var groupFilter = document.getElementById('mg-group-filter');
    if (groupFilter) { groupFilter.addEventListener('change', function () { state.group = this.value; state.page = 1; load(); }); }
    var create = document.getElementById('mg-create-group');
    if (create) {
      create.addEventListener('click', function () {
        var titleInput = document.getElementById('mg-new-group');
        var title = titleInput.value.trim();
        if (!title) { message('Enter a group heading first.', true); return; }
        mutateGroup('create_group', {title: title}, 'Group created.');
        titleInput.value = '';
      });
    }
    var all = document.getElementById('mg-select-all');
    if (all) {
      all.addEventListener('change', function () {
        document.querySelectorAll('#mg-grid input.mg-select-image').forEach(function (checkbox) {
          checkbox.checked = all.checked;
          if (checkbox.checked) { state.selected.add(checkbox.getAttribute('data-name')); }
          else { state.selected.delete(checkbox.getAttribute('data-name')); }
        });
        refreshBulk();
      });
    }
    var bulk = document.getElementById('mg-bulk-apply');
    if (bulk) {
      bulk.addEventListener('click', function () {
        moveImages(Array.from(state.selected), document.getElementById('mg-bulk-group').value);
      });
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
