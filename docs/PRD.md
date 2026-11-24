# Foundry ERP: product requirements

Updated 2026-11-22. This is the product-level view: what the product is, who it is for and where it is going. Detail lives elsewhere:

- [STATUS.md](STATUS.md): what is built, screen by screen.
- [PLAN.md](PLAN.md): open work items.
- [STREAMLINE.md](STREAMLINE.md): the small-foundry click path and process-depth rules.
- [STAGE2.md](STAGE2.md): order to pour, as built.
- [PPAP_APQP.md](PPAP_APQP.md): the automotive quality audit.

Status tags used below: **Built**, **Partial**, **Not started**.

---

## 1. Vision

An ERP for iron foundries that **shrinks to fit**. A foundry switches on only the modules it needs, and in each module it sets how much control it wants, one rule at a time. A one-person jobbing shop and a 200-person automotive supplier run the same product. The first goes from order to dispatch in a handful of screens. The second keeps every review, approval and record.

Every casting can be traced back to its heat, charge, pattern and order, and forward to the dispatch and invoice. That holds whatever depth the foundry has chosen.

## 2. Target customer

- **First market:** grey and ductile iron jobbing foundries in Pakistan, from about 5 to 200 staff, some of which also export.
- **Designed for small to large:** no customer size is built in. Every process difference between foundries is a setting, never a fork in the code.
- **Later:** other countries through tax and country packs, and other metals (see Non-goals).

## 3. Positioning

| | Foundry ERP | Synchro ERP, B&L Odyssey |
|---|---|---|
| Fit | Modules switched on one by one; each process step is a rule set to Off, Warn or Enforced | Full depth for everyone |
| Floor entry | Phone screens and QuickFlow grids built for the floor | Desktop-first |
| Traceability | Heat to lot to dispatch, at every depth | Yes |
| Local tax | Pakistan (FBR and provincial authorities) built in, as a country pack | Generic |

STATUS.md estimates parity at about 60-70% of foundry-specific function. The gap is mainly costing, MRP and scheduling, and reporting.

## 4. Users

| Role | Main jobs |
|---|---|
| Owner or MD | Order book, margins, cash, approvals |
| Sales and estimating | Enquiries, feasibility, quotations, orders, price lists |
| Planner | Production orders, moulding lists, melting plan, delivery promises |
| Melt shop and furnace operator | Heats, charges, recipes, thermal analysis, pouring |
| Moulding and core supervisor | Moulding runs, mould counts, cores and core boxes, sand |
| QC technician | Inspections, quick QC tally, NCRs, done on the phone (`/m`) |
| Stores | Receipts, issues, transfers, counts, reorder alerts |
| Purchasing | Approved suppliers, purchase orders, goods received |
| Accounts | Invoices, receipts, bills, payments, ledger, tax, bank |
| HR and payroll | Staff, attendance, payroll |

In a small foundry **one person holds most of these roles**. Flows must not force a hand-off to someone else. Notices skip the actor when the actor is also the receiver.

## 5. Product principles

1. **Modules, not tiers.** Every feature area is a module switched on or off on the Modules page (`ModuleRegistry`, `isEnabled()` is the single read point). Nobody is sold a tier, and every foundry uses only what it needs.
2. **Depth is configured per rule.** Inside a module, each check or step is its own setting in Settings (process-depth rules: Off / Warn / Enforced, or Skip / Required). The setup wizard's foundry sizes (Micro, Small, Full) and Basic and Full presets are **only shortcuts** that fill in suggested modules and rule values. Every value can be changed afterwards.
3. **Full first, Basic collapses it.** The full flow is built first. Basic collapses steps without creating a second set of documents, so there is one set of real records whatever the depth.
4. **The floor enters data where the work happens.** Large tap targets, grids prefilled from the plan, and about 30 seconds per heat or pour.
5. **Every page is Livewire.** Tabs, search, filters and paging change in place, with their state kept in the URL.

## 6. Success measures

| Measure | Target | Status |
|---|---|---|
| Steps from order to dispatch on Basic | 7 or fewer (pinned by `StepCountTest`) | Built |
| New foundry, from setup wizard to first invoice | Within one working day | Partial |
| Casting lot traced to its heat, charge and order | 2 clicks or fewer | Partial |
| Floor entry per heat or pour | About 30 seconds | Partial |

## 7. Module map

Dependencies show which modules must be on for another to work. Core is always on.

| Module | What it does | Depends on | Status |
|---|---|---|---|
| Core: products | Products, revisions, specifications, customer requirements, control plans | — | Built |
| Core: production orders and planner | Production orders, order planner, moulding lists | Sales | Built |
| Core: melting | Furnaces, iron grades, heats, charge recipes, thermal analysis, melting plan | — | Built |
| Core: moulding and pouring | Moulding runs, mould assemblies, pours, pouring gate, casting lots | Melting | Built |
| Core: quality | Inspections, quick QC, NCRs, lot queue, phone screens | — | Built (sand tests on phone and tensile not built) |
| Core: sand lab | Sand systems, tests, limits | — | Built |
| Parties | Customers and suppliers: country, currency, local or export | — | Built |
| Enquiry | Enquiries, feasibility, quotations with revisions and margin approval | Parties | Built |
| Sales | Sales orders, contract review, repeating lines, forecast, staggered deliveries, price lists, discounts | Parties | Built |
| QuickFlow | Quick order, moulds made and poured grids, Next heat, Today board | Sales, melting | Built |
| Development | New part development, from a won enquiry to an approved pattern | Enquiry, Tooling | Partial |
| Engineering | Bill of materials, level 1, with material cost per casting | — | Partial |
| Tooling | Patterns, core boxes, tooling life and maintenance | — | Built |
| Maintenance | Gauges and calibration | — | Built |
| Inventory (Stores) | Stock ledger, FIFO issues, transfers, store tree, counts, reorder alerts | — | Partial (finished goods not on the ledger; no issue or backflush to production) |
| Purchasing | Approved suppliers, purchase orders, goods received | Inventory | Built |
| Dispatch | Packing and dispatch of finished goods | Quality | Built |
| Finance | Invoices, credit notes, proformas, debit notes, receipts, supplier bills, payments, advances, tax certificates | Parties | Built |
| General ledger | Double entry, chart of accounts, journals, trial balance, P&L, balance sheet, bank reconciliation | Finance | Built |
| Tax | Country packs (Pakistan first): authorities, dated rates, rules, withholding | Finance | Partial (purchase-side rules, FBR digital invoicing) |
| Fixed assets | Asset register, depreciation, disposal | General ledger | Partial (phase 4 roll-forward to do) |
| CAPA | Corrective actions, 8D | Quality | Built |
| Documents | Controlled documents | — | Built |
| Compliance | Compliance records | — | Built |
| HR and payroll | Staff, attendance, payroll (Pakistan) | — | Built |
| PPAP / APQP | Phase gates, FMEA, MSA, capability, PPAP package for automotive suppliers | Development, Quality | Not started (building blocks exist; see PPAP_APQP.md) |
| Costing | Actual against standard cost per casting: metal, energy, labour, fettling | Inventory, Melting | Not started |
| MRP and scheduling | Material requirements, finite-capacity scheduling | Inventory, Sales | Not started (planner exists) |
| Outside processing | Subcontract flow, machining routings | Inventory, Purchasing | Not started (external store locations exist) |
| Reporting and BI | Management dashboards, customer scorecards, statutory exports | — | Not started |
| Licensing | Modules page, module hooks (nav, permissions, approvals, schedule, search, codes) | — | Built |

Known debt: core still calls some module classes directly (gauge calibration checks, tooling life panels). These calls move behind interfaces bound by modules, with `dependsOn()`.

## 8. Key flows

Each flow names its Basic shape (one person, steps collapsed) and its Full shape (every check on).

### 8.1 Enquiry to order
- **Full:** enquiry, then feasibility review, then a quotation. Its revisions are costed, and the margin is tested on the whole quotation; a thin one goes for approval. The quotation is sent, won, and becomes a sales order. Contract review follows with two signatures, from different users.
- **Basic:** quick order on one screen, saved straight to live with production released. Contract review passes automatically.

### 8.2 Order to pour (the floor day)
- **Full:** production order released (through approval if a policy is on), then the planner, work list, moulding run, mould assembly, melting plan, heat, pour, and casting lots.
- **Basic:** the moulding list is built automatically from the planner. The floor counts moulds made, pours them from the next heat and closes the heat. Lots are made at pour, one per mould or one per pour as the `lot_grouping` rule says.
- Gate mode (Advisory or Enforced) applies to both shapes.

### 8.3 Fettling and QC to dispatch
- **Full:** the lot queue moves lots through fettling and blasting stages in batches. Inspection is done by cavity mark, with an NCR on failure; a partial QC failure splits the lot. Packing, dispatch, then the invoice.
- **Basic:** a quick QC tally per product records good, rework and rejected against the oldest open lots, then pack and dispatch. The NCR threshold is a rule.

### 8.4 Purchase to stock
- **Full:** approved suppliers, purchase order with approval, goods received into a store location, a lot on the stock ledger, then issued with FIFO cost. Reorder notices go out when stock reaches its minimum.
- **Basic:** goods are received with no purchase order, and issues are recorded as they happen.
- **Not started:** issuing to production and backflushing against pours.

### 8.5 Invoice to ledger
- **Full:** an invoice from a dispatch or sales order (export customers' lines are zero-rated, in the customer's currency, with tax by rule), receipt into a named bank or cash account, matching advances, a tax certificate for tax withheld, and automatic ledger posting. Then bank reconciliation, with P&L and balance sheet.
- **Basic:** the same documents, with defaults filled in. The ledger can stay out of sight.

### 8.6 New part to approved pattern
- **Full:** a won enquiry opens a pattern development for each part. Pattern bills are treated as development cost. Trial castings and customer approval follow, and the product revision is approved for production. With the PPAP / APQP module, phase gates and the PPAP package come on top.
- **Basic:** the pattern is recorded and the product revision marked approved.

## 9. Platform and non-functional requirements

| Area | Requirement | Status |
|---|---|---|
| Hosting | SaaS only, hosted by us. One instance and one database per customer. No on-premise install for now. | Partial (provisioning not scripted) |
| Portability | No hard-coded file paths, cache keys or hostnames; everything through config, so moving later to one app with a database per tenant stays cheap. | Partial |
| Audit | Every record change keeps who and when. | Built |
| Access | Roles and permissions; approvals through `ApprovalGate` by policy. | Built |
| Backups | Daily, kept 30 days, each customer restorable on its own. | Not started |
| Uptime | 99.5%. | Not started |
| Speed | Lists and pages respond in under 1 s with about 5 years of a mid-size foundry's data. | Not measured |
| Phone | PWA under `/m`, Android first. Capacitor wrapper only if hardware (Bluetooth scales, spectrometers, printers) needs native access. iOS is best-effort. | Partial |
| Offline | Floor and phone entries queue offline through the service worker and sync when the connection is back, to cope with load-shedding and weak signal. | Not started |
| Language | English only, ready for translation: every user-facing string through `__()` and lang files, dates, numbers and currency formatted per locale, layouts survive strings 30% longer. Right-to-left (Urdu) not required yet. | Partial |
| Numbers and codes | Each document and master has a short prefix and five digits from 10000, one series per type. | Built |
| Quality system | ISO 9001 readiness holds whichever modules are on. The gaps in `iso-9001-readiness.pdf` are to be tracked against the code. | Partial |

## 10. Onboarding

- **Setup wizard:** picks a foundry size as a starting point, which switches on suggested modules and rule values.
- **CSV import:** customers, products, patterns, materials and opening stock (data import exists).
- **Target:** first invoice within one working day of sign-up.

Status: **Partial.** The wizard and import exist, but the one-day path has not been tested end to end.

## 11. Commercial model

- Sold as a hosted subscription. There are **no tiers**.
- A foundry switches on the modules it uses and sets each module's depth in Settings.
- The Modules page is self-contained so it can later move out of the product into a licensing service.
- This document sets no prices.

## 12. Non-goals (for now)

- Casting simulation or CAD.
- Steel and non-ferrous metallurgy.
- Multi-company or group consolidation.
- A customer web portal or e-commerce.
- Payroll rules for countries other than Pakistan.
- Shared-row multi-tenancy (one database with a tenant column).
- On-premise installation.
- Right-to-left languages.

## 13. Roadmap

In order:

1. **Finish Stores:**
   - finished goods on the stock ledger
   - issue and backflush to production
   - cost layers for every item type
2. **Costing:** actual against standard cost per casting (metal, energy, labour, fettling), rolled up from the BOM and the ledger.
3. **Finish Finance:**
   - fixed assets phase 4
   - settling withheld tax in receivables
   - purchase-side tax rules
4. **MRP and scheduling:** material requirements and finite-capacity scheduling, built on the planner.
5. **Outside processing and machining routings.**
6. **Reporting and BI:** management dashboards, customer scorecards, statutory exports.
7. **Integrations:** FBR digital invoicing, EDI, barcodes and devices.
8. **Platform work, alongside:**
   - instance provisioning and backups
   - offline floor entry
   - translation readiness
   - PPAP / APQP module
   - module interfaces with `dependsOn()`
   - tracking the ISO 9001 gaps against the code
