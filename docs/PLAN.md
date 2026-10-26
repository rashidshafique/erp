# Plan

Updated 2026-10-26. Module by module: what is done, what remains. Modules in `app/Domains/<Name>/` are self-contained; the production spine (melting, pouring, moulding, sand, quality, products) is still in `app/` and the matching `app/Domains` folders are empty placeholders.

## Core spine (in `app/`)

| Area | Done | Remaining |
|---|---|---|
| Sales and planning | Customers (quick add), production orders, melting plan, planner | Sales orders and contract review are in the Sales module; customers, production orders and melting plan stay core |
| Products and patterns | Products, revisions, specifications, patterns, revisions, cavity map, ownership, core boxes, preferred station | Move to a Product module (placeholder empty) |
| Melting | Furnaces, iron grades, heats, readings, chemistry (import and verify), charge batches, material consumption, cupola charge rounds | Cupola work uncommitted; hold, void and heat lights need tests run and commit |
| Thermal analysis | Curve analyser, TL/TE/recalescence, CE, calibration, void, CE source, cup timing | Same uncommitted batch |
| Moulding | Runs (bulk, record screen), stations, teams, flasks, work lists, mould readiness, mould hardness, flaskless auto-assembly | Move to a Moulding module |
| Sand | Sand systems, tests, limits, base sand, sand lab tables | Move to a Sand module |
| Pouring | Pour page, thermal gate, mould assemblies attached to a pour, rules | Move to a Pouring module |
| Quality | Casting lots and stage events, inspections, inspection plans, NCRs, defects, certificates, casting analysis | Move to a Quality module; ISO 9001 gaps not tracked |
| Stores | Materials, material lots, cost layers | Stock ledger, locations and movements moved to the Inventory module; finished goods not on the stock ledger yet |
| Traceability | Order trace to dispatch, labels, barcode scan | Placeholder module empty |
| Platform | Approvals, attachments, audit, custom fields, codes, imports, roles, users, branding, search | None planned |

## Modules (in `app/Domains/`)

| Module | Done | Remaining |
|---|---|---|
| Core, Licensing | Module registry, hooks, access contract, settings page | Settings page is detachable; keeps growing for licences |
| Maintenance | Gauges, calibration, rules | Interface instead of direct core calls |
| Tooling | Tool marks, maintenance, life limits, dashboards | Interface instead of direct core calls |
| Hr | Employees, attendance, leave, roster, payroll, payslips, advances, settlements, statutory, org chart, default shift | None open |
| Capa | CAPA register and actions | None open |
| Dispatch | Dispatches, packing, finished goods, shipment service | None open |
| Enquiry | Enquiries, request wizard, quotations, rates | None open |
| Purchasing | Suppliers, evaluation, incoming inspection and gate, purchase orders (issue, approval, receive into quarantine lots), reorder alerts | None open |
| Sales | Sales orders, contract review, staggered deliveries, repeating lines (weekly or monthly) | Paused for Finance: forecast grid per period, shortfall carry-over, one production order per period, standing and blanket orders |
| Inventory | Stock ledger (receipts, FIFO issues, transfers), locations, movements, stock by location | Finished goods, reservations screens, movement-type hook |
| Engineering | Bill of materials level 1 (per casting, per mould, per kg poured), need against stock on production orders | Contract review check, scrap allowance, backflush, cores, costing roll-up |
| Finance | Sales invoices and credit notes, receipts, receivables ageing and statements, supplier bills and credit notes with 3-way match, supplier payments, payables ageing | Withheld tax settlement, supplier advances on bills, bill price differences into lot cost, purchase-side tax rules |
| Ledger | Double-entry general ledger: seeded chart of accounts with sub-accounts, automatic journals from every Finance document and reversals on void, manual journals, trial balance, account ledger, closed-to date | P&L and balance sheet next, then bank reconciliation |
| Tax | Pakistan pack: authorities, dated rates, rules, party tax profiles, issue checks, invoice taxes and withholding | Registers per authority, returns, FBR digital invoicing |
| Documents | Controlled documents, revisions, approval workflow | None open |
| Compliance | Requirement profiles, customer requirements | None open |
| Parties | Shared party record | None open |
| QuickFlow | Quick order, floor entry, next heat | None open |
| Commercial, Costing, MRP and the production placeholders | Empty placeholders | Build or remove |

## Order of work
1. Run tests on the uncommitted cupola/thermal batch and commit.
2. Finance to completion (now): P&L and balance sheet, bank reconciliation, withheld tax, supplier advances, purchase tax and returns, e-invoicing. Then back to repeating orders in Sales.
3. Replace direct core-to-module calls with bound interfaces and `dependsOn()`.
4. Fill or remove empty placeholder modules.
5. Track ISO 9001 readiness gaps against the code.
