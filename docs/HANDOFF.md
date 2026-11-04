# Handoff

Updated 2026-11-04. Overwritten each session, not appended.

## Working on
Taking every design screen that fits existing models and controllers (no new models, columns only) and building it exactly as designed, one screen per commit. Design files are in `Foundry ERP workflow design/` (repo root, newest). Screen inventory with titles: session scratchpad `inventory.txt` (regenerate by listing `dv-opt` ids per area file).

## Done this session
- Invoices list to 24a: `resources/views/components/invoice-list.blade.php` (Livewire), tabs, search, customer and FY chips, side panel — 8e299617
- Invoice page to 24b (draft: printed page + Checks + posting preview) and 24c (posted record) — 9edd93e2
- Receive payment to 24d where data allows — 8706ec89
- STATUS.md entry listing what was left out (no mail: Sent/To send, reminder, Post and send; FBR/QR; lot/heat trace; Into bank, withheld rows, remittance advice on receipts; list Export)
- Tests: InvoiceListTest, InvoiceRecordTest (new); InvoicingTest, AdvancesTest, VoucherEntryTest still pass. No browser check done.

## Next
1. Speed: user wants localhost responses under 100 ms. Not measured yet (the curl check was declined). Start with `curl -w %{time_total} http://erp/login`, then check opcache, APP_DEBUG, Telescope (dev-only, adds overhead), and N+1 on the new invoice list (`waitingDispatches` loads every dispatched note; the list loads all invoices for the FY then pages in memory).
2. Next screens that look like they fit (fit-check each first): 8b credit note, 8c cash application and statement, 7b purchase order, 7d supplier bills, 8e approved suppliers, 19c-19e ledger, 19i-19m, 14a-14c enquiries, 16a/16b lots, 7g, 7h, 10c/10d, 16g/16h, 3d, 3h, 3i, 3j, 1i, 1l, 7e.
3. Look at the new invoice pages in a browser.

## Open questions
- `app/Models/Equipment.php` is modified and uncommitted from before this session; leave it.
- `RECOVERY-CODES-Rashid Shafique.txt` sits untracked in the repo root; never stage it. Stage by explicit path only.
- Design folders are untracked; not committed.

## Files in play
- resources/views/components/invoice-list.blade.php
- app/Domains/Finance/ (Http/Controllers/InvoiceController.php, resources/views/invoices/show.blade.php and index.blade.php, resources/views/receipts/create.blade.php)
- tests/Feature/InvoiceListTest.php, InvoiceRecordTest.php
