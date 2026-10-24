# Project status

Updated 2026-10-23.

## Done

**Order to pour (Stage 2, see STAGE2.md)**
- Customers, sales orders, contract review, production orders, melting plan, iron grades, thermal analysis, pouring gate, trace to dispatch.

**Production**
- Moulding runs: bulk entry, three-step record screen, KPI strip, mould hardness, stations, sand test from a run.
- Work lists: planned items, runs matched to the list, draft-list start setting, remake shortfall added in one click.
- Flaskless moulds assembled automatically when output is recorded.
- Pouring, heat timeline and readings, charge batches, material consumption.
- Sand lab: tests, limits, sand systems, sortable and filterable tables.
- Patterns: revisions, cavity map, ownership, preferred station.

**Domain modules (`app/Domains`)**
- Maintenance (gauges, calibration), Tooling, HR and payroll, CAPA, Dispatch and packing (finished goods), Enquiry and quotations, Purchasing, Documents, Compliance, Parties, QuickFlow.
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
- Not built: bill price differences feeding back into lot cost, advance-to-supplier offsets on a bill, general ledger and posting, bank reconciliation, financial reports, tax returns, e-invoicing. Order agreed: ledger next.

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

Estimate (2026-10-23): about 60-70% of foundry-specific functionality, 20-30% of a full ERP. Based on general knowledge of both products, not checked against current vendor feature lists.

Covered: order to pour, metallurgy and thermal, moulding, sand, patterns, tooling, quality and CAPA, casting lots, packing and dispatch, HR and payroll, approvals, audit, module licensing and per-rule process depth.

In priority order:
1. **Stores/Inventory** (next in the module split): stock ledger, bins, issues, GRN, stock valuation.
2. **Costing** (`Costing` is empty): actual versus standard cost per casting (metal, energy, labour, fettling).
3. **MRP and scheduling** (`MRP`, `Production` are empty): material requirements planning, finite capacity scheduling.
4. **Finance** (biggest gap): invoicing and receivables are in; still to do: AP and supplier bills, general ledger, tax returns, bank reconciliation. Decide build a thin version or integrate an accounting package.
5. **Outside processing and machining routings**: subcontract flow, machining steps.
6. **Reporting and BI**: management dashboards, customer scorecards, statutory report exports.
7. **Integration**: EDI, e-invoicing and tax filing for the target market, barcode and device input.
8. **Housekeeping**: track the ISO 9001 gaps against the code, replace direct core-to-module calls with interfaces and `dependsOn()`, write the README.

**Notifications and reorder alerts**
- `Notify` sends an `AppNotice` to everyone holding a permission, or to named roles; stored with Laravel's database notifications. The bell and growl-style toasts in the top bar (`notification-center`, Livewire) poll every 10 seconds. Websockets were not used: they need Reverb or Pusher and a socket server.
- `ReorderWatcher` raises one notice to `stores.manage` holders each time a watched material (tracked, active, minimum above zero) falls to its minimum, and re-arms when stock is back above it. It runs after every ledger receipt or issue, when a material is edited, and hourly (`stock:check-reorder`). The Reorder alerts page (Purchases) and a dashboard "Needs attention" item list the materials with a suggested order quantity.

### Stores: not built yet
- Finished goods are not on the ledger. They need a decision on their stock lot (casting lot or a new stock lot) and a second registered item type.
- Cost layers still point at `material_lots`; the ledger refuses lots of other item types until that is designed.
- The unused `inventory_reservations` table has no screens.
- Existing lot stock has no location; it shows as "not put anywhere" until transferred.
- Movement types have no module hook yet; `StockMovementTypes::register()` is called directly.

### Menu
Quick flow sits above Production (module nav groups take `'position' => 'top'`). Melting holds heats and furnaces; materials, charge recipes, suppliers and stock screens are under Purchases. Finished goods, packing and dispatch are under Sales & Planning.
