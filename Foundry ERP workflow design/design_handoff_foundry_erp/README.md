# Handoff: Foundry ERP (Castline), living design spec

## Read this first (for Claude Code)
- This package is **incremental**. It is not a full spec to build in one go. Screens will be added and existing ones will change. Treat it as a living reference.
- Build **one phase at a time** (see "Build order"). Stop after each phase so it can be reviewed.
- Before building or changing a screen, check `CHANGELOG.md` for what is new or changed since the last import.
- Screen IDs (`1a`, `6d`, `8a`…) are stable. Use them in commit messages, PR titles and code comments, for example `// design: 6d`, so later design changes can be traced to code.
- If a screen is not in this package, it isn't designed yet. Don't invent it. Use the existing app patterns, or ask.
- Track progress in the "Screen index" table: set `Built` when a screen ships.

## About the design files
`Foundry ERP.dc.html` is a **design reference made in HTML**. It is a single scrolling page with every screen laid out as a card, and a sticky nav at the top that links to each screen ID. It is not production code. Recreate the screens in the existing app (Laravel Blade + Livewire, stylesheets `app-base.css`, `app-compact.css`, `app-overrides.css`, CSS variables such as `--accent`). Reuse the existing partials and components first.

Open the file in a browser (keep `support.js` next to it). Find a screen by searching for `id="6d"` or by clicking its badge in the top nav. All styles are inline, so you can read exact values straight from the markup.

`screenshots/` holds PNGs of turn 1 (1a–1m) for quick visual reference. Where a screenshot and the HTML disagree, the HTML is correct.

## Fidelity
High fidelity: colours, type, spacing and copy are final. Map the values onto the app's tokens rather than hard-coding them. Sample data (customers, part numbers, codes) is illustrative only.

## Build order
1. **App shell**: sidebar, top bar, page header. Every screen uses it. (Spec below.)
2. **Shared components**: buttons, status pills, data table, label/value grid, KPI card, panel. (Spec below.)
3. **Screens, one module at a time**, starting where the repo already has the logic:
   order → plan → moulding run → pour gate → casting lots (6a–6e), then the rest by priority.
4. Simple mode (process depth, 7f) once the shell and some screens exist.

## Design tokens
Map these to CSS variables in `app-base.css`. The suggested names are only a guide; reuse an existing variable where one already fits.

| Token | Value | Use |
|---|---|---|
| `--accent` | `#2f5fd0` | primary buttons, links, logo mark, count badges |
| `--accent-hover` | `#1f3f8f` | link hover |
| `--text` | `#1c1d1f` | body text |
| `--text-muted` | `#6b6e73` | secondary text, table headers, field labels |
| `--text-faint` | `#8a8d92` | sidebar group labels, placeholders |
| `--border` | `#e6e7e9` | card, sidebar and header borders |
| `--border-row` | `#eff0f2` | table row dividers |
| `--border-input` | `#d9dbde` | secondary button border, toggle track |
| `--surface` | `#ffffff` | content background |
| `--surface-sub` | `#f8f8f9` | sidebar, pipeline columns |
| `--surface-pill` | `#f0f1f3` | neutral pill, selected segment |
| `--ok` | `#1d7048` | positive delta, pass |
| `--bad` | `#b3352c` | negative delta, fail, expiring |

- **Type**: `"Helvetica Neue", Helvetica, Arial, sans-serif`. Record codes use `ui-monospace, "SF Mono", Menlo, monospace` at .92em.
- **Sizes**: 11px (group labels, badges), 11.5px (table headers, pills), 12px (labels, meta), 13px (body, nav, buttons), 14px (brand), 20–22px/600 (page titles), 24px/600 (KPI values).
- **Radius**: 5px (logo mark), 6px (buttons, inputs, nav items), 8px (cards), 999px (pills).
- **Shadow**: active nav item `0 1px 2px rgba(0,0,0,.08)`.
- **Spacing**: content padding 24px 28px; header 14px 28px; gaps of 12, 16, 18, 24px.

## Phase 1: app shell

### Layout
CSS grid `220px minmax(0,1fr)`: sidebar on the left, top bar plus content on the right.

### Sidebar
- Container: background `--surface-sub`, 1px right border `--border`, padding 18px 14px, flex column, gap 18px, font 13px.
- Brand: 22×22 square, radius 5px, `--accent`, then "Castline" at 14px/600; gap 8px, padding 0 6px.
- Group: flex column, gap 2px. Label at 11px, uppercase, letter-spacing .06em, `--text-faint`, padding 0 8px 4px.
- Item: padding 6px 8px, radius 6px.
- Active item: background white, shadow `0 1px 2px rgba(0,0,0,.08)`, weight 500.
- Item with a count (for example Approvals): flex, space-between. Badge at 11px, padding 1px 7px, radius 999px, `--accent` background, white text.
- Footer, pinned with `margin-top:auto`: border-top `--border`, padding 10px 8px. It shows either the **Simple mode** switch (30×18 track `--border-input`, 14px white knob) or "Signed in as …" (12px, muted).
- Hover and focus states aren't designed yet. Use a light `--surface-pill` background on hover and the app's existing focus ring.

### Two menu sets in the design
- **Operations (1a)**: Sales (Overview, Enquiries, Quotations, Sales orders) · Engineering (Feasibility, Patterns & tooling) · Production (Planning, Melt & heats, Fettling) · Quality (Inspection, PPAP, NCR & CAPA, Audits & documents) · Logistics (Dispatch).
- **Owner (4a)**: Home, Approvals (count), Reports · Business (Sales & quotes, Production, Quality, Receivables).

Later screens add modules that aren't in either menu yet: purchasing, stores, payables, ledger, bank, maintenance, payroll (T7, T8). **The menu will grow and change.** Build it from a config array (group → items → route, permission, `simple` flag), not hard-coded markup. Set the active item with `request()->routeIs()`. Hide items by role and by simple mode.

### Top bar
- Flex, space-between, padding 14px 28px, bottom border `--border`.
- Left: search box, 360px wide, padding 7px 12px, 1px `--border`, radius 6px, 13px, placeholder "Search part, heat, serial, PO…" in `--text-faint`.
- Right (13px, gap 12px): "Mon 27 Oct · Shift A" in muted text, then a 28px initials avatar (`--border` background, 11px/600).

### Page header (inside the content area)
- Dashboard style (1a): an h1 at 22px/600 with letter-spacing -.01em, and a primary button on the right.
- Record style (most screens, for example 8a): breadcrumb row "Module / CODE" plus a status pill, with actions on the right. 13px, gap 16px, separator "/" in muted text.

## Phase 2: shared components
- **Primary button**: padding 7px 14px, radius 6px, `--accent` background, white text, no border, 13px/500.
- **Secondary button**: same size, white background, 1px `--border-input`.
- **Status pill**: 11.5px/500, padding 2px 8px, radius 999px, nowrap. Neutral is `--surface-pill` with `#55585d` text. Coloured pills follow the same shape; take their colours from the screen that uses them.
- **Table**: full width, collapsed borders. `th`: 11.5px/500, muted, padding 9px 12px, bottom border `--border`, left-aligned, nowrap. `td`: 13px, padding 10px 12px, bottom border `--border-row`. Number columns are right-aligned. Codes use `<code>`.
- **Label/value grid**: `repeat(4, minmax(0,1fr))`, gap 14px. Label 12px muted; value 13px, weight 500 for names.
- **KPI card**: 1px `--border`, radius 8px, padding 14px 16px. Label 12px muted; value 24px/600; sub-line 12px, muted or `--ok`/`--bad`.
- **Record layout**: main column plus a 340px side panel (`minmax(0,1fr) 340px`); main padding 20px 28px.

## Screen index
Status: `Designed` means it is in the design file. Set `Built` once a screen is in the app. New turns will be added at the top.

| ID | Screen | Repo files (where known) | Built |
|---|---|---|---|
| 8a | Invoice from dispatch | | |
| 8b | Credit note | | |
| 8c | Cash application + statement | | |
| 8d | Requisitions + quote compare | | |
| 8e | Approved suppliers | | |
| 8f | Supplier return + debit note | | |
| 7a | Cavity map | app/Services/PatternCavityMap.php | |
| 7b | PO + receiving | | |
| 7c | Stores | | |
| 7d | Payables | | |
| 7e | Approvals inbox | | |
| 7f | Process depth | app/Support/ProcessDepth* | |
| 7g | Iron grade | | |
| 7h | Furnaces / charge recipes | | |
| 7i | Ledger P&L / BS | | |
| 7j | Bank reconciliation | | |
| 7k | Maintenance | | |
| 7l | Payroll | | |
| 7m | Floor mobile (lot scan, QC tally, pour gate) | | |
| 6a | Order + contract review + release | app/Support/OrderRules.php, resources/views/production-orders/ | |
| 6b | Plan (planner, work list, melting plan) | resources/views/planner/, work-lists/, heat-plan/ | |
| 6c | Moulding run | resources/views/moulding-runs/create.blade.php, app/Services/PatternCavityMap.php | |
| 6d | Pour + pouring gate | app/Services/QualityGates.php, app/Support/PouringRules.php, resources/views/pours/ | |
| 6e | Casting lots | app/Models/CastingLot.php, app/Support/QualityRules.php | |
| 5a–5d | Defect atlas, leak log, fishbone, mobile classifier | | |
| 4a | Owner home | | |
| 4b | Sand vs defects | | |
| 4c | Simple invoicing | | |
| 3a–3p | Masters, engineering, shop floor, quality tools, finance, reports, mobile | | |
| 2a–2d | Job timeline, cost stack, PPAP matrix, simple mode | | |
| 1a | Dashboard (app shell reference) | | |
| 1b–1m | Enquiry → quote → order → production → QC → dispatch → PPAP, ISO 9001, mobile | | |

T1–T5 entries are grouped. Open the design file for the individual screens.

## Interactions and states
The mocks show each screen in a single state with sample data. Loading, empty and error states aren't designed: use the app's existing patterns. Gates and checks (pour gate, contract review, lot readiness) take their rules from the repo services named above. The design shows how results look, not the logic.

## Updating this package
When new designs arrive:
1. Replace `Foundry ERP.dc.html` with the new export.
2. Read the new entry at the top of `CHANGELOG.md`.
3. Build only what it lists as new or changed, and leave built screens alone unless they are listed.

## Files
- `Foundry ERP R4 Components.dc.html`: round 4 components with all states. Build these before any round 4 screen; round 4 uses Instrument Sans in place of Helvetica.
- `Foundry ERP R4 Homes / Bell / Quality / Moulding / Furnace / Owner.dc.html`: round 4 screens (see the 2026-11-07 CHANGELOG entry)
- `Foundry ERP.dc.html`: all screens (open in a browser)
- `support.js`: runtime needed to open the design file
- `CHANGELOG.md`: what changed in each import
- `screenshots/`: PNGs of 1a–1m
