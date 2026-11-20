# Design changelog

Newest entry at the top. Each entry lists what is new or changed, by screen ID, so only those screens need work.

## 2026-11-18 · invoice screens moved to Sales

- Turns 24 (invoice flow) and 31 (raise invoice, debit note, advance receipt, customer tax/currency, decisions) and the invoicing half of turn 8 (8g, 8a, 8b, 8c) moved from the Accounts file to the Sales file: 13 screens. Accounts 43 screens, Sales 44.
- Turn 8 on Accounts is now "Purchasing: requisitions, suppliers and returns" (8d, 7b, 8e, 8f); the moved invoicing screens sit in their own turn 8 on Sales.
- Accounts keeps day book, ledger, journals, purchasing, payables, assets and the chart. Links, nav chips, index cards and counts updated; `ivVals()` moved to the Sales page script. Script: `scripts/move_invoices.py`.

## 2026-11-18 · invoices raised on their own, and the invoice model settled (T31)
- New: 31a raise an invoice with nothing behind it — scrap, a machining job on the customer's own castings, an asset sale. You pick the income account and the sales tax follows the customer, not the part. Second frame: billing tooling or an advance off an order before anything ships, as a proforma or a tax invoice. Creating an invoice used to start at a dispatch note.
- New: 31c debit note, the mirror the credit note never had — undercharge, missed charge, escalation recovery, or a customer deduction we do not accept. Its own series, its own posting, the original invoice untouched.
- New: 31d advance receipt — money taken when there is no invoice to allocate it against. Held as a liability in 22-07-02, carries no output tax, shown as a deduction on the first real invoice. 24d allocates across open invoices and had nowhere to start.
- New: 31e the customer carries the country, the currency and the tax treatment; quotation, order, invoice and receipt all read them. Two local customers in PKR with GST and withholding, six export customers in USD, zero-rated against a Form E.
- New: 31f the decisions, with what each screen used to say beside them.

### Structural fixes to the existing invoice screens
- INV-26-1192 was two different invoices: USD and exempt on 8a and 8g, PKR with 18% GST on 24b and 24c, with different second lines, a different tooling model and different freight. 8a, 8g and the printed template 9b are now INV-26-1185, the export invoice to Meridian; 24b, 24c and 24d keep INV-26-1192 as the local invoice.
- The rupee GST invoice belonged to a customer in Detroit. It is now Ravi Autos (Pvt) Ltd of Lahore, with its contacts, PO reference and remittance advice to match.
- The company itself was a US corporation on the printed invoice (Redfern Castings Inc., Erie PA) and a Pakistani one everywhere else. 9b now carries the Lahore letterhead and the NTN, and reads zero-rated rather than exempt.
- One receivable account: 22-02-01 Trade receivables, which is what the chart of accounts actually has (19a, 19d, 19f). The 24 set had invented 21-04-01 Trade debtors.
- One invoice series, INV-; 19o said SI-26-0418. One receipt series, REC-; 8c said RC-26-0612.
- 19o charges 18% GST, so its customer is now local (Hilal Pumps). Its label says the charge and deduction boxes are for trade discount and anything that is not a supply, while freight, packing, surcharge and amortisation are lines, as on 24b.
- 8a, 8c, 3l and 4c are labelled as earlier explorations against the live path: 8g creates, 24b reviews and posts, 24c is the record, 24d takes payment, 8b credits, 31c debits.
- Customer names settled: Nordline, Orbis and Stellar, not Nordline Fluid Tech, Orbis Auto or Stellar Hydraulics. A freight line on 8a named a customer as the carrier.
- 13a shows each customer's currency and gains the two local customers; its tab counts follow.

## 2026-11-18 · a quotation can start on its own
- 30g gains a mode strip — from an enquiry, from scratch, or copied from an earlier quotation — and a second frame for the from-scratch path. A customer who rings for a price does not raise an enquiry.
- From scratch you pick the customer and the parts. A part we already make brings its drawing, alloy, weight, cost history and pattern; a part new to us brings nothing, so the quotation is marked as needing a drawing and feasibility before it can be sent.
- Why we are quoting is asked, because it is the only way a quotation with no enquiry shows up honestly in the figures: it counts on 30a, which counts quotations, and not in the received-to-quoted figures on 14a, where nothing was received.
- Fixed: the quotation document named Sadiq Foundry Works when the company is Redfern Castings (Pvt) Ltd, and an invented customer "Stellar Industrial".

## 2026-11-18 · the rest of the missing enquiry and quotation screens
- New: 30h send a quotation. Who it goes to, a covering note pre-filled from the revision reason and the open questions, what is attached, the document itself, and the chase set while you are thinking about it. The document is generated from the quotation, so the paper and the record cannot disagree — this is also the quotation print template the 9x set did not have.
- New: 30i a drawing revision arrives part-way through. What changed, and what it makes stale: which feasibility sign-offs must be given again, whether the tools are affected, the cost sheet, the open quotation and the open questions. Three honest ways forward: re-run feasibility then price, re-price only, or record it and decide later. It also points out that a revision quietly resets the reply-by date.
- New: 30j decline to quote, with the judgement written down. Can we pour it, mould it, machine it, test it; is there capacity; is the customer sound — pre-answered from the part and the plant where they can be. The note to the customer is kept on their record, and the rail offers the usually-better answer: quote the part of it we can make.
- New: 30k a customer price list. Rates per kg by alloy, process and quantity band, the per-piece extras, the minimum lot and short-lot charge, and which half moves with the metal index. Simple mode on 30f and the price check on 23a both read off it.
- With these, every gap from the enquiry-and-quotation review is closed. Turn 30 is eleven screens.

## 2026-11-18 · creating a quotation, multi-part, and the last structural gaps
- New: 30g, start a quotation from an enquiry. It carries the parts, volumes, drawings, planned tools, terms, rate card and feasibility conditions across and asks only what it cannot work out: which parts to include, and whether the first price comes from a similar part, from feasibility and the rate cards, or from an empty sheet. This was the hole the quotation chain started in — 30c revised something nothing created.
- Multi-part quotations. An enquiry can hold several parts (1b has Add part, 18i plans tools per part) but a quotation could only hold one. 30g makes one quotation with two lines, and 30f gains a part switcher with a cost sheet per part. The margin that must clear the floor is the one on the set, because that is what the customer buys; a part may sit under it as long as the set does not.
- The example ties into work already drawn: ENQ-26-0152 is the pump set whose tools are planned on 18i and priced on 18l.
- 1c was a feasibility for a water pump housing while 14b linked to it as the review for a brake caliper bracket. It is now FR-26-0150 for that bracket, with notes, process, yield, poured weight, capital and conditions to match, 4 of 7 signed and Machining, Quality and Purchase still out, as 14b says. One of its two conditions is the machining-stock question that is open on 14b.
- 18l quoted tooling in rupees while the quotation it feeds is in dollars. Quoted tooling now follows the quotation; the screen says that what the core box costs us to build stays in PKR on the development.

## 2026-11-18 · one quotation screen, with somewhere to edit the numbers
- 1d and 30b were both the quotation screen. The record wins, and editing gets its own place inside it: the quotation is now one screen with four tabs — Summary (30b), Costing (30f), Revisions and Log.
- New: 30f Costing, 1d's full cost sheet as a tab. Editable only while the revision is a draft; boxed figures are the ones you set, the rest are worked out. Type a price and the margin follows, or a margin and the price follows.
- The floor is shown as a price, not only a percentage: $27.72 on this quotation. Below it the MD signs (30d).
- The sheet says where every rate comes from — metal from the charge mix and iron grade (7g), line and labour from Settings, the subcontract rate from the supplier record (8e) — and that a sent revision keeps the rate it was priced at.
- The metal line now explains itself: poured 16.8 kg at 68% yield, returns credited back, so 11.4 kg is charged. The figure was right on 30b but read as if yield had been ignored.
- On a sent revision the Costing tab is read-only and shows as locked on 30b, so a price that has gone to a customer cannot be changed underneath them. The only way to change one is a new revision.
- 1d is kept as the original exploration and labelled superseded.
- Settled the other overlap: closing an enquiry as won goes through 30e, which is what opens the sales order and the development. Closing an enquiry won with no accepted quotation is not allowed. 14c says so.
- 30c's example floor price was $27.60 against a cost that gives $27.72.

## 2026-11-18 · enquiry and quotation, consistency pass
- One margin floor. 1d gated the MD at 15% while 30a-30d ran on 12%. It is 12% everywhere, so 1d's 12.4% no longer needs the MD.
- One approval chain: every quotation needs the estimator and the sales head; the MD only sees it when the margin is under the floor. 30b and 30d now show all three (R. Novak, S. Hayes, A. Brandt) instead of the MD alone.
- The win figures agree. 30a was showing 12 won and 41% of quoted against 14a's 18 and 62% for the same 90 days. 30a now reads off 14a, and its tabs sum to the 29 quoted (Draft 2, Awaiting approval 2, Sent 3, Accepted 18, Lost 4).
- The Nordline valve bonnet is QT-26-0416 on both 14c and 30a; it was 0417 on the list.
- The enquiry stage follows the furthest quotation: ENQ-26-0149 is Quoted (its quote is sent), ENQ-26-0147 is Quoting (its quote is at rev 3). 14a now states the rule.
- Feasibility is seven departments on 1c, so 14b counts 4 of 7 and names Purchase among those still to sign; 30b's FR-26-0145 is 7 of 7.
- 14b's quote row opened a record for a different quotation; it now opens the list, where QT-26-0421 is a row.
- 30b carries the things the record was losing: the raw-material clause and index base from 1d, and tooling ownership and life.
- 30e uses the same lost reasons as 14c, and says where "no reply" differs from 14c's "no response".
- Turn 1's blurb used the old year-less code format.
- Not done here, still open: 1d and 30b are both the quotation screen and one has to absorb the other; nothing creates a first quotation; quotations are single-part while enquiries are not; 18i and 18l price tooling in PKR while the rest of the flow is USD.

## 2026-11-18 · quotations (T30)
- New (Sales): 30a quotations list, 30b quotation record, 30c new revision, 30d margin approval, 30e close quotation. The Sales menu has had a Quotations item since 1a with no screen behind it; this fills it.
- A quotation keeps one number for its whole life and revisions sit inside it. A revision never overwrites the one before: the customer keeps what they were sent, and the old revision stays on the record marked superseded.
- A piece price under the margin floor (12%) cannot be sent until it is approved. 30c works the margin out as you type and says so before anything is created; 30d is the approver's view and also appears in the approvals queue (7e).
- Accepted closes to a new sales order (23a) with the lines, prices and call-off schedule already in, and closes the enquiry as won (14c). A quote that simply ran out is "no reply", not lost, so the win rate stays honest.
- Changed: 14b's Quotes table now opens 30b. 1d is labelled as the costing editor behind a revision; 14a and 23a point at 30a.
- Still open in Sales: no sales orders list, no customer record page or new-customer form, no quotation or dispatch-note print template, no order amendment or cancellation, no price-list screen, and the older screens (13a-13c, 14a-14c, 1b-1e, 6a) are still in USD while 16e, 18l, 23a and 23b are in PKR.

## 2026-11-18 · Production split into five pages
- Changed (file layout, no screen changes): the Production page had 71 screens and was slow to open, so it is now five pages. Production (15x board and floor, 16a/16b/16g/16h lots, 6b-6e plan to casting lots, 1f 1g 1h 1j 1m) · Cores (29a-29k) · Development (18a-18h 18m-18o tooling, 12a/12b new and replacement, 11a-11c developments and sourcing) · Products (10a-10h products, BOM, materials) · Plant (3a 3n 3b 3c 3e 3f 3g 3m engineering and shop floor, 7a 7c 7g 7h 7k 7m cavity map, stores, iron grade, furnaces, maintenance, floor mobile).
- Screen IDs are unchanged, so 18a is still 18a. Cross-page links, the area nav on every page and the index cards were repointed. 29k sat inside turn 18's markup and now sits with the other core screens.

## 2026-11-16 · list controls and table style, all areas
- Changed (all lists, from review): the funnel is replaced by the chip filter bar drawn on 29k — search, default chips (icon, label, value, chevron; blue tint + ✕ when applied), and a + that adds any other field. Defaults per list: 29a Type, Process · 29d Owner, Where it is · 28c Tool, Where it is (amber attention chips kept) · 16a Stage, Due · 11a Customer, Due · 10a Customer, Process · 10c Type, Below reorder · 14a Customer, Reply by · 13a–13c Industry, Overdue only · 19c Date (applied), Type · 24a Customer, Date. Rule: date if the list grows over time, status as tabs, what users name first, a high-cardinality dropdown. Each screen label lists what + adds. Export stays an icon at the right. 8e, Quality, Overview and Settings lists are not yet converted.
- Changed (29h, from review): the Cores made form no longer has “Used today in a moulding run” (checkbox, run picker, Used). Use is recorded on the moulding run only (29j), which draws from the lot; the form creates the lot for the full quantity.
- Changed (all areas, from review): zebra rows are now a cool light blue-grey (#f2f5fa) so they read apart from the selected row, which keeps the sidebar's beige-grey (#f8f8f9).
- Changed (all areas, from review): the selected row takes the side panel's own background (#f8f8f9) with the blue bar on its left edge; the row's divider lines and the panel's left border are hidden at that row, so the open record and its panel read as one continuous surface. Applies to 28c, 29a, 29d, 24a, 13a–13c, 14a, 8e and the other lists with a panel.
- Changed (all areas, from review): the 29a/29d list treatment now applies app-wide. Filters and Export are icon-only buttons (tooltip carries the name) — Export in the page header of 7i, 8e, 13a–13c, 14a, 19a, 19c, 19e and 24a; Filters as a funnel with count on the right of the 24a toolbar. Every table in Accounts, Sales, Quality, Settings and Overview takes the new style from the page CSS: uppercase 11 px headers with vertical dividers on grey, zebra rows, 9 px cell padding.
- Changed (Accounts): 28c rebuilt on the same pattern as 29a — uppercase divided headers (full height), zebra rows, 42 px rows, asset no. under the name when the panel is on (its own column when off), Details button removed from the toolbar; panel off: expand icon in the last header cell; panel on: » inside the panel.
- Changed (8e, 13a–13c, 14a, 24a): side panels gain a » collapse icon at the top. The collapsed state (expand icon in the last header cell, extra columns) is drawn on 28c, 29a and 29d only; build the others the same way.

## 2026-11-17 · filter-bar pattern
- New (Production, 29k): the shared filter-bar pattern, from the Nomi reference, demonstrated on the cores list. Search is its own row; filters are chips below it. A + opens an Add-filter menu of the screen's fields; picking one drops a chip and opens an editor popover matched to the field's data type — a value list / multi-select (status, type, account, currency), a number range (on hand, weight, amount), a date (presets + custom range), or text (contains / is / starts with). Each chip shows a summary with an ✕; an applied set gains Save (bookmark) and Clear-all icons, and can be re-applied from a Saved filters menu. Replaces the single funnel button. Export and the panel toggle stay as icons at the right. To roll into every list toolbar next.

## 2026-11-15 · cores and core boxes
- Changed (from review): 29a and 29d toolbar — Filters and Export are icon-only buttons on the right; the Details button is gone from the toolbar. Panel off: the expand-panel icon sits in the last header cell after the final column (above the row ⋯ menus). Panel on: the collapse icon sits inside the side panel, top right.
- Changed (from review): list header column dividers (29a, 29d) now run the full height of the header row.
- Changed (from review): new table treatment, applied across the Production page. Column headers are uppercase with vertical dividers and a grey ground; body rows are zebra-striped (every other row tinted); the record code sits under the object name where there is no separate code column (cores, core boxes, and the core page tables); one font and size across all columns (codes no longer in a mono face); the Filters control is a funnel icon on the right of the toolbar with a count badge, next to Export and Details. No columns were removed. The two cores lists (29a, 29d) were rebuilt for this; the embedded tables pick it up from the page style.
- Changed (from review): 29e core box page compacted. Field labels are 11 px uppercase with 14 px medium values (as 28g); the facts sit beside the photo; ownership & custody is one labelled strip; the cavity map and life & maintenance share a two-column row, with the maintenance list inside the life block. The page now fits a screen. Head shows the name before the code.
- Changed (from review): tab counts on 29a and 29d are now badges — a rounded pill with tabular figures, blue tint on the open tab and grey on the rest — so a count never reads as part of the tab label.
- Changed same day (from review): 29b core page — the photo moves to the left of one block that holds the facts grid, the description and the engineering notes, so nothing sits under the photo. Record head shows the name before the code.
- Changed same day (from review): 29a and 29d rebuilt in the standard list layout used by 28c — two header rows (title + actions, then status tabs), one toolbar (search, Filters, filter chips, Export, Details toggle), defined columns with the name first and the code beside it, sortable headers, 40 px rows, footer totals. Collapsible Details panel: panel on, a row click fills it (core: boxes that make it, needed by, recent; box: cavity map, life, custody, open maintenance); panel off, a row click opens the record page and the freed width adds columns (cores: Mark, Needed by; boxes: Where it comes from, Commissioned) plus a row ⋯ menu. Both states are drawn.
- New (Production, T29): 29a cores list, 29b core page, 29c core form, 29d core boxes list, 29e core box page (ownership & custody, cavity map, life & maintenance), 29f core box form (simplest / multi-core / customer-owned states), 29g core stock, 29h cores made, 29i core lot page, 29j cores panels on the product page and the moulding run.
- Settings-dependent: core stock (29g–29i and the On hand column) and lot checks are off by default; each screen shows its off/empty variant.
- Options to pick: cavity map M1 (plan view) / M2 (slot strip) / M3 (table + mini map); core box form layout A (stacked) / B (live preview).
- New codes: CR- (core), CL- (core lot). A die is a core box — same screens.

## 2026-11-15 · asset register: one screen, toggle-able panel
- Changed (Accounts): 28c and 28l merged into one 28c. A Details button (panel icon, right end of the toolbar) opens or closes the side panel, and its state sets the row-click behaviour: panel on, a row click selects and shows the asset in the panel (6 columns); panel off, a row click opens the asset page 28d and the reclaimed width adds Asset no., Last used and Cost (10 columns), with quick actions on the row ⋯ menu. 28l removed; its nav chip dropped from the page and the index. Two frames on the screen show the on and off states.

## 2026-11-15 · dispose-asset affordance (28h)
- Changed (Accounts): 28h controls made obvious. Disposal mode (Sell / Sell as scrap / Write off) is now three radio option cards, each with a one-line description, the chosen one ringed blue — replaces the small segmented toggle with the differences buried in a sentence. How-the-money-comes-in (Sales invoice / Received now) is two radio cards the same way. Fields now read as inputs: 40 px boxes with uppercase labels, Buyer and Sales tax carry a dropdown chevron, Date of sale a calendar icon, Price shows an Rs prefix and the active (focused) blue border and caret, Sales tax shows its computed amount as a derived note. Dialog widened to 940.

## 2026-11-14 · capital purchase bill cleaned up
- Changed (Accounts): 28g fact labels are now 11 px uppercase, letter-spaced, tertiary grey, so they read apart from the values (values 14 px medium, detail 12 px grey).
- Changed (Accounts): 28g laid out cleaner. Facts in one labelled grid of eight (supplier, invoice, due, bill total; PO, goods receipt, asset, asset class) with the Goods/Services toggle dropped — the PO decides it. Bill lines: one line per fact, Treat as dropdown with its posting account underneath, Account column folded into it. Totals right-aligned under the lines. The journal ("This bill posts") spans the full width at the bottom with the goods-receipt explanation beside the label, replacing the two stacked postings in the panel. Panel keeps checks and an After posting note.

## 2026-11-12 · fixed asset register list
- Changed (Accounts): 28c back to list + side panel, made denser: 40 px one-line rows (18 on one screen), tabs and actions in one 48 px header, search, filters and To do chips in one toolbar row, totals and rows-per-page in the footer. Columns: Asset (name + FA no., inline warnings), Tool, Where it is, Life used, Book value, Status. Row click selects; Open asset, Enter or double-click goes to 28d. Panel: warning to clear, life bar, cost, depreciated, book value, method and rate, in use since, location, last used, used for, came from, last three events.
- New (Accounts): 28l, the alternative without a panel: whole row opens 28d, adds Asset no., Last used and Cost columns, row ⋯ menu (Set tax class, Move, Print asset tag, Sell or retire). Choose 28c or 28l.
- Changed (Accounts): 28d header gains Previous / Next; Retire becomes Sell or retire.

## 2026-11-10 · fixed assets: buy, sell, reverse, roll-forward, ledger check
- New (Accounts): 28g buying plant through a purchase bill (capital PO + goods receipt; Treat as per line: Asset, Add to asset cost, Stock; waits in capital work in progress until Put in use), 28h selling an asset with proceeds (Sell, Sell as scrap, Write off; depreciation to the sale date; gain or loss; sales invoice to the buyer), 28k reversing a depreciation run (one reversing journal, newest run first, period must be open; blocked state), 28i roll-forward by class, 28j register-to-ledger check (cause of a difference and how to clear it).
- Changed (Accounts): 28a, 28b, 28e, 28d, 28f use accounts from the chart (19a): PRA input 22-04-02, pattern depreciation 51-06-03 split from plant depreciation 51-06-04, accumulated depreciation by class 21-02-01 to 21-02-05.
- Chart additions: 22-04-02 Sales tax input (PRA), 21-02-05 Acc. depreciation: lab and vehicles, 42-01-05 Gain or loss on disposal.
- Changed (posting rules, 19g): a journal line to an asset account (21-01 to 21-04) must name an asset.
- To confirm with the accountant: input tax on plant, sales tax and advance tax on selling a used asset.

## 2026-11-09 · service bills for tooling, fixed asset register, invoices list
- New (Accounts): 28a supplier bill for a service (pattern or core box/die maker): PO stage + confirmed milestone in place of a goods receipt, extra lines not on the PO flagged, services sales tax by province (PRA/SRB, from 20e), income tax withheld at payment. Who pays for the tool (18a) decides the account. 28b the same bill when the foundry pays (capital work in progress). 28e capitalise on development approval. 28c fixed asset register (list layout). 28d asset card with cost build-up, usage-based depreciation by fills, separate tax book. 28f monthly depreciation run, one journal.
- Changed (Accounts): 24a invoices list: tabs with status dots, Filters button, date presets and calendar, sortable columns, column order Date · Number · Customer · Status · due · Total · Balance, one font in the table, credit-limit flags and a Customer credit block in the side panel.
- A die is a core box: no new tool kind. New codes: BL- (supplier bill), FA- (fixed asset).
- Tax rates and tax depreciation classes are samples; confirm with the accountant before build.

## 2026-11-07 · round 4: components, due-now homes, bell, quality, moulding, furnace, owner
Full rule set only; Basic comes later. Type is Instrument Sans (the app's Tailwind theme), codes in mono. Seven new pages, each with its own screen IDs:
- `Foundry ERP R4 Components.dc.html` · build first. C0 tokens and four densities (desktop, tablet, tablet with gloves, phone), C1 tablet tab bar, C2 numeric keypad + count field, C3 home row (+ Not ready), C4 blocker line, C5 stage strip, C6 status chips (IN PRODUCTION, ON HOLD, CLEAR, READY, PACKED, DISPATCHED, OPEN, CLOSED), C7 notice row / toast / tablet strip / bell badge / heat chip, C8 big next-step button, C9 NEW tag. Every component shows default, pressed, disabled, warning, danger and empty.
- `Foundry ERP R4 Homes.dc.html` · route `/` for every role, filtered by permission: H0 how one component set makes all five (with the permission behind each row), A1 production manager desktop, A2 moulding supervisor tablet, A3 owner phone + desktop, A4 furnace operator tablet, A5 stores and dispatch clerk tablet; each with empty and warning, A1 and A3 with loading. Replaces the dashboard, `/home` tiles and `/quick/today`.
- `Foundry ERP R4 Bell.dc.html` · BELL·1–9 and BELL·L: panel grouped Urgent / To do / For information, mark all read skips urgent, tablet sheet above the tab bar, tablet strip, desktop toasts, black "!" badge, one notice for several roles, empty, loading, event table.
- `Foundry ERP R4 Quality.dc.html` · Q·1–Q·6 (Q·3 also on desktop), Q·5A and Q·5B side by side, Q·E, Q·W (stricter thermal rule, an option), Q·L.
- `Foundry ERP R4 Moulding.dc.html` · F3·1–F3·5, F3·E, F3·W.
- `Foundry ERP R4 Furnace.dc.html` · F4·1–F4·6, F4·E, F4·W, F4·W2.
- `Foundry ERP R4 Owner.dc.html` · F5·1–F5·5 (F5·4 Pass to is NEW), F5·E, F5·W, F5·L.

Approved:
- Bell (BELL·1–9, BELL·L) as drawn, including the fold rule (2 or more notices from one poll fold into one toast or strip) and the "Save first?" guard when Open is tapped on a tablet strip over unsaved numbers. Ready to build after R4 Components.

Decided in review:
- Charge overdue is an Urgent bell notice to melt shop and planner (round 3 wins over the older NO TRIGGER).
- The On track line never carries a hold count; held lots are listed in Where it's slipping.
- Finished rows on a moulding list stay until the list closes, folded into one "Done · n patterns" line at the bottom.
- On the furnace, the most overdue step gets the big button; on a tie the charge wins; the other is a 56 px row.
- Quality tablet tabs are Home · Lots · NCRs · Scan.

Still open (flow notes on the frames): Raise invoice needs `finance.manage`, which the Stores and dispatch role does not hold (A5); F5·5 row has two buttons (Open, Reply) against the one-button rule; Flow 4 renames the "Next heat" tab to "Heat" while a heat runs.

Not in this round (build from the sketches with these components): planner Prepare list and Resolve (Flow 2), clerk D·1–D·5, Flow 1 Basic.

## 2026-11-01 · international
- New: 20a region and formats, 20b new company setup, 20c entry with Journal/Payment/Receipt and multi-currency, 20d numbering and printing per type, 20e tax codes per region pack, 20f exchange rates, 20g payment print, 20h Urdu right-to-left entry.
- Superseded: 19b by 20c, 19h by 20g. JV/CPV/BPV/CRV/BRV labels and prefixes are dropped; use JNL-, PAY-, REC-.
- Changed: tax lines on 19g and 19o now come from tax codes (20e).

## 2026-11-01 · chart of accounts to client structure
- Changed: 19a chart of accounts now Category › Section › Nominal account with 6-digit codes (22-06-01); 19b–19h renumbered; 19e rebuilt.
- New: 19i new account, 19j edit account, 19k new section, 19l edit section, 19m sections list with Up/Down, 19n charge and deduction box settings, 19o invoice charges and deductions.

## 2026-11-01 · split by area + accounts core
- Structure: the single design file is split into an index plus Overview, Sales, Production, Quality and Accounts files. Screen IDs are unchanged.
- New (Accounts): 19a chart of accounts, 19b journal entry with voucher types, 19c day book, 19d ledger statement, 19e trial balance, 19f opening balances, 19g posting rules, 19h voucher print.

## 2026-10-31 · pattern development with several tools
- New: 18a development page with Tooling panel (rows per tool: kind, route, made by, pays, quoted/actual, due, status; row menu), 18b one-tool state, 18c panel states (empty + kind picker, rework, shared tool, register record without product, tracking off), 18d gated move Warn/Block, 18e send back with tool checklist, 18f start dialog Tools chips, 18g replacement picker for pattern or core box, 18h core box page (ownership/custody, developments, Start replacement) + pattern panel, 18i enquiry planned tools per part, 18j change-route warning, 18k intake tooling line, 18l quote tooling, 18m development list Tools column + tool search, 18n development rules, 18o cancel with started tools.
- Changed: 11b Pattern/Pattern by/Actual pattern cost fields replaced by 18a Tooling panel. 12a gains Tools row. 14b Tooling box extended. 1f Replace pattern becomes Start replacement. 11a gains Tools column.

## 2026-10-30 · lots, process depth v2, repeating lines, stores movements
- Currency: new screens are PKR (Rs). Earlier screens still show USD.
- New: 16a lot queue with bulk stage moves (skips gated lots), 16b casting lot detail (stages, qty split, ready checks, holds/waivers, trace), 16c first-iron hold (first article by cavity, release needs), 16e repeating/staggered order line (call-offs, repeat-ahead, forecast diff), 16f 3× forecast change confirm, 16g stock movement form (issue/scrap/count/transfer), 16h count sheet and reconciliation.
- Changed: 16d process depth v2 replaces 7f (grouped rules incl. floor rules, menu effect, How you work questions, step count).

## 2026-10-29 · production board
- New: 15a production board (six lanes: plan, mould, melt, pour, after pour, QC), 15b–15g action drawers (release list, moulds made, count, heat start/close, pour with gate, move batch), 15h full-depth board, 15i phone floor, 15j menu after merge.
- Replaces: Today page. Takes off the menu: planner, work lists, melting plan, next heat, quick floor, mould readiness, mould count (pages stay reachable from links).
- Changed: 6b plan is superseded by 15a Plan lane + 15b.

## 2026-10-28 · enquiries
- New: 14a enquiries list (stage, pattern route, value, reply-by, win/loss summary), 14b enquiry record after sending (stage, pattern decision, feasibility, customer questions, quotes, log), 14c close enquiry dialog (won/lost/declined + reason).
- Changed: 1b header gains Start pattern development.

## 2026-10-28 · customers
- New: 13b prospect selected (path to serial supply, missing items), 13c on hold selected (reason, what is blocked, collection, release).
- New: 13a customers list (supply status, parts, open orders, developments, PPM, terms, balance; side panel with defaults for new work).

## 2026-10-28 · development types
- New: 12a new development dialog (type: new part, new product, replacement, modification; sets source, copied fields, stages), 12b replacement development (reason, who pays, notification/PPAP, production cover, old pattern disposition).
- Changed: 11a Type column; 1f Replace pattern button; 10a Start development button.

## 2026-10-28 · pattern development
- New: 11a developments list, 11b development record (points to resolve, log, engineering time), 11c pattern sourcing and charge (route, supplier quotes, fee, staged billing).
- New record code: PD-. Point topics and stages are foundry-editable lists.
- Links into existing: 1b, 1c, 1f, 3a, 7b, 8a, 1k.

## 2026-10-28 · first import
- New: T1–T8, all screens listed in the README screen index.
- Build now: Phase 1 (app shell from 1a and 4a), then Phase 2 (shared components).
- Known gaps: hover, focus, loading, empty and error states not designed; nav doesn't yet include the T7/T8 modules.
