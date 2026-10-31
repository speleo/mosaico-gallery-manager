<div id="mosaicogallery-app" data-api="{$galleryApiUrl|escape:'html'}" data-token="{$galleryCsrfToken|escape:'html'}">
  <div class="mg-header">
    <div>
      <p>Choose which images appear in new mailings. Archiving hides images from the editor but <strong>never deletes files or changes existing email URLs</strong>.</p>
    </div>
    <div class="mg-toolbar">
      <button type="button" id="mg-sync" class="button">Rescan image directory</button>
      <button type="button" id="mg-usage" class="button">Refresh usage counts</button>
    </div>
  </div>
  <p id="mg-status" class="mg-status" role="status" aria-live="polite"></p>
  <div class="mg-summary" id="mg-summary"></div>

  <section class="mg-groups" aria-labelledby="mg-groups-title">
    <div class="mg-groups-heading">
      <h3 id="mg-groups-title">Image groups</h3>
      <span id="mg-group-count">0 of 10 groups</span>
    </div>
    <p>Create up to 10 groups with your own headings. Renaming or removing a group never changes or deletes image files.</p>
    <div class="mg-group-create">
      <label for="mg-new-group">New group heading</label>
      <input type="text" id="mg-new-group" maxlength="80" placeholder="e.g. Club logos, Events, Caving photos">
      <button id="mg-create-group" type="button" class="mg-button">Add group</button>
    </div>
    <div id="mg-group-list" class="mg-group-list"></div>
  </section>
  <div class="mg-controls">
    <label>Group <select id="mg-group-filter">
      <option value="all">All groups</option>
      <option value="ungrouped">Ungrouped</option>
    </select></label>
    <label>Show <select id="mg-filter">
      <option value="active">Active</option>
      <option value="archived">Archived</option>
      <option value="favourites">Favourites</option>
      <option value="all">All</option>
    </select></label>
    <label>Order by <select id="mg-sort">
      <option value="recent">Newest file first</option>
      <option value="popular">Most used</option>
      <option value="favourites">Favourites first</option>
      <option value="name">Filename (A–Z)</option>
    </select></label>
    <label class="mg-search-label">Search filename <input type="search" id="mg-search" placeholder="e.g. AGM, header, logo"></label>
  </div>

  <div class="mg-bulk" role="group" aria-label="Select and group images">
    <div class="mg-bulk-top">
      <strong>Bulk image grouping</strong>
      <span id="mg-bulk-count" aria-live="polite">0 photos selected</span>
    </div>
    <div class="mg-bulk-controls">
      <label class="mg-bulk-select-all"><input type="checkbox" id="mg-select-all"> Select all on this page</label>
      <button type="button" id="mg-clear-selection" class="mg-button">Clear selection</button>
      <label for="mg-bulk-group">Assign selected photos to</label>
      <select id="mg-bulk-group">
        <option value="__choose__">Choose group…</option>
        <option value="">Ungrouped</option>
      </select>
      <button type="button" id="mg-bulk-apply" class="mg-button mg-button-primary" disabled>Assign selected</button>
    </div>
    <p class="mg-bulk-hint">Click thumbnails or tick their boxes to select several photos. Selection is retained when changing pages or filters.</p>
  </div>
  <div id="mg-grid" class="mg-grid" aria-live="polite"></div>
  <nav id="mg-pagination" class="mg-pagination" aria-label="Gallery pages"></nav>
  <p class="mg-note">Each image belongs to at most one group. Images without a group remain in Ungrouped. Deleting a group only removes its assignments. Archived images remain hidden from the editor regardless of group.</p>
  <p class="mg-note">Usage = number of distinct saved CiviMail messages and Mosaico templates referencing an image; not email recipients, opens or downloads. Newest is based on the file modification date. Missing files are not automatically deleted from the index.</p>
</div>
