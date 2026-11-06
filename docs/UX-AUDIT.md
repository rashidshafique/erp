# UX current-state audit (U0a)

2026-11-06. Method: 330 distinct GET routes (`route:list`), `STATUS.md`, `RoleHome`, the 14 seeded roles, and the earlier `docs/design/26-10-workflow/Design Audit.md` (28 Oct). Screens were not opened in a browser, so "built" means a route and view exist, not that the screen is good. Verdicts: **Keep** (fits the plan), **Reshape** (built, needs a new flow or role view), **New** (nothing built).

## 1. Headline findings

1. **Most of the 28 Oct audit's "missing: in repo, no screen" list is now a design gap, not a code gap.** Lots and queue, purchase orders and receipts, stock locations and movements, supplier payments, ledger (P&L, balance sheet, trial balance, banks), HR payroll, maintenance, approvals, users and roles, imports, settings pages and rules all have routes. What lacks is a designed flow, not a built screen.
2. **A role home already exists**: `RoleHome` builds tile sections from the user's permissions (`/home`). Plan gap 2 ("no role-based home") is partly wrong. The real gap is that it is counts-only tiles, with no "what is due now" ordering, and the Today board (`quick.today`) is a separate, Basic-flow-only start page.
3. **14 roles are seeded**: ADMIN, SALES, PRODUCTION_MANAGER, MELT_SUPERVISOR, MOULDING_SUPERVISOR, QUALITY_INSPECTOR, QUALITY_MANAGER, STORES_DISPATCH, VIEWER, HR_MANAGER, ACCOUNTS, BUYER, QA_TEST_TECH, QA_DEFECT_TECH. There is **no PLANNER and no OWNER role**, yet planner and owner are the first and third design priorities. Decide whether they are new roles or map to PRODUCTION_MANAGER and ADMIN.
4. **Mobile covers only the quality tech** (`/m`: home, scan, thermal, spectro, hardness, defects). Moulding, furnace, packing and dispatch have no phone/tablet screens; they use desktop pages or `quick/*`.
5. **Not built at all**: notifications (no route), global search (only scan suggest), customer portal, returns and complaints flow, subcontract/machining, costing, PPAP elements/PSW, shift handover, attendance self-service on phone.
6. **Two parallel floor paths**: Quick (`quick/*`, `mould-count`, `quick-qc`) and full pages (`moulding-runs`, `pours`, `heats`, `work-lists`, `heat-plan`). A rule picks which the menu shows. Sketches must show both or the design will fit only one.

## 2. Flow to screen map

| Flow | Built screens | Verdict | Notes for the sketch |
|---|---|---|---|
| F1 Order to dispatch, Basic | `quick/orders/new`, `quick/today`, `quick/floor`, `quick/next-heat`, `quick-qc`, `packing`, `dispatches` | **Keep**, **Reshape** Today board | Pinned at 7 steps. Today board needs role versions. |
| F2 Real floor day | `planner`, `work-lists`, `moulding-runs`, `mould-count`, `heat-plan`, `heats`, `pours`, `lots/queue`, `quick-qc`, `packing`, `dispatches` | **Reshape** | 14 steps over 11 desktop pages; no tablet versions. |
| F3 Repeat order | `sales-orders` (form with repeat/forecast) | **Keep** | Behaviour is heavy (3× confirm, floors). Check form clarity. |
| F4 Thermal test (phone) | `m`, `m/tests/thermal/*` | **Keep** | Designed (26a/26i). Open: live curve, History, tensile. |
| F5 Defect | `m/defects`, `defects`, `ncrs`, `corrective-actions` | **Keep** phone, **Reshape** NCR/CAPA | |
| F6 Heat gone wrong | `heats/{x}`, `heats/{x}/summary`, pour gate | **Reshape** | No single "hold and decide" path. |
| F7 Stock and reorder | `stock-reorder`, `materials`, `purchase-orders`, `receipts`, `stock-*`, `stock-locations` | **Reshape** | Built piecemeal in Stores; no end-to-end flow. |
| F8 Enquiry to order | `enquiries`, `quotations`, `sales-orders`, `feasibility` | **Keep** | Paused work. Customer-facing side missing. |
| F9 Audit/trace | `lots/{x}`, `lots/{x}/traveller`, `certificate`, `scan`, `audit`, `documents` | **Reshape** | No one search box from a piece ID to heat. |
| F10 Setup | `settings/how-you-work`, `process-depth`, `rules/*`, `modules`, `imports` | **Reshape** | 35 settings routes; wizard exists, first-run empty states unchecked. |
| Owner check-in | `home`, `reports/yield`, `dashboard` | **New** (as designed) | No on-time, scrap-by-cavity, cost-per-heat views. |
| Approvals | `approvals` | **Reshape** | Inbox exists; not surfaced from home or phone. |
| Purchase to pay | `purchase-orders`, `receipts`, `bills`, `supplier-payments`, `payables` | **Keep**, **Reshape** | Linear in code; sketch end to end. |
| Month-end | `ledger/*`, `tax-register`, `tax-return`, `invoices`, `receivables` | **Reshape** | No period-close flow. |
| Maintenance breakdown | `maintenance`, `tooling-maintenance`, `gauges` | **Reshape** | Not tied to a running heat. |
| HR / attendance | `hr/*` (29 routes), `leave`, `payslips` | **Keep** | Employee self-service on phone: **New**. |
| New part to PPAP | `developments`, `feasibility`, `patterns`, `core-boxes`, `products` | **Reshape** | Docs in `PPAP_APQP.md`; no PPAP elements/PSW screen. |
| Return / complaint / credit note | `invoices/{x}/credit`, `ncrs` | **New** as a flow | Credit note exists; the return path does not. |
| Notifications, search, portal, subcontract, costing, shift handover | none | **New** | |

## 3. Persona to screen coverage

| Persona | Role in code | Own screens today | Gap |
|---|---|---|---|
| P3 Planner | none (PRODUCTION_MANAGER?) | `planner`, `work-lists`, `heat-plan`, `quick/today` | No planner home; evening list flow spans 4 pages. |
| P4 Moulding supervisor | MOULDING_SUPERVISOR | `moulding-runs`, `quick/floor`, `mould-count`, `mould-readiness`, `moulding-teams` | No tablet design. Count moulds is a desktop page. |
| P1 Owner | none (ADMIN?) | `home` tiles, `reports/yield` | No owner questions answered. |
| P5 Furnace operator | MELT_SUPERVISOR | `heats`, `quick/next-heat`, `furnaces`, `charge-recipes` | No tablet design; recipe charge reminders are on the heat page. |
| P6 Lab tech | QA_TEST_TECH, QA_DEFECT_TECH | `/m` | Done. |
| P8 Quality | QUALITY_INSPECTOR, QUALITY_MANAGER | `quality-home`, `quick-qc`, `lots`, `ncrs`, `inspections` | Fragmented across 5 areas. |
| P9 Stores/buyer | STORES_DISPATCH, BUYER | many | Needs a role home. |
| P10 Dispatch | STORES_DISPATCH | `packing`, `dispatches` | Tablet design. |
| P11 Accountant | ACCOUNTS | `ledger/*`, `invoices` | Dense tables; check patterns. |
| HR | HR_MANAGER | `hr/*` | Strong; phone self-service missing. |
| Sales | SALES | orders, quotations, customers | Strong. |
| Admin | ADMIN | settings, roles, users, imports | Settings sprawl (35 routes). |

## 4. Conflicts still open from the 28 Oct audit

- Currency and tax: design USD-neutral, repo Pakistan first.
- Type: Helvetica in the design, Instrument Sans in the app.
- Record codes differ (`INV-26-1188` vs `INV-<year>-0001`; `WO-` vs production order).
- Simple mode toggle vs per-rule process depth.
These need a one-line decision each before the next Claude Design pass.

## 5. Consequences for the plan

1. **Planner and owner roles**: decide to add `PLANNER` and `OWNER` roles (or map them) before designing their homes.
2. **Scope of sketches (U1)**: planner, moulding, owner and furnace are all *reshape* work on built screens, so the brief must attach screenshots or route names of the current screens, not start blank.
3. **Reuse**: `RoleHome` tiles and Today board should be merged into one role-aware "due now" home; sketch that, not a new dashboard.
4. **Tablet is the biggest hole** on the floor; phone is covered only for quality.
5. **Never designed or built, so cheapest to sketch first**: notifications, search, shift handover, returns. These add value across all personas.
6. **Carry-over from the 28 Oct list**: lot detail, process depth settings and approvals inbox are now built; drop them from the "next screens" list. Remaining design backlog: cavity map, supplier bills end to end, maintenance, HR self-service, mobile lot stage scan and pour gate.

## 6. Next step

U0b: role × action permissions grid and owner questions, starting from the 14 existing roles.
