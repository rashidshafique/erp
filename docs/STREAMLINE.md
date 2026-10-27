# Streamlining order-to-dispatch for small foundries

Status 2026-10-27: phases 0 to 8 built and committed. Not done: a step-count pin for the Full path (only Basic is pinned); the melting plan and work lists screens still exist for Full.

## Context

A small foundry going from order to dispatch today goes through: sales order (draft), contract review (run, acknowledge, two sign-offs), production order (draft, release), planner, work list (draft, issue), moulding run (draft, complete), assemble moulds, melting plan (rebuild, release heat), heat, pour (create, add moulds, start, complete), lots, QC, packing, dispatch.

What already helps:
- The Basic preset (`app/Support/ProcessDepth.php`) cuts the sales side to one click (`order_flow=SIMPLE` with `contract_review=SKIP`).
- Foundry size (`app/Domains/Licensing/FoundrySize.php`) shortens the menu.
- QuickFlow (`app/Domains/QuickFlow`) has a quick order screen, a grid for moulds made and poured, and Next heat.

The gap: QuickFlow pours create **no mould assemblies and no lots** (`FloorEntryController::poured`). So Quick QC, packing, dispatch and order completion get nothing from it, and the floor side of the full screens is still the whole chain.

**Agreed direction:** approach 1 with QuickFlow eventually replacing the full moulding and pour screens when Basic is on.
- There is one set of real documents.
- Each collapsible step is its own process-depth rule. Full keeps today's behaviour, and Basic collapses the step.
- QuickFlow becomes the floor entry screen that writes real runs, assemblies, pours and lots.
- The rules page stays, so any foundry can change any single rule. The setup wizard and presets only fill in values.

**Chosen scope:**
- Collapsible steps: contract review auto-pass, work lists optional, auto mould assembly and a one-step pour, no drafts and melting plan off.
- Extras: Today board, fixes for the bugs found, a step-count test, and a setup wizard.

## Target click path (Basic)

1. Quick order: one screen, saved straight to live with released production.
2. Today board, then "Moulds made": the grid is prefilled from the planner. Saving creates the run and assembles the moulds.
3. Next heat → start the heat (the heat page is unchanged).
4. "Moulds poured": choose the heat and pattern rows. Saving creates the pour, attaches the waiting moulds, completes the pour and makes lots. The order completes at pour.
5. Quick QC.
6. Pack and dispatch.

That is about six screens. With Full, every step stays as it is today.

---

## Phase 0: Fix the bugs found (one commit each)

1. **Melting plan drops started orders.**
   - Problem: `HeatPlanner::demand()` (`app/Services/Planning/HeatPlanner.php:217`) only reads orders with status RELEASED.
   - Fix: include IN_PROGRESS orders and subtract the metal already poured for each one (from `pour_mould_assemblies` and its lots).
   - Test: in the existing heat-plan test.
2. **Quick paths bypass the release approval.**
   - Problem: `ProductionOrderFactory::releaseForLine()` (`app/Services/Orders/ProductionOrderFactory.php:56-66`) writes RELEASED without going through `ApprovalGate`.
   - Fix: when a `production_order.release` policy is active, leave the order as DRAFT, raise the approval request, and tell the user.
   - Test: `ApprovalFlowTest`.
3. **An approved review never goes stale.**
   - Problem: `SalesOrder::hasApprovedReview()` (`app/Domains/Sales/Models/SalesOrder.php:83`) accepts any review ever approved.
   - Fix: only the latest review counts. When review is REQUIRED, an edit to a line's product, quantity or date supersedes the APPROVED review (`SalesOrderEditor`).
4. **Re-running a review knocks a live order back.**
   - Problem: `ContractReviewer::evaluate()` (`:53`) and `sign()` (`:232`) set UNDER_REVIEW or CONFIRMED regardless of the order's state.
   - Fix: only change status from DRAFT or UNDER_REVIEW.
5. **Sign-offs are free-text names.**
   - Fix: store the logged-in user's id on each sign-off, with the name filled in from that user.
   - Under REQUIRED, the same user cannot sign as both Sales and Quality.
6. **The quick pour ignores the gate mode.**
   - Problem: `FloorEntryController::poured` hard-codes `gate_mode='ADVISORY'`.
   - Fix: use `PouringRules::gateMode($heat)`. ENFORCED refuses, with the reason.
7. **The Micro menu hides Packing.** Add `packing.index` to `FoundrySize::MICRO_SHOWS`.

Tests: extend `SalesOrderStatusTest`, `ApprovalFlowTest`, `QuickFlowTest` and `FoundrySizeTest`, plus the heat-plan test.

## Phase 1: Rules framework

- **`ProcessDepth` registry.**
  - Replace the `match` arms in `definition()`, `get()` and `set()` with a map of rule to rule class, so new rules plug in without editing three switches.
  - Add the new rule keys to `VALUES['BASIC']` and `VALUES['FULL']`. Every FULL value equals today's behaviour.
  - Add `worklists.start_before_issue` to the presets as well: ALLOW in Basic, BLOCK in Full.
- **Rules page stays and is grouped.**
  - `resources/views/settings/process-depth.blade.php` lists every rule under Orders, Moulding, Melting, Pouring and Quality.
  - Each rule can still be set on its own. The preset buttons only fill in values, and `current()` still reports CUSTOM.
- **Rule-driven menu.**
  - Add `RuleMenuFilter`, which wraps `FoundrySize` as the bound `MenuFilter`.
  - It hides screens a rule has switched off (work lists, the melting plan, and the full moulding and pour screens in Phase 5).
  - Hidden screens still open from links. Only the menu changes, the same way foundry size works today.

**New rules** (class, key, options, with Basic and Full values):

| Rule | Class / key | Options | Basic | Full |
|---|---|---|---|---|
| Contract review | `OrderRules` `orders.contract_review` | REQUIRED / **AUTO** (new) / SKIP | SKIP | REQUIRED |
| Work lists | `WorkListRules` `worklists.use` | USED / OFF | OFF | USED |
| Mould assembly | `MouldingRules` (new, `app/Support`) `moulding.assembly` | MANUAL / AUTO | AUTO | MANUAL |
| Pour entry | `PouringRules` `pouring.entry` | STEPS / ONE_STEP | ONE_STEP | STEPS |
| Melting plan | `PlannerRules` `planner.melting_plan` | USED / OFF | OFF | USED |
| Floor screens | `MouldingRules` `moulding.floor_screens` | FULL / QUICK | QUICK | FULL |

The "no drafts" choice is carried by existing rules plus these: `order_flow=SIMPLE`, `start_before_issue=ALLOW`, `pouring.entry=ONE_STEP`, and quotation acceptance following `order_flow` (Phase 2). No separate drafts switch is needed.

Rules that depend on QuickFlow (`melting_plan=OFF`, `floor_screens=QUICK`) fall back to USED and FULL while the quickflow module is off. The rules page says so.

Test: `ProcessDepthTest` (presets set the new keys, CUSTOM is still detected, single-rule save works).

## Phase 2: Orders

- **Contract review AUTO** (`app/Domains/Sales/Services/ContractReviewer.php`, `SalesOrderController::store`):
  - Saving an order runs `evaluate()` automatically.
  - All PASS: the review is approved automatically, recorded as "auto" with the saving user. With `order_flow=SIMPLE` the order then goes straight to production.
  - WARN: the order stays under review. One acknowledgement and one sign-off by the logged-in user approves it, with no second signature.
  - BLOCK: the order stays under review and the fix links show, as today.
  - `OrderRules::reviewRequired()` becomes true for REQUIRED and for AUTO. `SalesOrderController.php:118` goes straight to production when the review is SKIP, or when it is AUTO and passed.
- **Quotation accepted follows the order flow** (`app/Domains/Enquiry/Services/QuotationService.php:220-260`):
  - With SIMPLE and the review passing or skipped, accepting a quotation makes a live order with released production orders, not a DRAFT.
- Tests: `ContractReviewAutoTest` (new), and extend `SalesOrderStatusTest`.

## Phase 3: Moulding

- **Auto mould assembly** (`app/Services/Production/MouldAssembler.php`):
  - Generalise `assembleFlaskless()` into `assembleAccepted(MouldingRun $run)`. When `moulding.assembly=AUTO`, it assembles every accepted mould not yet assembled, flasked moulds included.
  - Configuration: the run's own, else the station's or the pattern revision's default, else the first active one. Flasked moulds get 2 layers, flaskless moulds 1.
  - Flask shortage becomes a warning on the run instead of a block, for automatic assembly only.
  - It is called from every place output is saved: the run's record and complete actions (`MouldingRunController`), `BulkRunEntry`, `WorkListService` completion, and `QuickFloorLinker::linkNow`.
  - Flaskless moulds keep assembling as today under either value.
- **Work lists OFF** (`WorkListRules`, `OrderPlanner`, `PlannerController`):
  - Work lists and their menu entry are hidden.
  - The planner's "Put on a work list" becomes "Plan today's moulding". It needs no stored list, because the QuickFlow "moulds made" grid opens prefilled with `OrderPlanner`'s first-shift suggestion (pattern, station, order and suggested moulds), and the moulder types moulds made.
  - Runs need no list under either value, as today.
- Tests: `MouldAssemblyRuleTest` (new); extend the planner test and `QuickFlowTest` for the prefill.

## Phase 4: Pouring and melting (closes the QuickFlow lot gap)

- **`OneStepPour` service** (new, `app/Services/Production/OneStepPour.php`):
  - It takes a heat, rows of pattern revision, order and moulds poured, an optional temperature and an optional weight.
  - Inside one transaction:
    1. Create the pour.
    2. Pick the waiting PREPARED assemblies for each row, oldest first, through `PourAssembler::attach()`.
    3. Run the gate with `QualityGates` in the heat's gate mode.
    4. Complete the pour and call `LotFactory::createForPour()`. Lots, grouping, holds and completion at pour all follow their rules unchanged.
  - **Shortfall:** when fewer moulds are waiting than the row says (the moulder skipped "made"), it records a completed run for the difference, "made, not entered", and assembles those moulds, so a shop that only enters pours still gets lots.
  - Weight is optional. `LotFactory` already handles a missing weight, and the cavity map gives net weights.
- **The QuickFlow poured grid uses `OneStepPour`** (`FloorEntryController::poured`). It still keeps its `QuickFloorEntry` rows for the grid history.
  - Entries without an order still wait. When `QuickFloorLinker::linkNow` later links a POURED entry, it creates the run and assemblies, attaches them to that entry's pour, and calls `createForPour` again (it skips moulds that already have lots).
- **The full pour screen with ONE_STEP** (`PourController`, `resources/views/pours/create.blade.php`): the create form also takes waiting moulds (ticked) and the temperature and weight, and completes the pour in one submit. With STEPS it is unchanged. A "Pour moulds" button on the heat page opens it for that heat.
- **Melting plan OFF:** the heat-plan menu entry is hidden, and so is the planner's "Rebuild the melting plan after" box. Next heat (`NextHeatController`) is the melt-shop view and appears in the menu in its place.
- **Existing quick pours** made before this change keep no lots, with no backfill. STATUS.md says so.
- Tests: `OneStepPourTest` (new); extend `QuickFlowTest` (lots, completion at pour, shortfall, gate ENFORCED refusal), `LotGroupingTest` and `QuickQcTest` (quick pours now reach QC).

## Phase 5: QuickFlow replaces the full floor screens (`floor_screens=QUICK`)

- The menu shows Quick floor, Next heat and the Today board in place of Moulding runs, bulk entry, Mould readiness, Work lists, Pour register and the Melting plan (through `RuleMenuFilter`).
- The detail pages (run, pour, heat, lot) stay and open from links, because the records are real.
- The production order page's "+ Moulding run" opens the quick grid filtered to that order.
- `StartPage::routeFor` (`app/...StartPage.php`) sends floor roles to the Today board.
- Test: extend `SidebarMenuTest` and `StartPageTest`.

## Phase 6: Today board

- New page `quick.today` in QuickFlow, registered in its nav group at the top. It is the start page under QUICK, Micro or Small.
- Cards, each with a count, a short list and its one-click action:
  - Orders due (`DashboardData::queue()`), with New order.
  - Moulds to make today (`OrderPlanner` first shift), with Record moulds made.
  - Next heat (`NextHeatPlan::build()`), with Start heat.
  - Moulds waiting to pour, with Record pour.
  - Lots to QC (`QuickInspection::waiting`), with Quick QC.
  - Ready to pack or dispatch (only when the dispatch module is on).
  - Needs attention (`DashboardData::attention()`).
- Test: `TodayBoardTest` (new): the cards show the right counts and module-off cards are hidden.

## Phase 7: Setup wizard

- New Settings → "How you work" (`ProcessSetupController`, `resources/views/settings/process-setup.blade.php`). It is reached from a dashboard banner until it has been done once (`AppSetting setup.completed_at`) and can be re-run at any time.
- The questions map onto rules:
  - Do you review orders before production? Always with sign-off / only when something is off / no → `contract_review` REQUIRED / AUTO / SKIP.
  - Do you plan shifts on lists? → `worklists.use`.
  - Flasks, flaskless or both? → `moulding.assembly`.
  - Do you plan heats ahead? → `planner.melting_plan`.
  - Cupola, induction or other, and do you hold pours on tests? → the gate rules.
  - Lot per mould or per pour? → `lot_grouping`.
  - Complete orders at pour or at inspection? → `order_completion`.
  - Quick floor screens or full ones? → `floor_screens`.
  - Foundry size → menu.
- The last step lists every rule it will set, beside the current value, with "Change any of these later on the rules page". The rules page links back to the wizard.
- Test: `ProcessSetupTest` (new).

## Phase 8: Step-count test

- New `StepCountTest`. It drives order → dispatch over HTTP for Basic (quick path) and for Full (the full screens), counting the POST requests a user makes with a small helper.
- It checks that lots exist, the order completes, and a dispatch is made.
- It pins the Basic count (about 6 to 8) and the Full count, so neither grows unnoticed.
- The first version lands at the end of Phase 4 and is updated in Phases 5 and 6.

---

## Working conventions

- Each numbered item in Phase 0 and each phase from 1 to 8 is committed once its tests pass, never pushed.
- Run only the tests related to the change. No Pint.
- Update `docs/STATUS.md` in the same commit.
- New PHP classes go through `php artisan make:class` or `make:test --phpunit`.

## Verification

- Per phase: run the named test files, for example `php artisan test --compact tests/Feature/QuickFlowTest.php tests/Feature/OneStepPourTest.php`.
- Run `StepCountTest` after Phases 4 to 6.
- By hand in the browser (`get-absolute-url`):
  - Apply Basic and run the target click path, ending with lots in Quick QC and a dispatch.
  - Switch back to Full and check that every screen and step behaves as before. The preset reports FULL, and the existing order-to-pour flow tests (`OrderToPourFlowTest`) still pass.
