# Plan

Updated 2026-11-02. Module by module: what is done, what remains. Modules in `app/Domains/<Name>/` are self-contained; the production spine (melting, pouring, moulding, sand, quality, products) is still in `app/`. The empty placeholder modules (Commercial, Costing, MRP and the production ones) have been removed.

## Core spine (in `app/`)

| Area | Done | Remaining |
|---|---|---|
| Sales and planning | Customers (prospects, on hold, credit panel), production orders, melting plan, planner, daily moulding list prepared from open orders | Planner: working-day calendar, per-pattern rates, downstream stage capacity; 15a production board not built (Today board stands in) |
| Products and patterns | Products list, page and edit to design 10a–10g, revisions with draft and status, per-product routing, specifications, patterns, cavity map, ownership, core boxes with custody, preferred station, bulk pattern linking | Move to a Product module |
| Melting | Furnaces, iron grades, heats, readings, chemistry, charge batches, editable charge recipes with roles, cupola charge rounds, close heat with leftover metal | Move to a Melting module |
| Thermal analysis | Curve analyser, TL/TE/recalescence, CE, calibration, void, CE source, cup timing, edit from the samples panel | None open |
| Moulding | Runs (bulk, record screen), stations, teams, flasks, work lists, mould readiness, mould hardness, automatic assembly, mould count with write-off | Move to a Moulding module |
| Sand | Sand systems, tests, limits, base sand, sand lab tables | Move to a Sand module |
| Pouring | Pour page, thermal gate, one-step pours that make lots, mould assemblies attached to a pour, rules | Move to a Pouring module |
| Quality | Casting lots and stage events, batch moves after the pour, lot grouping (mould or pour), inspections, NCRs, defects by cavity, certificates, casting analysis, feasibility review | DAY lot grouping; lot screens to design 16a–16c not checked; move to a Quality module; ISO 9001 gaps not tracked |
| Stores | Materials, material lots, cost layers, billed price into lot cost | Finished goods not on the stock ledger; cost layers still tied to `material_lots` |
| Traceability | Order trace to dispatch, labels, barcode scan | None planned |
| Process depth | Per-rule settings in sections, Basic/Full/Custom, simple mode switch, "How you work" questions, rule-driven menu, step-count test | Setup wizard (STREAMLINE phase 7) beyond the questions page |
| Platform | Approvals, attachments, audit, custom fields, codes, imports, roles, users, branding, search, notifications, shell and menu to design 1a | None planned |

## Modules (in `app/Domains/`)

| Module | Done | Remaining |
|---|---|---|
| Core, Licensing | Module registry, hooks, access contract, settings page, `CreditControl` contract | Settings page is detachable; keeps growing for licences |
| Maintenance | Gauges, calibration, rules | Interface instead of direct core calls |
| Tooling | Tool marks, maintenance, life limits, dashboards, core lots | Interface instead of direct core calls |
| Development | Pattern developments from enquiries: types, stages, send back, several tools per development with gate, shared tools, register records (design 11, 12, 18) | None open |
| Hr | Employees, attendance, leave, roster, payroll, payslips, advances, settlements, statutory, org chart, default shift | None open |
| Capa | CAPA register and actions | None open |
| Dispatch | Dispatches, packing, finished goods, shipment service, credit check | None open |
| Enquiry | Intake (design 1b), list and record (14a–14c), quotations, rates, close dialog, prospects | Quotations do not use price lists |
| Purchasing | Suppliers, evaluation, incoming inspection and gate, purchase orders (issue, approval, receive into quarantine lots), reorder alerts | None open |
| Sales | Sales orders, contract review (auto, credit, price checks), repeating lines with forecast, staggered deliveries, sold by pcs or kg, price lists, line discounts | Shortfall carried to the next period, one production order per period, standing and blanket orders; discount as an amount or on the whole order |
| Inventory | Stock ledger (receipts, FIFO issues, transfers), store tree, movements, stock by location | Reservations screens, movement-type hook |
| Engineering | Bill of materials level 1, need against stock on production orders | Contract review check, scrap allowance, backflush, cores as made items, costing roll-up |
| Finance | Invoices and credit notes (products on lines, charges and deductions, layouts and template builder), receipts, ageing, statements, credit limits, advances, tax certificates, supplier bills with 3-way match and withholding, supplier payments, payables, tax register and return, Tax & year end settings | FBR digital invoicing; tax periods |
| Ledger | Double entry with the client's chart (category, section, account), vouchers (JV, CPV, BPV, CRV, BRV), day book, voucher print, ledger statement, trial balance, P&L, balance sheet, opening balances at go-live, posting rules, bank accounts and reconciliation, advances kept apart | Posting stock, cost of sales, goods receipt, heat close, returns, scrap, tooling and payroll; year-end close entry; bank statement import |
| Tax | Pakistan pack: authorities, dated rates, rules, party tax profiles, issue checks, sales and purchase taxes, withholding agent | FBR digital invoicing; credit notes taxed at the original invoice's rules; s.153 and sales tax on advances wait for the tax adviser |
| Documents | Controlled documents, revisions, approval workflow | None open |
| Compliance | Requirement profiles, customer requirements | None open |
| Parties | Shared party record | None open |
| QuickFlow | Quick order, Today board, quick floor screens, quick pours, Quick QC | None open |
| Cameras | Camera links under Settings › Foundry | None open |

## Not started
- Costing: actual against standard cost per casting.
- MRP and finite scheduling.
- Outside processing and machining routings.
- Reporting and BI, statutory exports.

## Order of work
1. Ledger: post stock and cost of sales (the greyed rows on posting rules), then the year-end close entry.
2. Sales: the rest of repeating orders (shortfall carry-over, one production order per period, standing and blanket orders).
3. FBR digital invoicing once credentials are available.
4. Replace direct core-to-module calls with bound interfaces and `dependsOn()`.
5. Track ISO 9001 readiness gaps against the code.
