# Stage 2 – order to pour, with thermal analysis

What was added to the app (existing screens keep working):

| Area | Where | What it does |
|---|---|---|
| Customers | Sales & Planning → Customers | Customer accounts |
| Sales orders | Sales & Planning → Sales orders | Order entry with lines on approved product revisions |
| Contract review | Sales order page | Checks every line: approved revision, approved product specification, customer requirement, grade conflict between our spec and the customer's (ASTM/ISO/IS/JIS equivalents), furnace able to melt the grade, thermal window set, pattern cavity linked, control plan, delivery lead time. Warnings need a written acknowledgement; sales and quality must both sign. |
| Production orders | Sales order page | Created from an order line only after the review is approved; releasing a customer production order also needs the approved review |
| Melting plan | Sales & Planning → Melting plan | Groups released orders by iron grade, sizes heats to furnace capacity, sequences grey before ductile, flags changeover and late risk, lets you change furnace and release a heat |
| Iron grades | Metallurgy → Iron grades | Chemistry/CE/temperature windows, standards equivalents, per-furnace overrides, thermal acceptance windows |
| Thermal analysis | Metallurgy → Thermal tests | Upload or paste a cooling curve; finds TL, TEmin, TEmax, recalescence, solidus; CE and carbon from TL; judges against the grade window; inoculant advice; calibration from your own history |
| Pouring gate | Pour page | A heat with an iron grade cannot start a pour without chemistry in the grade window and a recent passing thermal test (45 minutes by default). Pour temperature outside the grade window needs a reason. |
| Dispatch evidence | Sales order → Trace to dispatch | Order → production order → moulding runs → casting lots → heat → thermal and chemistry evidence → inspections |
| Product ↔ pattern | Stage 1 | The cavity map feeds the planner's metal estimate and the contract review |

Fixed in your existing app: the "Create heat" page failed because the charge recipes were never passed to it (and the recipe was not saved). Heats can now take an iron grade and a charge recipe.

Settings: `config/foundry.php` (gates on/off, 45-minute test age, furnace families, fill factor, default yield, lead time). Env switches: `FOUNDRY_REQUIRE_CONTRACT_REVIEW`, `FOUNDRY_ENFORCE_THERMAL_GATE`.

Not built yet (the tables exist): screens to add mould assemblies to a pour, create casting lots, create dispatches and packing records, and quality inspection entry. The trace page and the lot readiness check already read those tables.
