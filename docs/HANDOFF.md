# Handoff

Updated 2026-11-03. Overwritten each session, not appended.

## Working on
Bringing the Sales module and Settings pages to the design. Newest design is `Foundry ERP workflow design.zip` (Nov 3, split by area: Sales, Settings, ...). The unzipped copy used so far is in the session scratchpad only; the folders under docs/design/ are the older single-file export.

## Done this session
- Sales order page to designs 1e/6a (header bar, facts row, lines card, "only what needs you" review cards, sign-off side panel); list and record share `orders/_record-styles.blade.php` — b78400ac
- Approve & release (6a): `SalesOrderStatus::approveAndRelease`, route `sales-orders.approve-release`; signs the user's side, releases when approved, keeps a first sign-off when a second person must sign — 5ec3fd17
- Appearance page to 22c (Screen, Brand, Formats cards, live Preview, one Save); old font/formats/branding addresses still work — af406841

## Next
1. Go through the rest of `Foundry ERP Sales.dc.html` in the zip (16e, 16f, 18i-18l, 13x, 14x) and compare with the app.
2. Order form (create/edit), trace page and price lists have no design yet; do not invent.
3. Check 9c order acknowledgement print against the app.
4. Look at the pages in a browser; none of this session's pages were checked by eye.

## Open questions
- `DisplayFormatsTest::test_typed_numbers_keep_the_sitewide_decimal_places_and_decimal_boxes_take_any_value` fails: `step="0.x"` in pours/create, invoices/_charges, banks/reconcile, banks/show, journals/create (not touched this session; not checked whether it failed before).
- `app/Domains/Enquiry/resources/views/rates.blade.php` was modified before this session and is uncommitted; leave it unless asked.

## Files in play
- app/Domains/Sales/ (Services/SalesOrderStatus.php, Http/Controllers/SalesOrderController.php, resources/views/orders/)
- resources/views/settings/display.blade.php, app/Http/Controllers/DisplaySettingsController.php, BrandingController.php
- docs/STATUS.md
