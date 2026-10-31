(function () {
  'use strict';
  function init() {
    var root = document.getElementById('mosaicogallery-app');
    if (!root) { return; }
    var api = root.getAttribute('data-api');
    var token = root.getAttribute('data-token');
    var state = { page: 1, filter: 'active', group: 'all', sort: 'recent', search: '', busy: false, groups: [], selected: new Set(), visibleNames: [], bulkGroup: '__choose__', ready: false };
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
      var clear = document.getElementById('mg-clear-selection');
      var selectedHere = state.visibleNames.filter(function (name) { return state.selected.has(name); }).length;
      if (counter) { counter.textContent = selected + ' photo' + (selected === 1 ? '' : 's') + ' selected'; }
      if (apply) { apply.disabled = !selected || state.busy || !state.ready || state.bulkGroup === '__choose__'; }
      if (clear) { clear.disabled = !selected || state.busy; }
      if (allBox) {
        allBox.checked = !!state.visibleNames.length && selectedHere === state.visibleNames.length;
        allBox.indeterminate = selectedHere > 0 && selectedHere < state.visibleNames.length;
        allBox.disabled = !state.visibleNames.length || state.busy;
      }
    }

    function renderGroups() {
      var all = [{ value: 'all', label: 'All groups' }, { value: 'ungrouped', label: 'Ungrouped' }];
      var assignable = [
        { value: '__choose__', label: 'Choose group…' },
        { value: '', label: 'Ungrouped' }
      ];
      state.groups.forEach(function (group) {
        all.push({ value: String(group.id), label: group.title });
        assignable.push({ value: String(group.id), label: group.title });
      });
      groupSelect(document.getElementById('mg-group-filter'), all, state.group);
      if (state.bulkGroup !== '' && state.bulkGroup !== '__choose__' &&
          !state.groups.some(function (g) { return String(g.id) === state.bulkGroup; })) {
        state.bulkGroup = '__choose__';
      }
      groupSelect(document.getElementById('mg-bulk-group'), assignable, state.bulkGroup);
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
        item.appendChild(node('span', 'mg-group-image-count', '(' + (group.count || 0) + ' images)'));
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
      if (state.busy || !names.length || groupId === '__choose__') { return; }
      state.busy = true;
      var completed = 0;
      var unique = Array.from(new Set(names));
      try {
        // The API limits each operation to 100 filenames to keep requests safe.
        for (var i = 0; i < unique.length; i += 100) {
          var batch = unique.slice(i, i + 100);
          await request('assign_group', {filenames: JSON.stringify(batch), group_id: groupId}, 'POST');
          batch.forEach(function (name) { state.selected.delete(name); });
          completed += batch.length;
        }
        message(completed + ' photos assigned. Files and existing email links are unchanged.');
      }
      catch (e) {
        message((completed ? completed + ' photos assigned before an error. ' : '') + e.message, true);
      }
      finally { state.busy = false; load(); }
    }

    function setSelected(name, selected) {
      if (selected) { state.selected.add(name); }
      else { state.selected.delete(name); }
      if (document.querySelectorAll) {
        document.querySelectorAll('#mg-grid .mg-card').forEach(function (card) {
          if (card.getAttribute('data-name') !== name) { return; }
          var checked = state.selected.has(name);
          card.classList.toggle('mg-selected', checked);
          var box = card.querySelector('input.mg-select-image');
          if (box) { box.checked = checked; }
          var imageButton = card.querySelector('.mg-picture-select');
          if (imageButton) { imageButton.setAttribute('aria-pressed', checked ? 'true' : 'false'); }
        });
      }
      refreshBulk();
    }

    async function load() {
      if (state.busy) { return; }
      state.busy = true;
      grid.setAttribute('aria-busy', 'true');
      grid.replaceChildren(node('p', 'mg-loading', 'Loading images…'));
      try {
        var data = await request('list', {
          filter: state.filter, group: state.group, sort: state.sort, search: state.search, mg_page: String(state.page)
        });
        state.visibleNames = data.items.map(function (item) { return item.name; });
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
          var card = node('article', 'mg-card' +
            (item.archived ? ' mg-archived' : '') +
            (state.selected.has(item.name) ? ' mg-selected' : ''));
          card.setAttribute('data-name', item.name);
          var picture = node('button', 'mg-picture mg-picture-select');
          picture.type = 'button';
          picture.setAttribute('aria-label', 'Select image ' + item.name);
          picture.setAttribute('aria-pressed', state.selected.has(item.name) ? 'true' : 'false');
          picture.addEventListener('click', function () {
            setSelected(item.name, !state.selected.has(item.name));
          });
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
          info.appendChild(node('div', 'mg-detail', item.usage_count > 0
            ? 'Used in ' + item.usage_count + ' saved record' + (item.usage_count === 1 ? '' : 's')
            : 'No saved references found'));
          info.appendChild(node('div', 'mg-detail', item.date));
          var groupRow = node('div', 'mg-assignment');
          var check = node('input');
          check.type = 'checkbox';
          check.className = 'mg-select-image';
          check.setAttribute('aria-label', 'Select ' + item.name);
          check.setAttribute('data-name', item.name);
          check.checked = state.selected.has(item.name);
          check.addEventListener('change', function () {
            setSelected(item.name, check.checked);
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
        var checked = all.checked;
        state.visibleNames.forEach(function (name) {
          setSelected(name, checked);
        });
        refreshBulk();
      });
    }
    var clear = document.getElementById('mg-clear-selection');
    if (clear) {
      clear.addEventListener('click', function () {
        Array.from(state.selected).forEach(function (name) { setSelected(name, false); });
      });
    }
    var bulkGroup = document.getElementById('mg-bulk-group');
    if (bulkGroup) {
      bulkGroup.addEventListener('change', function () {
        state.bulkGroup = this.value;
        refreshBulk();
      });
    }
    var bulk = document.getElementById('mg-bulk-apply');
    if (bulk) {
      bulk.addEventListener('click', function () {
        moveImages(Array.from(state.selected), state.bulkGroup);
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
    // Usage counts default to zero until they have been indexed. Check once
    // when opening the manager, then perform the first listing only after
    // any required refresh has completed. Cached counts remain immediately
    // available on visits during the next 24 hours.
    async function initializeWithUsageCounts() {
      state.busy = true;
      var usageButton = document.getElementById('mg-usage');
      if (usageButton) { usageButton.disabled = true; }
      grid.setAttribute('aria-busy', 'true');
      grid.replaceChildren(node('p', 'mg-loading', 'Checking image usage statistics…'));
      try {
        var status = await request('usage_status', {}, 'GET');
        if (status.needs_refresh) {
          grid.replaceChildren(node('p', 'mg-loading', 'Counting image references in saved mailings and templates…'));
          message('Updating image usage statistics…');
          await request('usage', {}, 'POST');
          message('');
        }
      } catch (e) {
        // Never prevent image/group management if the reference scan fails.
        // The previous cached counts remain available for inspection.
        message('Unable to refresh usage counts automatically: ' + e.message +
          ' Use Refresh usage counts to retry.', true);
      } finally {
        if (usageButton) { usageButton.disabled = false; }
        state.busy = false;
        grid.removeAttribute('aria-busy');
        load();
      }
    }
    initializeWithUsageCounts();
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
  else { init(); }
})();
