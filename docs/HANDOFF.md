# Handoff

Updated 2026-11-08. Overwritten each session, not appended.

## Working on
Round 4 floor flows from `Foundry ERP workflow design/` (R4 files). Build order left: 3 planner Resolve panel (F2·4 flask shortage), 6 merged home, 10-11 clerk home and owner Pass to, 12 codes (`ITEM-`, `PRD-`, `PO-`). Quality reshape (8-9) is built but NOT merged.

## State
- master at c8200144 + this docs commit. Merged: thresholds in Rules, R4 components, moulding tablet, furnace tablet, planner Waiting block (`PlannerWaiting`, `RoleHome`), permission-aware sidebar.
- Branch `r4-quality-reshape` in `.worktrees/quality-reshape` (4 commits, last 0e4484c8). Waiting for the user to say merge. Two migrations from it already ran on the shared dev DB: `customers.allows_partial_shipments`, `sales_order_lines.partial_shipments` and `min_ship_qty`, `casting_lots.quality_hold`. If merging, no migrate needed on this DB; remove the worktree and branch after.
- What it holds: `ShipRule` (customer/line partial-shipment rule, shippable pieces, minimum), changes in `QualityGates::lotReadiness`, `ShipmentService` (packable, NCR check), `QuickInspection::record(... ?CastingLot $only)`, `NcrController::close` clears held lots, lot page "What blocks dispatch", queue blockers, customer checkbox, order-line Shipping rule dialog, quality tablet `QualityTabletController` and `resources/views/floor/quality/*` (Q·2 to Q·5, permission `quality.inspect`, routes `floor.quality.*`).
- Not built in quality: Q·1 quality home, Q·6 NCR tablet screen, Q·W, Q·L, Hold button on Q·3, folding one-piece lots, menu link, codes. Rework is never re-inspected; the NCR counts rework plus rejected (design: rejected only).
- Never looked at in a browser: the quality tablet (site is http://erp, serves master, login needs a test user). `qi@test.local` password was reset to the shared `TEST_USERS_PASSWORD` in `.env` (never print it).

## Rules to keep
- Never merge or push without the user saying so. Worktrees only inside `.worktrees/`. Commit each finished feature with the attribution lines; reload Chrome after commits. No Pint, no menu tests, run only related tests.
- Quote globs in grep (`--include='*.php'`); macOS sed needs `-i ''`.
- Known failing tests are listed in docs/STATUS.md; they fail on c7321dc4 too.
