# APQP and PPAP audit

Date: 2026-10-27. Static review of the code; nothing was run.

**Bottom line.** Neither APQP nor PPAP exists as a workflow. Both are stored only as customer requirement settings (`RequirementProfile::SETTINGS`), and nothing reads those settings to gate anything. Several building blocks for a PPAP package do exist.

## APQP phases

| Phase | Status | What exists |
|---|---|---|
| 1. Plan and define | Partial | Enquiry items have a feasibility status and notes (`EnquiryItem::FEASIBILITY`). Contract review checks spec, customer requirement, grade, furnace, pattern and control plan. No APQP project, timing plan, team or customer inputs record. |
| 2. Product design | Partial | Product revisions, product specifications, customer product requirements (with customer approval reference), BOM. No DFMEA, no special-characteristics list, no drawing or balloon link on the revision. |
| 3. Process design | Partial | Process routes, stage definitions, control plan per revision (copies the previous revision, approval, flagged for review on a pattern change). No process flow diagram record, no PFMEA, no floor-plan or work-instruction link. |
| 4. Validation | Missing | No trial run, run-at-rate, capability study (Ppk), MSA study or PPAP package. |
| 5. Feedback and correction | Partial | NCR, CAPA, 8D setting. No feedback into an FMEA, no lessons-learned record. |

No phase gates, sign-offs or launch-readiness status anywhere.

## PPAP elements

| # | Element | Status |
|---|---|---|
| 1 | Design records | Partial: product spec and customer requirement document number and revision; no ballooned drawing on the revision |
| 2 | Engineering change documents | Missing: no ECN record; revisions are simply added |
| 3 | Customer engineering approval | Partial: `customer_approved_at` and `customer_approval_reference` on `customer_product_requirements` |
| 4 | DFMEA | Missing |
| 5 | Process flow diagram | Partial: route and stage sequence could be printed as one; no document |
| 6 | PFMEA | Missing |
| 7 | Control plan | Exists, with gaps (below) |
| 8 | MSA studies | Missing: gauges and calibration exist and inspection results check calibration; no gauge R&R study |
| 9 | Dimensional results | Partial: inspection results per lot with gauge; no first-article or layout report across all characteristics and cavities |
| 10 | Material and performance tests | Partial: chemistry, tensile, hardness and thermal per heat; not packaged per product |
| 11 | Initial process studies (Ppk) | Missing: measurements stored, no Ppk or Cpk calculation |
| 12 | Qualified lab documentation | Missing (only `external_lab` on a calibration) |
| 13 | Appearance approval report | Missing |
| 14 | Sample parts | Missing |
| 15 | Master sample | Missing |
| 16 | Checking aids | Partial: gauges register; fixtures not tied to a product |
| 17 | Customer-specific requirements | Exists: requirement profiles and customer product requirements |
| 18 | Part Submission Warrant | Missing |

## Defects in what exists

1. **Profile settings are inert.** `ppap_level`, `ppk_initial`, `cpk_ongoing`, `fmea_format` and `msa_required` are only displayed. `RequirementResolver` is used only by the customer page; contract review lists profile names only (`ContractReviewer.php:100`). A PPAP level 3 customer is checked the same as one with no requirement.
2. **"Full PPAP" option is mislabelled.** `ControlPlanRules::RULES['pattern_change']` option `PPAP` only blocks until a new revision has an approved plan. No PPAP is checked.
3. **Control plan cannot mark special characteristics.** `process_control_plan_items` has `is_critical`, `sample_size`, `control_category` and `measurement_method_code`, but `ControlPlans::saveItems` never writes them.
4. **No control plan phase.** AIAG has prototype, pre-launch and production plans; here a revision has one.
5. **Control plan and inspection plan are separate.** `ProcessControlPlan` and `InspectionPlan` both hold per-revision characteristics, limits and reaction plans with no link, so they will drift.
6. **No PPAP trigger.** Nothing ties a pattern, revision or supplier change to "re-submit PPAP", apart from item 2.

## Options

Each is a rule that can be switched off, so small foundries stay unaffected.

- **A. Make profile settings bite (small).** Contract review warns or blocks when the customer's PPAP level is above 0 and the revision has no approved PPAP; same for MSA and Cpk. WARN / BLOCK / OFF like `ControlPlanRules`.
- **B. Fix the control plan (small).** Save `is_critical` as CC/SC, sample size and method; add a phase (prototype, pre-launch, production); rename or make real the "Full PPAP" option.
- **C. PPAP package per product revision (medium).** One record per revision and customer with the 18 elements, each with a status (required by level, not required, done, approved) and a link to existing records or attachments. Printable PSW with signatory; customer approval recorded. The level decides which elements are required.
- **D. Ppk / Cpk calculator (medium).** Over existing `measurements` and inspection results per characteristic, against profile targets. Covers element 11 and ongoing SPC.
- **E. MSA studies (medium).** Gauge R&R record (operators x parts x trials, ANOVA or average-and-range) on the gauges register. Covers element 8.
- **F. PFMEA (larger).** FMEA lines per process step (failure mode, effect, cause, S/O/D, AP or RPN by `fmea_format`, actions), linked to control plan rows so a high AP forces a control.
- **G. APQP project with gates (larger).** One launch project per new product or revision, five phases, a gate checklist and sign-off; ties C to F together. Off by default.
- **H. First-article or layout report (medium).** Every characteristic x every cavity of a pattern revision, reusing `PatternRevisionCavity`. Covers element 9.

Suggested order: A, B, C, D first (a real PPAP submission with little new data entry); E, F, G only if there are automotive customers. `docs/AUDIT.md` places PPAP / FMEA / MSA / SPC after certificates and NDT.
