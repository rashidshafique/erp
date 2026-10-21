# Plan

Updated 2026-10-21. Module by module: what is done, what remains. Modules in `app/Domains/<Name>/` are self-contained; the production spine (melting, pouring, moulding, sand, quality, products) is still in `app/` and the matching `app/Domains` folders are empty placeholders.

## Core spine (in `app/`)

| Area | Done | Remaining |
|---|---|---|
| Sales and planning | Customers (quick add), sales orders, contract review, production orders, melting plan | Sales split into its own module; customers stay core master data |
| Products and patterns | Products, revisions, specifications, patterns, revisions, cavity map, ownership, core boxes, preferred station | Move to a Product module (placeholder empty) |
| Melting | Furnaces, iron grades, heats, readings, chemistry (import and verify), charge batches, material consumption, cupola charge rounds | Cupola work uncommitted; hold, void and heat lights need tests run and commit |
| Thermal analysis | Curve analyser, TL/TE/recalescence, CE, calibration, void, CE source, cup timing | Same uncommitted batch |
| Moulding | Runs (bulk, record screen), stations, teams, flasks, work lists, mould readiness, mould hardness, flaskless auto-assembly | Move to a Moulding module |
| Sand | Sand systems, tests, limits, base sand, sand lab tables | Move to a Sand module |
| Pouring | Pour page, thermal gate, mould assemblies attached to a pour, rules | Move to a Pouring module |
| Quality | Casting lots and stage events, inspections, inspection plans, NCRs, defects, certificates, casting analysis | Move to a Quality module; ISO 9001 gaps not tracked |
| Stores | Materials, material lots, stock movements, report, reconciliation, cost layers | Next module split (Inventory); MRP and Costing placeholders empty |
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
| Purchasing | Suppliers, evaluation, incoming inspection and gate | None open |
| Documents | Controlled documents, revisions, approval workflow | None open |
| Compliance | Requirement profiles, customer requirements | None open |
| Parties | Shared party record | None open |
| QuickFlow | Quick order, floor entry, next heat | None open |
| Commercial, Costing, Engineering, Inventory, MRP | Empty placeholders | Build or remove; Inventory is next |

## Order of work
1. Run tests on the uncommitted cupola/thermal batch and commit.
2. Inventory (stores) module split, then Sales split.
3. Replace direct core-to-module calls with bound interfaces and `dependsOn()`.
4. Fill or remove empty placeholder modules.
5. Track ISO 9001 readiness gaps against the code.
