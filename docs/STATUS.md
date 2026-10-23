# Project status

Updated 2026-10-21. 132 commits, 105 feature test files, 52 uncommitted files from the in-progress cupola/thermal work.

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

## In progress (uncommitted)
- Cupola charge rounds (`HeatChargeRoundController`, `ChargeRecorder`, `CupolaChargeRoundsTest`).
- Thermal tests: void, CE source, cup timing and sampling.
- Heat hold and heat-light indicator.
- Customer quick-add; pattern link filter; optional mould assembly configuration.

These need their tests run and a commit.

## Missing / next
1. HR is done; next in the module split: Stores/materials (Inventory), then Sales split. Melting, pouring and moulding stay in core.
2. Empty module folders still to fill or remove: Commercial, Costing, Engineering, Inventory, MRP, Melting, Moulding, Pouring, Product, Production, Quality, Sand, Traceability.
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
4. **Finance** (biggest gap): general ledger, AR/AP, invoicing, tax, bank reconciliation. Decide build a thin version or integrate an accounting package.
5. **Outside processing and machining routings**: subcontract flow, machining steps.
6. **Reporting and BI**: management dashboards, customer scorecards, statutory report exports.
7. **Integration**: EDI, e-invoicing and tax filing for the target market, barcode and device input.
8. **Housekeeping**: track the ISO 9001 gaps against the code, replace direct core-to-module calls with interfaces and `dependsOn()`, write the README.
