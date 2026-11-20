# Design audit: screens vs codebase

Compared `Foundry ERP.dc.html` (T1–T6) with `rashidshafique/erp@master` (docs/STATUS.md, routes, views, rules). 28 Oct 2026.

## Done: repo feature has a screen

| Feature in repo | Screen |
|---|---|
| Enquiry and quotation | 1b enquiry, 1d quote, 2b cost stack, 2d simple quote |
| Customers, part master, product revisions | 3a part master |
| Sales order, contract review, release | 1e sales order, 6a order (review + release in one) |
| Production orders | folded into 6a (progress on order line) |
| Planner, work lists, melting plan | 1g planning, 6b plan (one screen) |
| Moulding runs | 6c moulding run |
| Pouring gate | 6d pour |
| Heats, chemistry, treatment, fade | 1h heat log, 1m melt deck mobile |
| Thermal analysis | 3o web, 3p mobile |
| Charge batches / charge mix | 3g charge calculator |
| Sand lab, sand systems | 3d sand lab, 3m mobile, 4b sand vs defects |
| Patterns, ownership, life | 1f register, 3n mould kit |
| Core boxes, core lots | 3c core shop, 3n mould kit |
| Inspections, NCR, CAPA | 1i inspection, 1l ISO register, 3i 8D, 5a–5d defects |
| Control plan, PFMEA | 3j |
| Gauges and calibration | 3h |
| Packing and dispatch | 1j dispatch, 1m scan mobile |
| Sales invoices, receipts, ageing | 3l invoicing, 4c simple invoicing |
| Dashboard, reports | 1a dashboard, 3k reports, 4a owner home |
| Basic mode (process depth) | partial: simple mode toggle in 1a, 2d, 4c |

## Partial: screen exists but misses repo behaviour

- **Cavity map / family patterns.** Repo maps each cavity to a product revision (whole or part, closed, weights, casting levels). No screen shows the map; 6b treats one block as one order; 6c records moulds, not good castings per product.
- **Casting lots.** Lots appear as codes in 6b–6d, but there is no lot screen (stages, holds, waivers, readiness checks from `QualityGates::lotReadiness`).
- **Lot grouping rule** (one lot per mould or per pour): not shown anywhere.
- **First-iron hold and containment** (`PouringRules`): no hold state on a lot or pour.
- **Repeating and staggered order lines.** 1e shows call-offs; the repo's repeat-ahead, forecast change and 3× confirm are not designed.
- **Iron grades.** Windows are referenced in 1h, 3o, 6d but there is no grade screen (chemistry/CE/temperature windows, standard equivalents, per-furnace overrides, thermal windows).
- **Process depth.** "Simple mode" toggle exists; the repo's per-rule settings (11 rules, Basic/Full/Custom) have no screen.
- **Quick QC tally** (`/quick-qc`): 3m has a fettling count, but not the tally over open lots with the NCR threshold.
- **Bill of materials.** Shown only as a side panel idea; no BOM editor on the product.
- **Machining / subcontract.** 3f is designed; repo lists outside processing as not built.

## Missing: in repo, no screen

Production and metallurgy
- Casting lot detail and production queue (bulk stage moves)
- Iron grade detail
- Furnaces and furnace grade capability
- Charge recipes (cupola auto charge, skipped charges)
- Flasks and flask sizes
- Moulding teams, stations
- Mould readiness
- Heat calendar / melting calendar
- Labels and scan (barcode)
- Casting analyses

Purchasing and stores
- Purchase order, approval, receiving into quarantine lots
- Stock locations, stock by location, movements (issue, scrap, count, transfer)
- Stock report, stock reconciliation
- Reorder alerts
- Materials and material lots

Finance
- Supplier bills, 3-way match, supplier payments, payables ageing
- Credit notes, customer statement
- General ledger: chart of accounts, journals, trial balance
- P&L, balance sheet
- Bank accounts and reconciliation
- Tax register, taxation settings

Platform and other modules
- Approvals inbox (only owner approvals on 1m mobile)
- HR and payroll
- Maintenance plans, work orders, breakdowns, meters
- Tooling life (beyond patterns)
- Controlled documents (listed in 1l only)
- Attachments, audit log, custom fields, data import, roles and users, notifications
- Settings pages (order rules, pouring rules, quality rules, modules on/off)

## Design-only: screen with no repo feature yet

- 1c feasibility review (cross-department sign-off)
- 1k / 2c PPAP elements and PSW
- 2a job timeline spine
- 3b methoding review and freeze
- 3e fettling WIP by operation
- 3f machining and subcontract
- 3k / 4a owner reports with margin and cost of poor quality (repo has no costing)
- 5b mould leak and run-out log
- 5c cause analysis fishbone
- 5a defect atlas with live cause checks (repo has a defects list only)

## Conflicts to decide

- **Currency and tax.** Design is USD only, neutral; repo is Pakistan first (FBR, SRB, PRA, PCT codes, withholding). Pick one for finance screens.
- **Type.** Design uses Helvetica; repo Tailwind theme sets Instrument Sans.
- **Record codes.** Design `INV-26-1188`, repo `INV-<year>-0001`; design `WO-` for production orders, repo calls them production orders. Align before handoff.
- **Simple mode.** Design shows one toggle; repo has rule-by-rule process depth. Decide whether the toggle sets the Basic preset.

## Suggested next screens, in order

1. Casting lot (stages, holds, readiness, trace)
2. Cavity map on pattern + family-pattern handling on 6b/6c
3. Purchase order + receiving, stores by location
4. Supplier bills and payments
5. Approvals inbox
6. Process depth settings
7. Iron grade, furnaces and charge recipes
8. Ledger, P&L / balance sheet, bank reconciliation
9. Maintenance, HR and payroll
10. Mobile: lot stage scan, quick QC tally, pour gate
