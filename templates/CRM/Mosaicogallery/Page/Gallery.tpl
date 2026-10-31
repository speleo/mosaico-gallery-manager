<div id="mosaicogallery-app" data-api="{$galleryApiUrl|escape:'html'}" data-token="{$galleryCsrfToken|escape:'html'}">
  <div class="mg-header">
    <div>
      <h2>Mosaico Gallery Manager</h2>
      <p>Choose which images appear in new mailings. Archiving hides images from the editor but <strong>never deletes files or changes existing email URLs</strong>.</p>
    </div>
    <div class="mg-toolbar">
      <button type="button" id="mg-sync" class="button">Rescan image directory</button>
      <button type="button" id="mg-usage" class="button">Refresh usage counts</button>
    </div>
  </div>
  <p id="mg-status" class="mg-status" role="status" aria-live="polite"></p>
  <div class="mg-summary" id="mg-summary"></div>
  <div class="mg-controls">
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
  <div id="mg-grid" class="mg-grid" aria-live="polite"></div>
  <nav id="mg-pagination" class="mg-pagination" aria-label="Gallery pages"></nav>
  <p class="mg-note">Usage = number of distinct saved CiviMail messages and Mosaico templates referencing an image; not email recipients, opens or downloads. Newest is based on the file modification date. Missing files are not automatically deleted from the index.</p>
</div>
