# Manual smoke tests (requires CiviCRM + Mosaico)

1. Install on a staging WordPress / CiviCRM instance; ensure page and API permissions work.
2. Confirm directory and URL resolve via CRM_Mosaico_Utils::getConfig.
3. Index directory; confirm card list shows actual files and no thumbnail directories.
4. Archive an old, referenced image; confirm its original HTTP URL remains HTTP 200.
5. Open a historic mailing; confirm image still appears.
6. Create a mailing; check archived image is hidden but new uploads POST and GET still work.
7. Flip sort in editor control and reopen gallery; confirm list order changes.
8. Rebuild counts; manually compare three examples with saved mailing HTML and Mosaico templates.
9. Browse several pages; search; show archived; restore; and favourite.
10. Disable extension; confirm native gallery returns unchanged.
11. Test with different WordPress CiviCRM base pages and another editor account.
