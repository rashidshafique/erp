# UX plan: personas, flows, phases

Drafted 2026-11-06 from STATUS.md, STREAMLINE.md and the floor-flow decisions. UI-only: no code until a Claude Design export is back (see "Design before build" memory).

## 1. Principles

1. **Few screens by default, depth by rule.** Basic shows 7 steps order to dispatch, the real floor day 14. Every extra check or step is a per-rule setting, never a fork of the UI.
2. **Design for small to large.** The same screen works for a 5-person foundry and a 200-person one; size changes what is shown, not the layout.
3. **Start from "what is due now".** Each role lands on a list of work (Today board, Tests due, Moulds to pour), not on a module menu.
4. **Floor = phone/tablet, office = desktop.** Floor roles get large tap targets, numeric keypads, one task per screen, and a bottom gap of at least 28px (`--nav`) for Android buttons.
5. **Enter once, trace forever.** Cavity mark, heat, lot and piece IDs are captured at the point of work, so trace to dispatch needs no re-keying.
6. **Mistakes are fixable in place.** Invalid test, written-off moulds, undo a count: always with a reason, never a dead end.
7. **Every number has a next action.** A red countdown, shortage or unaccounted metal links to the screen that fixes it.

## 2. Personas

Small-foundry people often hold several of these roles. Roles are permission and landing-page bundles, not job titles.

| # | Persona | Device | Goal | Pain today | Lands on |
|---|---|---|---|---|---|
| P1 | **Owner / GM** (small foundry, 5–30 staff) | Phone + laptop | Know if today's orders will ship and if money is made | Too many screens; wants answers, not modules | Today board, then Dashboard |
| P2 | **Sales / order desk** | Desktop | Quote, confirm, promise dates, invoice | Re-keying a PO; repeat orders by hand | Orders, Quick order |
| P3 | **Planner / PPC** | Desktop | Turn orders into a moulding list and heat plan | Heat plan drops started orders; shortages found late | Planner, moulding list |
| P4 | **Moulding supervisor** | Tablet at station | Make the day's moulds, count them, report shortfalls | Paper lists, flask shortages, miscount before pour | Moulding list, Moulds made, Count moulds |
| P5 | **Furnace / melt operator** | Phone/tablet near furnace, gloves | Charge to recipe, tap, pour on time, close the heat | Missed charge times, leftover metal unaccounted | Active heat, Next heat, Close heat |
| P6 | **Metallurgist / lab tech** (the `/m` phone user) | Phone | Take thermal and chemistry readings on time, log defects | Overdue tests, wrong-heat entries | `/m` home: active heat card, Tests due |
| P7 | **Fettling / finishing lead** | Tablet | Move batches through knock-out, blast, grind, with rejects | Lots lose pieces between steps | Production queue, Move a batch |
| P8 | **Quality inspector / QA manager** | Phone + desktop | Pass/rework/reject by cavity, raise NCRs, issue certificates | Defects not traceable to cavity/heat | Quick QC, Lots, NCR, certificates |
| P9 | **Stores / purchasing** | Desktop + phone | Right materials in stock, reorder, receive, issue to heats | Stockouts of charge materials, flasks | Stock, reorder alerts, GRN |
| P10 | **Dispatch / packing clerk** | Tablet | Pack and ship finished lots with correct paperwork | Wrong lot on wrong order | Ready to go out, Packing, Dispatch |
| P11 | **Accountant** | Desktop | Invoice per dispatch, receivables, tax, close period | Manual invoice from dispatch | Invoices, ledger, tax |
| P12 | **Customer** (external, later) | Phone/email | Enquire, approve quote, see status, get certificate | Phone calls for status | Enquiry link, order status |
| P13 | **Auditor** (ISO 9001, PPAP; read-only) | Desktop | Prove traceability and controlled records | Evidence scattered | Audit trail, trace, documents |

Design priority, in order: **P3 planner, P4 moulding supervisor, P1 owner, P5 furnace operator**. P6 (`/m`) is already designed and built. Others reuse existing patterns.

## 3. Flows

Flow notation: screen → action → result. "(rule)" marks a step that exists only when that process-depth rule is on.

### F1. Order to dispatch, Basic (7 steps)
Owner/P2 and floor.
1. Quick order → one screen, saved live with production released.
2. Today board → Moulds made (grid prefilled from planner); save assembles moulds.
3. Next heat → Start heat.
4. Moulds poured → pick heat + pattern rows; save makes pour, lots; order completes at pour.
5. Quick QC → good / rework / rejected per product.
6. Pack → Dispatch → invoice.

### F2. A real floor day, Full-ish (14 steps)
P3 → P4 → P5 → P7 → P8 → P10.
1. 18:00 auto moulding list from open orders (P3 adjusts rows, issues).
2. P4 makes moulds, enters output (auto assembly; shortage warning goes to run page).
3. P4 Count moulds before pour; shortfall needs reason, written off.
4. P5 Next heat → start heat, attach/default charge recipe, charges tick at scheduled times.
5. P6 thermal tests on timer (F4).
6. Pour gate check (advisory / enforced) → pour → lots.
7. P5 Close heat: off time, leftover metal, where it went; balance shows unaccounted.
8. P7 Move a batch per step with cooling time, rejects.
9. P8 Quick QC by cavity mark; defects feed NCR over threshold.
10. P10 Pack → Dispatch → P11 invoice.

### F3. Repeat order (P2)
Order line set to repeat weekly/monthly → deliveries scheduled ahead (4/8/13 wks), daily top-up → "Change forecast" per period → history shows changes. Edge: change >3× needs confirm; cannot go below released production.

### F4. Thermal test on the phone (P6)
`/m` home → active heat card with countdown (red when overdue) → Start test → stage + cup, readings as tiles, cup batch → Save to heat. Branches: "Something wrong with this test?" (invalid + reason, set up same cup again); "Test did not finish" (invalid attempt, clock not reset). Gap: live analyser curve (26j), late-entry (27), tensile (26d), History tab.

### F5. Defect found (P6/P8)
Scan label or Log defect → defect, pieces, reject/rework, cavity, location on casting, piece IDs (rule) → feeds 30-day defects-by-cavity → NCR when threshold met → CAPA (optional).

### F6. Heat gone wrong (P5)
Overdue test or off-spec reading → heat card red → hold pour (gate) → corrective charge/note → retest → release or scrap, metal accounted at close.

### F7. Stock and reorder (P9)
Reorder alert → purchase order → receive (GRN) → material lot in store tree → issued to heat charge → stock drops automatically. Edge: charge recipe needs material below minimum → warning on Next heat.

### F8. Enquiry to order (P2, P12; paused work)
Enquiry → quotation (price list) → accept → order flow per rule (repeat work follows order flow) → contract review (auto-pass unless warnings).

### F9. Audit / trace (P13, P8)
Search customer complaint piece or lot → back to heat, chemistry, tests, mould, operator, charge materials; forward to dispatch and invoice. Certificate print from lot.

### F10. First-time setup (P1)
Setup wizard "How you work" questions → picks Basic / full preset → rules page remains for single-rule edits → menu shrinks via `RuleMenuFilter` → Today board.

## 4. Information architecture

- **Role home** (Today board; `/m` home on phone) replaces module-first navigation.
- Menu groups: Quick flow (top), Production, Melting, Quality, Sales & Planning, Purchases/Stores, Finance, HR, Settings. A rule that drops a screen also removes its menu entry.
- Cross-cutting: global search by order / lot / heat / piece ID; notifications for overdue tests, shortages, approvals.

## 5. Gaps found (UX debt to design)

1. Floor phone screens not built: moulding station (15i), melt/furnace (7m), packing/dispatch on tablet.
2. No role-based home: Today board is one view for all; P1, P4, P5, P10 need tailored cards.
3. Phone History tab, late-entry path, tensile and sand tests.
4. No customer-facing view (P12).
5. Empty, error and loading states, and offline/poor-signal behaviour for the floor, are not specified.
6. Language: foundry terms vs plain wording for small-shop users; possible local language (Urdu) and RTL support.
7. Accessibility: contrast in sun/plant light, glove-sized targets, colour-only red/green status.

## 6. Phases

| Phase | Output | Notes |
|---|---|---|
| U0 | Validate personas and top 5 flows with 2–3 real users (one small, one mid foundry) | Short interviews + floor walk; update this file |
| U1 | Role homes brief for Claude Design: P3, P4, P1, P5 landing screens, in that order | UI-only brief, no code |
| U2 | Floor phone/tablet set: moulding station, furnace, dispatch (reuse `/m` patterns, `--nav` gap) | After U1 export |
| U3 | States and edge cases: empty, error, offline, undo, permissions | Spec sheet |
| U4 | Build in slices per flow; feature test per behaviour change | Commit after each |
| U5 | Usability test on the floor, measure steps and time vs `StepCountTest` | 7 and 14 step pins as benchmark |

## 7. Success measures

- Steps order to dispatch: 7 (Basic) and 14 (real day) stay pinned.
- Time to log a thermal test: target under 60 seconds.
- Overdue tests per heat; unaccounted metal per closed heat (trend down).
- A new user finishes F1 without help in under 15 minutes.

## 8. Open questions

1. Which roles exist as real logins in a small foundry, and is a shared tablet login acceptable on the floor?
2. Is Urdu or another language needed at launch?
3. Should P12 (customer view) be in scope this year?
4. Offline tolerance: is plant wifi reliable at the furnace and moulding line?
5. Which floor phone screen comes first: moulding (15i) or furnace (7m)?
