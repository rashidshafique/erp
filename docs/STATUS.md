# Project status

Updated 2026-10-26.

## Done

**Order to pour (Stage 2, see STAGE2.md)**
- Customers, sales orders, contract review, production orders, melting plan, iron grades, thermal analysis, pouring gate, trace to dispatch.
- Repeating order lines (2026-10-26, paused to finish Finance first):
  - Done: a line repeats every week or month with no new PO. Each period adds a delivery of the line's quantity and grows the ordered quantity, scheduled up to the "repeat ahead" order rule (4, 8 or 13 weeks) and topped up daily by `sales:repeat-deliveries`. Editing the quantity changes future deliveries; stopping keeps what is scheduled. An order with a line still repeating is not closed by a dispatch. Invoice per dispatch as usual.
  - Left, in order: (1) a forecast grid on the order to change the quantity of each period; (2) a shortfall carried into the next period when a period ships short; (3) one production order per period instead of one per line; (4) standing-order and blanket-order documents (a blanket order agrees a total over a span and is called off in releases).

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
  - Not posted yet: withheld tax (receivables keep the full total), stock movements and cost of sales (periodic for now), receipt allocations (not needed: both sides sit in receivables).
- Left, in order:
  2. Financial reports: profit and loss, balance sheet, general ledger by account.
  3. Bank and cash: bank accounts, reconciliation against a statement.
  4. Withheld tax settled against the withholding certificate (receivables still count the full total).
  5. Advance-to-supplier offsets on a bill; bill price differences feeding back into lot cost.
  6. Purchase-side tax rules (bills and POs still use the typed percent); tax registers per authority and tax returns.
  7. E-invoicing (FBR digital invoicing for Pakistan).

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
7. Lot grouping rule (`lot_grouping`: MOULD or POUR): POUR makes one lot per pour and product, with every mould kept in `casting_lot_pour_mould_assembly`. The production queue moves ticked lots through several stages at once (`lots.bulk-stage`); packing and dispatch have an all-lots tick. Not done: a DAY grouping (needs several pours per lot) and QuickFlow pours still make no lots.
8. README is still the stock Laravel text.

## Gap list: Synchro ERP / B&L Odyssey parity

Estimate (2026-10-23, before Finance): about 60-70% of foundry-specific functionality, 20-30% of a full ERP. Since then invoicing, payables and the general ledger have been added. Based on general knowledge of both products, not checked against current vendor feature lists.

Covered: order to pour, metallurgy and thermal, moulding, sand, patterns, tooling, quality and CAPA, casting lots, packing and dispatch, HR and payroll, approvals, audit, module licensing and per-rule process depth.

In priority order:
1. **Stores/Inventory** (next in the module split): stock ledger, bins, issues, GRN, stock valuation.
2. **Costing** (`Costing` is empty): actual versus standard cost per casting (metal, energy, labour, fettling).
3. **MRP and scheduling** (`MRP`, `Production` are empty): material requirements planning, finite capacity scheduling.
4. **Finance** (in progress): invoicing, receivables, supplier bills, payables and the double-entry general ledger are in; still to do, in order: P&L and balance sheet, bank reconciliation, withheld tax settlement, supplier advances, purchase-side tax and returns, e-invoicing (list under Finance in Done).
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
  - Registers per authority, and FBR digital invoicing.

### Stores: not built yet
- Finished goods are not on the stock ledger. They need a decision on their stock lot (casting lot or a new stock lot) and a second registered item type.
- Cost layers still point at `material_lots`; the stock ledger refuses lots of other item types until that is designed. Stock is not posted to the general ledger either (periodic: purchases are expensed, inventory adjusted by journal).
- The unused `inventory_reservations` table has no screens.
- Existing lot stock has no location; it shows as "not put anywhere" until transferred.
- Movement types have no module hook yet; `StockMovementTypes::register()` is called directly.

### Menu
Quick flow sits above Production (module nav groups take `'position' => 'top'`). Melting holds heats and furnaces; materials, charge recipes, suppliers and stock screens are under Purchases. Finished goods, packing and dispatch are under Sales & Planning.
