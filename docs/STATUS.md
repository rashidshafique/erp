# Project status

Updated 2026-10-27.

## Done

**Order to pour (Stage 2, see STAGE2.md)**
- Customers, sales orders, contract review, production orders, melting plan, iron grades, thermal analysis, pouring gate, trace to dispatch.
- Repeating order lines (2026-10-26, paused to finish Finance first):
  - Done: a line repeats every week or month with no new PO. Each period adds a delivery of the line's quantity and grows the ordered quantity, scheduled up to the "repeat ahead" order rule (4, 8 or 13 weeks) and topped up daily by `sales:repeat-deliveries`. A repeating line's total is its deliveries: turning a one-off line into a repeating one replaces its quantity, changing the quantity, dates or end re-plans from today, and removing a delivery lowers the total; stopping keeps what is scheduled. The form previews each repeating line's deliveries and total, a line total that changes more than 3× needs confirming, and no line can go below released production or shipments (the refusal names the production orders). Delivery changes and the daily top-up show on the order's history. An order with a line still repeating is not closed by a dispatch. Invoice per dispatch as usual.
  - Forecast (2026-10-26): "Change forecast" on a repeating line sets the quantity of each period still to come (0 removes it, past periods stay), with a live line total. The total follows, so the same floor applies, a period changing more than 3× needs confirming, a higher total is released at once on a live straight-to-production order, and the change shows on the order's history.
  - Staggered deliveries on the order form (2026-10-26): a one-off line can tick "Staggered deliveries" and list its dates, quantities and notes on the edit form once the order is saved (the new-order form leaves them out), with what is still to schedule; they cannot add up to more than ordered. Editing changes, adds or removes them (each on the history), and unticking clears them.
  - Left, in order: (1) a shortfall carried into the next period when a period ships short; (2) one production order per period instead of one per line; (3) standing-order and blanket-order documents (a blanket order agrees a total over a span and is called off in releases).

**Streamlining for small foundries (2026-10-27, plan in STREAMLINE.md)**
- Each step that can shrink is a process-depth rule (Settings > Process depth, in sections); Basic collapses it, Full keeps it. New floor rules (`FloorRules`): work lists, mould assembly, pour entry, melting plan, quick or full floor screens. A rule that leaves a screen out also takes it off the menu (`RuleMenuFilter`, wrapping the foundry size filter).
- Contract review "only when something is off" (AUTO): runs on save, a clean review approves itself, warnings need one acknowledgement and one Approve. Accepted quotations for repeat work follow the order flow.
- Automatic mould assembly when output is saved (all entry screens); a blocking flask shortage leaves the moulds for the run page with a warning.
- Quick pours make lots (`OneStepPour`): waiting moulds by pattern, accepted moulds assembled as needed, a run "made, not entered" for the rest. Poured entries linked later join their pour. The full pour screen has a one-step form under the rule, opened from "Pour moulds" on the heat page.
- Work lists off: the planner fills the quick moulds made grid instead. Quick screens: production orders open the grid on their pattern.
- Today board (`quick.today`), start page for the floor on the quick screens. "How you work" questions (Settings) set the rules.
- Fixes: melting plan keeps started orders; quick and straight-to-production releases respect the release approval; a review goes stale after an edit and re-review leaves a live order live; sign-offs record the user (not both roles); quick pours follow the gate mode; Micro shows Packing.
- `StepCountTest` pins Basic at seven forms from order to dispatch. Quick pours made before 2026-10-27 have no lots (no backfill).

**Production**
- Moulding runs: bulk entry, three-step record screen, KPI strip, mould hardness, stations, sand test from a run.
- Work lists: planned items, runs matched to the list, draft-list start setting, remake shortfall added in one click.
- Planner (`/planner`, `OrderPlanner`): open orders ranked by next unshipped delivery, then priority; moulds left loaded onto stations by moulds per shift and patterns at once; projected finish with late / at-risk flags; poured pieces still in the queue per order; the first shift goes onto a draft work list in one click, optionally rebuilding the melting plan. Not done: working-day calendar, per-pattern rates, downstream stage capacity.
- Flaskless moulds assembled automatically when output is recorded.
- Pouring, heat timeline and readings, charge batches, material consumption.
- Sand lab: tests, limits, sand systems, sortable and filterable tables.
- Patterns: revisions, cavity map, ownership, preferred station.

**Domain modules (`app/Domains`)**
- Maintenance (gauges, calibration), Tooling, HR and payroll, CAPA, Dispatch and packing (finished goods), Enquiry and quotations, Purchasing, Documents, Compliance, Parties, QuickFlow, Sales, Inventory (stores), Engineering (bill of materials), Finance (invoicing, bills and payments), General ledger, Taxation.
- Licensing unit with the module toggle page, module hooks (nav, permissions, approvals, schedule, search, codes).

**Platform**
- Approvals, attachments, audit, custom fields, codes, data import, roles, profile pictures, global search.

**Stores (`app/Domains/Inventory`, `app/Services/Stock`)**
- `StockLedger` is the one write path for receipts, FIFO issues and transfers. Movements name their item (`item_type` + `item_id`); `StockItemTypes` and `StockMovementTypes` are registries a module can add to. `Material` is a `Stockable`, `MaterialLot` a `StockLot`.
- Stock locations register, record a stock movement (issue, scrap, count adjustment, transfer) and stock by location, all Livewire. Lot receipts can name a location.

**Finance (`app/Domains/Finance`, started 2026-10-24)**
- Sales invoices and credit notes raised from a dispatch, a sales order or with free lines (never tied to dispatch); draft, issue (numbered `INV-<year>-0001`), void, print.
- Customer receipts spread over invoices (rest stays on account), ageing report, customer statement, tax rates and defaults under Settings > Invoicing & tax. Permission `finance.manage`, role ACCOUNTS.
- Supplier side: purchase orders in Purchasing (draft, issue with optional approval, receive into quarantine lots, over-receipt rule); supplier bills and credit notes in Finance entered from a PO or free, posted as `BILL-<year>-0001` with a duplicate check on the supplier's number, 3-way match against the PO (off, warn or block, with a tolerance), supplier payments spread over bills, payables ageing and supplier statements. Permissions `purchasing.manage` (role Buyer) and `finance.manage`.
- General ledger (`app/Domains/Ledger`, module "General ledger", 2026-10-26): full double entry. Every journal balances and goes through `Ledger::post`, which also refuses group or switched-off accounts and dates on or before "books closed to".
  - Chart of accounts seeded in local style (`DefaultChart`, 1000 assets to 7000 finance costs); accounts and sub-accounts can be added anywhere, but not under an account that already has postings; groups roll up.
  - Finance fires `DocumentPosted` / `DocumentVoided` inside its transactions; the ledger posts (`PostingRules`) or reverses on the void date. A refusal undoes the document. Invoice: Dr receivables, Cr sales and tax per authority. Bill: Dr each line's account (new optional Account column on bill lines) and input tax, Cr payables. Receipt and payment: cash or bank by method. Credit notes the other way.
  - Settings > Ledger accounts: account per posting role, payment method map, tax authority map, closed-to date, "post what is missing" (also `ledger:post-missing`) for documents from before the ledger.
  - Manual journals (reverse, never edit), journal list, trial balance, account ledger with running balance, ledger link on each document. Permission `ledger.manage` (role ACCOUNTS).
  - Advances kept apart (2026-10-28): money not on an invoice or bill posts to customer advances (2130, a liability) or advances to suppliers (1140, an asset), not inside receivables or payables. Putting it on a document later (receipt or payment page, "Use money on account" / "Use advances", or automatically) posts the move: Dr 2130 Cr 1130, or Dr 2110 Cr 1140, dated that day. Why: IAS 1 (para 32) forbids netting assets and liabilities, and money paid ahead is owed back in goods or cash, so netting it understated both receivables and liabilities. Earlier receipts and payments with money on account are moved once by a migration (`AdvanceReclass`, dated today or the day after the closed-to date), and again from "Post what is missing" if that failed; it is idempotent. New ledger roles `customer_advances` and `supplier_advances`.
  - Still open on advances: income tax withheld when an advance is paid to a supplier (s.153 applies at payment; withholding is worked out on the bill today), and sales tax on advances received for goods (time of supply can be the payment). Both wait for the tax adviser.
  - Not posted yet: stock movements and cost of sales (periodic for now).
  - Financial statements (2026-10-26, read only): profit and loss over a period with the cost-of-sales group (Settings > Ledger accounts, 5000 by default, or none) before gross profit, beside the period before or a year earlier; balance sheet as at a date with profit not yet closed under equity, beside a second date; general ledger of every account that moved or carries a balance. All printable.
  - Bank accounts and reconciliation (2026-10-26): bank details on an asset ledger account (or a new one under the bank group); a statement opens on the last reconciled balance, lines up to its date are ticked as cleared and it finishes at a nil difference; bank charges or profit on the statement are posted and ticked in one step; the latest statement can be opened again. Journals are never changed (cleared lines live in `ledger_reconciliation_lines`). Not built: statement import from a bank file.
- Tax register (Finance > Tax register, 2026-10-26, read only): output tax on issued invoices or input tax on posted bills over a period, per authority, tax and rate and per document with registration numbers and tariff code; credit notes negative by their own date, voids left out; CSV download for the return.
- Tax certificates (Finance > Tax certificates, 2026-10-27): a customer's certificate for tax it withheld, for one tax (as worked out on the invoices, or "Other" for tax the invoices did not withhold), spread over its open invoices up to what is withheld and not yet covered. It settles that part of each invoice (balance, ageing and statement count it) and posts Dr tax withheld by customers (new ledger role, 1163), Cr receivables; a void puts it back and reverses. Ageing shows what is awaiting a certificate; receipts fill only the money part; an invoice with a certificate cannot be voided.
- Advances (2026-10-27): a receipt can name the sales order and a supplier payment the purchase order it is an advance for. "Use money on account" on an invoice and "Use advances" on a bill put that money on it, the document's own order first and never another order's (a payment naming no order goes anywhere). Settings > Invoicing: "Money paid in advance" MANUAL (button, default) or AUTO (on issue / post). Since 2026-10-28 the ledger keeps advances in their own accounts (see Ledger below).
- Credit limits (2026-10-27): a customer's limit, hold (with reason) and release-until date on the business partner page (Finance panel, with what is owed, open orders not invoiced, still available and overdue). Contract review adds an order-level CREDIT check with the order's value; a dispatch is checked before it is made. Settings > Invoicing: Off / Warn (default) / Block, whether open orders count (before tax), and an overdue-days threshold (0 = off). A hold always blocks; a release passes everything until its date. Sales and Dispatch reach it through the core `CreditControl` contract (no limits without Finance).
- Left, in order (each changes how existing documents count, so they wait for a decision):
  1. Done: withheld tax settlement (tax certificates above).
  2. Done: advances, and bill price into lot cost (2026-10-27): posting a bill line from a purchase order line re-costs that line's lots at the billed price (net of tax, weighted over its posted bills), with the stock left at the new cost; stock already used keeps its cost and the difference is recorded (`lot_cost_adjustments`, shown on the bill). A void puts the order price back. Rule under Settings > Invoicing, on by default. Supplier credit notes are not counted.
  3. Purchase-side tax (2026-10-27, started): bill lines are goods or a service and keep the tax the supplier charged, named by the rules (tax and authority) while the typed percent stays the amount: the matching taxes when they add up to it, else one tax at the typed percent named after the first match. The input tax register shows authorities. Tax we withhold from suppliers (2026-10-27): with "We are a withholding agent" ticked under Taxation, purchase-side rules (new in the Pakistan pack: a fifth of sales tax from registered suppliers, income tax on goods 5% and services 9%, both on the value with tax; added to earlier installs by `TaxSetup::addMissing`) keep tax back on each bill line. The bill total is unchanged; the supplier is owed the total less it (balance, ageing, statement), and posting moves it from payables to withholding tax payable (2150, new ledger role). The input register lists it as withheld. Tax return (Finance > Tax return, 2026-10-27, read only): per authority and month, output less input, less the share of sales tax customers kept back, plus the share we kept back from suppliers; income tax withheld either way listed apart. Each tax amount now keeps its basis (NET, GROSS or a tax code) so a withheld share of sales tax is told from income tax; older lines are judged by their base. Paying the authority is a manual journal (linked from the page).
  4. E-invoicing (FBR digital invoicing for Pakistan): calls FBR's service, needs credentials. Not started.

**Price lists (`app/Domains/Sales`, 2026-10-27)**
- Sales > Price lists: lists with dates, on/off and one default; prices per product per piece or per kg with quantity breaks (pieces or kg in the price's basis). A customer's own list (set on the list page or the partner page) is used first; the default fills its gaps. A per-kg price becomes a piece price by the casting's net weight and back.
- The order form fills a line's price from the list when the product, quantity, unit or customer changes, unless someone typed their own price. Contract review adds a PRICE check for a line under its list price (warn by default; off or block on the price list page).
- "Copy for a price change" makes a new list with every price moved by a percent, switched off until checked.
- Not built: discounts as such (a customer list is the discount), quotations do not use the lists yet.

**Bill of materials (`app/Domains/Engineering`, level 1, 2026-10-25)**
- Each product revision has a list of materials with a quantity and a basis: per casting, per mould or per kg poured. Metal is not listed; the planner works it out from the cavity map. Panel on the product page; a revision with no list can copy another revision's.
- A production order shows the need (list times planned quantity) against stock on hand. Per mould needs an approved pattern with a cavity map; per kg poured uses the planner's metal per product. Display only: nothing is reserved or issued.
- Not built: contract review check, scrap allowance, issue and backflush through the ledger, cores as made items, costing roll-up.

## Missing / next
1. HR and the first part of Stores are done; Sales split done (sales orders and contract review in `app/Domains/Sales`; customers, production orders and the melting plan stay in core). Melting, pouring and moulding stay in core.
2. Empty module folders still to fill or remove: Commercial, Costing, Engineering, MRP, Melting, Moulding, Pouring, Product, Production, Quality, Sand, Traceability.
3. Core still calls module classes directly (gauge calibration checks, tooling life panels). Planned fix: interfaces bound by modules, plus `dependsOn()`.
4. STAGE2.md "not built yet" is out of date: inspection and dispatch screens now exist. Still to confirm: screens to add mould assemblies to a pour and to create casting lots.
5. ISO 9001 readiness gaps listed in `iso-9001-readiness.pdf` are not tracked against the code.
6. Quick QC tally (`/quick-qc`) records good, rework and rejected counts per product over open lots oldest first; the NCR threshold is a process-depth rule, and every process-depth rule can now be set on its own.
7. Lot grouping rule (`lot_grouping`: MOULD or POUR): POUR makes one lot per pour and product, with every mould kept in `casting_lot_pour_mould_assembly`. The production queue moves ticked lots through several stages at once (`lots.bulk-stage`); packing and dispatch have an all-lots tick. Not done: a DAY grouping (needs several pours per lot). QuickFlow pours make lots since 2026-10-27.
8. README is still the stock Laravel text.

## Gap list: Synchro ERP / B&L Odyssey parity

Estimate (2026-10-23, before Finance): about 60-70% of foundry-specific functionality, 20-30% of a full ERP. Since then invoicing, payables and the general ledger have been added. Based on general knowledge of both products, not checked against current vendor feature lists.

Covered: order to pour, metallurgy and thermal, moulding, sand, patterns, tooling, quality and CAPA, casting lots, packing and dispatch, HR and payroll, approvals, audit, module licensing and per-rule process depth.

In priority order:
1. **Stores/Inventory** (next in the module split): stock ledger, bins, issues, GRN, stock valuation.
2. **Costing** (`Costing` is empty): actual versus standard cost per casting (metal, energy, labour, fettling).
3. **MRP and scheduling** (`MRP`, `Production` are empty): material requirements planning, finite capacity scheduling.
4. **Finance** (in progress): invoicing, receivables, supplier bills, payables, the double-entry general ledger, P&L, balance sheet, bank reconciliation and the tax register are in; still to do, in order: purchase-side tax and returns, e-invoicing (list under Finance in Done).
5. **Outside processing and machining routings**: subcontract flow, machining steps.
6. **Reporting and BI**: management dashboards, customer scorecards, statutory report exports.
7. **Integration**: EDI, e-invoicing and tax filing for the target market, barcode and device input.
8. **Housekeeping**: track the ISO 9001 gaps against the code, replace direct core-to-module calls with interfaces and `dependsOn()`, write the README.

**Notifications and reorder alerts**
- `Notify` sends an `AppNotice` to everyone holding a permission, or to named roles; stored with Laravel's database notifications. The bell and growl-style toasts in the top bar (`notification-center`, Livewire) poll every 10 seconds. Websockets were not used: they need Reverb or Pusher and a socket server.
- `ReorderWatcher` raises one notice to `stores.manage` holders each time a watched material (tracked, active, minimum above zero) falls to its minimum, and re-arms when stock is back above it. It runs after every ledger receipt or issue, when a material is edited, and hourly (`stock:check-reorder`). The Reorder alerts page (Purchases) and a dashboard "Needs attention" item list the materials with a suggested order quantity.

**Tax and reporting details (Pakistan first)**
Plan for bought-in goods sold as they are, in order: (1) trade details on items, done; (2) sales lines that point at any stock item, with dispatch issuing through the ledger; (3) a tax module of rules by jurisdiction, item class and customer, starting with Pakistan sales tax; (4) e-invoice output per jurisdiction (FBR digital invoicing for Pakistan).
- Materials and products carry `inventory_class` (raw material, finished goods, stock-in-trade, consumable, stores and spares), `tariff_code`, `tax_uom` and `origin_country` (`HasTradeDetails`). Every stock movement records the class it was posted under; the ledger takes an `inventory_class` override, so stock bought to resell reports apart from the same item bought to use.
- `TaxJurisdiction` reads the `tax_jurisdiction` setting (default `PK`) for the tariff code's name and format: an 8-digit PCT code written 7325.1000. It asks the installed country pack first.
- Invoices, bills and purchase orders get their taxes from the `TaxCalculator` contract (core). `FlatRateTax` is the default (one percent per line, as before).
- Taxation module (`app/Domains/Tax`, Settings > Taxation):
  - A country is a `Jurisdiction` pack registered in `TaxJurisdictions`; Pakistan is the first.
  - Data is kept as rows: `tax_authorities` (FBR federal; SRB, PRA, KPRA and BRA provincial), `tax_components` (dated rates, charged or withheld, based on net, gross or another tax) and `tax_rules` (side, supply type, place of supply, party status, withholding agent, inventory class, code prefix, then the taxes to add).
  - "Load defaults" fills the starting set. A rate change starts a new dated row.
  - Partners get a Tax panel (`party_tax_profiles`) through the new `ModuleRegistry::panels('party.show')` hook.
  - Items carry `supply_type` (goods or service).
- With the module on, `RuleBasedTax` taxes sales invoices.
  - Every active rule in force on the invoice date that matches a line adds its taxes at that day's rates: charged taxes first, then withheld ones.
  - The tax of an authority the company isn't registered with is left off once any registration is ticked.
  - A line no rule covers keeps its chosen percent.
  - With no country loaded, invoices work exactly as without the module.
- Invoice lines keep `supply_type`, `tariff_code`, `inventory_class` and a `taxes` JSON snapshot. Invoices keep `place_of_supply` and `tax_withheld` (`payableToUs()` = total less withheld).
- The print shows seller and buyer registrations, the PCT code, each tax with its rate and authority, and the amount withheld.
- Checks before issuing (Off / Warn / Enforced): buyer registration number, tariff code on goods lines, an authority you're not registered with.
- Purchases (bills, POs) still use the typed percent.
- Not yet:
  - Receivables still count the full total; withheld tax needs settling against the withholding certificate.
  - Credit notes are taxed at the original invoice's date with the rules in force then.
  - FBR digital invoicing (the register per authority is done, under Finance).

### Stores: store tree (2026-10-28)
- Location types are a registry (`StockLocationTypes`), so a module adds its own and can mark one as external (stock is ours but sits with someone else, such as a subcontractor). The register shows stores and sub-stores as a tree, each with what it holds itself and with everything below it (`StockLocationTree`, `StockLevels::onHandByLocation`).

### Stores: not built yet
- Finished goods are not on the stock ledger. They need a decision on their stock lot (casting lot or a new stock lot) and a second registered item type.
- Cost layers still point at `material_lots`; the stock ledger refuses lots of other item types until that is designed. Stock is not posted to the general ledger either (periodic: purchases are expensed, inventory adjusted by journal).
- The unused `inventory_reservations` table has no screens.
- Existing lot stock has no location; it shows as "not put anywhere" until transferred.
- Movement types have no module hook yet; `StockMovementTypes::register()` is called directly.

### Menu
Icons come from the free Untitled UI set (MIT), copied into `NavIcons` as inline SVG; factory, flame, sand, mould, crucible and planner are drawn by hand in the same style.
Quick flow sits above Production (module nav groups take `'position' => 'top'`). Melting holds heats and furnaces; materials, charge recipes, suppliers and stock screens are under Purchases. Finished goods, packing and dispatch are under Sales & Planning.
