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
7. README is still the stock Laravel text.
