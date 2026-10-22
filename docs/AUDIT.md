# Foundry ERP audit: gap analysis against ISO/ASTM and ISA-95

Date: 2026-10-22.

**Scope and method.** Static review of the Laravel/Postgres codebase: migrations, models, selected services and views, `docs/STATUS.md`, and keyword searches per standard. The app and test suite were not run. STATUS.md lists about 50 uncommitted files (cupola charge rounds, thermal tests) that were not audited. "Missing" means no model, table or route was found.

## 1. Executive summary

**Readiness: 5.5 / 10.**
- About 7 / 10 for a green-sand grey and ductile iron foundry that wants ISO 9001 plus customer-driven certificates.
- About 3 / 10 against the full brief: IATF 16949, EHS, ISA-95 integration, investment casting and die casting.

**Strengths:**
- Melt-to-pour control: the pour gate checks chemistry against grade windows and thermal-analysis recency before a heat can be poured.
- Grade handling: `iron_grades` holds chemistry and mechanical windows plus a cross-standard alias table (ASTM A48 / A536, ISO 185 / 1083, IS, JIS).
- Traceability: lot, pour, mould assembly, moulding run, pattern, station and sand; lot back to heat.
- Document control, contract review, approvals, audit log, CAPA, supplier evaluation, incoming inspection and gauge calibration are in place.

**Primary structural risks:**
1. The certificate is a printable web page, not a controlled record: no EN 10204 type, no immutable issue number, no signatory. It shows only the latest chemistry reading and two thermal tests.
2. No NDT or post-cast heat-treatment records. NDT is an inspection `type` string and a quote checkbox. `heat_treatments` covers melt treatment (Mg, inoculation, desulphurisation) only.
3. IATF 16949 is configuration only. `RequirementProfile` stores `ppap_level` and `fmea_format` as settings. No PPAP element, FMEA, MSA (gauge R&R) or SPC/Cpk records.
4. No integration layer. `routes/` holds only `web.php` and `console.php`: no API, ingest endpoint, device identity or ISA-95 hierarchy.
5. EHS and ethics are absent: no silica, REACH/SVHC, ISO 50001 or conflict-minerals (CMRT) support.
6. Investment and die casting are not modelled (no wax, shell, slurry, die or shot-profile data).

## 2. Gap analysis table

| Dimension / Standard | Status | Identified risk / flaw | Recommended fix |
|---|---|---|---|
| **1a. Heat and lot traceability** | Implemented | `heats`, `heat_events`, chemistry, thermal, treatment, power and process readings exist. Lot to heat via `CastingLot::sourceHeat()`, with a trace screen (`OrderTraceController`). Lot grouping by POUR or MOULD is built. | Add a DAY grouping and make QuickFlow pours create lots (open in STATUS). Add a recall / where-used report from a supplier lot forward to dispatch. |
| **1b. Charge recipe and least-cost optimisation** | Partial | `charge_batches` and items hold target, min and max kg. Charge postings tie to `material_lots`. No optimiser, no cost-driven charge calculation, no predicted chemistry from charge. | Add per-material typical analysis and yield (burn-off) and a linear-programming charge calculator with chemistry constraints. Compare predicted against spectrometer result per heat. |
| **1c. Spectrometer chemistry** | Partial | Manual file import (`ChemistryImport`, with element aliases). No instrument link. Only 9 elements plus CE (C, Si, Mn, P, S, Cr, Cu, Mo, Mg). | Add Ni, Ti, Sn, Sb, Pb, Al, V and trace elements as a child `heat_chemistry_elements` table or jsonb. Add a calibration or standard-check reference per reading. |
| **1d. Defect taxonomy and RCA links** | Partial | `quality_defects` (seeded, categorised, ICF group) link to NCRs and inspection results. NCR has the lot, and the lot reaches mould, run, pattern, station and sand. No defect quantity per mould or position; no Pareto or defect-vs-process report found. | Add `defect_occurrences` (lot, defect, qty, mould position, stage, detected_by). Build defect-vs-heat, sand, pattern and station reports. |
| **1e. Operations flow** | Partial | `production_stage_definitions` seeds SHAKEOUT, CUTTING, SHOT_BLAST, FETTLING, HEAT_TREATMENT, MACHINING, COATING and FINAL_INSPECTION, with stage events and yields. Core making has definitions and boxes only; no core-shop production run, sand or cure record. | Add core-making runs (box, sand, cure, counts) linked to the mould assembly. Add per-stage parameters. |
| **1f. Post-cast heat treatment** | Missing | The HEAT_TREATMENT stage is a label only. | Add `casting_ht_cycles` and `casting_ht_load_items` with setpoints, time-temperature trace, furnace, loaded lots and pyrometry/TUS reference (CQI-9 / AMS 2750). |
| **2a. Material specs (ASTM A48 / A536)** | Partial | Alias mapping exists. Seeded grades are grey and ductile only (CGI, white and alloy exist as enum values). One `min_tensile_mpa`, `min_yield_mpa`, `min_elongation_pct` per grade. The A48 / A536 to EN mapping is approximate. | Model each standard as its own row with its own limits, including section-size or class tables (A48 class by test-bar diameter), hardness ranges and A536 impact. Add A47, A532, A897 (ADI) and ISO 1083. |
| **2b. Tensile (E8) and hardness (E10/E18)** | Partial | `measurements` framework captures tensile, yield, elongation, HB and impact with sample type (Y-block, cast-on, from casting) and limits. Missing: test method, specimen size, Brinell indenter and load (HBW 10/3000), machine ID, technician. Brinell vs Rockwell not distinguished beyond the name "HB". | Add `test_method`, `specimen_*`, `indenter`, `load_kgf`, `machine_equipment_id` and `tested_by` to measurement sets. Reuse the gauge-calibration check to block out-of-calibration machines. |
| **2c. Certificate of Analysis / 3.1** | Partial | `CertificateController` renders an HTML page with a DRAFT banner from `QualityGates::lotReadiness`. No EN 10204 type, no certificate number or revision, no stored copy, no e-signature or signatory field, no PDF. | Immutable `certificates` table (section 4.A) snapshotting data at issue, with unique number, signatory and PDF hash. Re-issue creates a new revision. |
| **2d. NDT** | Missing | Inspection `TYPES` has the string 'NDT'. No method (RT, UT, MT, PT), acceptance standard or level (ASTM E446 / E186 / E280, ISO 4993, MSS SP-55), technician qualification or film/report. | See section 4.B. |
| **2e. NCR and CAPA** | Partial | NCR and `capa_actions` both exist with containment, root cause, corrective, preventive and effectiveness verification. Root cause is free text. The two tables duplicate fields. No 8D structure stored although a `report_8d` setting exists. | Link NCR to CAPA by foreign key and remove duplicated columns. Add structured root-cause steps (5-Why, Ishikawa category) and D1-D8 fields. |
| **2f. PPAP / FMEA / control plan / MSA / SPC** | Missing, apart from control plans | `process_control_plans` and inspection plans exist with approvals. PPAP and FMEA only as customer-requirement settings. No PPAP element records, FMEA, gauge R&R or Cpk calculation. | Add PPAP package per product revision (18 elements and status), `fmea` / `fmea_lines` (S, O, D, AP), `msa_studies`, and an SPC calculator on `measurements`. |
| **3a. ISA-95 hierarchy** | Missing | `equipment` and `work_centers` are flat, joined by `equipment_work_center_assignments`. No enterprise, site, area or cell level; no equipment-class or property model. | Add a self-referencing `plant_nodes` tree (enterprise, site, area, work cell, unit) and attach `equipment` and `work_centers`. Add equipment class and property tables. |
| **3b. MES/SCADA ingest readiness** | Missing | No API routes. Readings carry a `source` column ('MANUAL') but nothing writes anything else. No device auth, idempotency keys, or OPC UA / MQTT bridge. | Add versioned `/api/v1/ingest/*` with Sanctum or HMAC-signed device tokens, idempotency key and a raw-payload log table. Map to `heat_chemistry_readings`, `heat_process_readings`, `heat_power_readings`. |
| **4a. Silica / RCS exposure** | Missing | No exposure samples, OELs, health surveillance or respirator records. | See section 4.C. |
| **4b. REACH / hazardous binders** | Missing | `materials` has no hazard class, SDS link or SVHC flag. | Add SDS reference, CAS numbers, SVHC and hazard flags on `materials`, and a restricted-substance report for EU sales. |
| **4c. Energy (ISO 50001)** | Partial | Per-heat power and cumulative kWh readings exist; meters can have a KWH unit. No site-level baseline, EnPI (kWh per tonne good casting) or cost allocation. | Add an energy baseline and EnPI view (heat kWh / tonnes good castings). Add non-melt meters (compressors, HT furnaces). |
| **4d. Conflict minerals (CMRT)** | Missing | Suppliers have approval status and scores. No country of origin, smelter list or CMRT version. | Add `supplier_declarations` (type, version, expiry, file), a smelter list, a per-material "3TG-relevant" flag and a purchasing gate. |
| **Platform governance** | Implemented | Audit log, approvals, controlled documents with revisions, gauge calibration, supplier evaluation, incoming inspection, contract review, custom fields, roles and permissions. | Keep. Track the gaps in `iso-9001-readiness.pdf` against code (STATUS item 5 lists this as undone). |

## 3. Notes on specific findings

- **Quality gate is strong but narrow.** `QualityGates::pourGate` blocks pouring on out-of-window chemistry or a stale or failed thermal test. It silently passes any heat with no iron grade ("legacy records are not gated"), and a config switch turns it off. An auditor will want a logged override with reason and approver.
- **Seed windows are generic.** The migration comment says they "MUST be reviewed against your own control plans". Put this on the go-live checklist.
- **Free-text identifiers.** `heat_or_batch_reference` and `certificate_reference` on `material_lots` are text, so a supplier's mill certificate isn't linked to chemistry. Incoming spec checks (`material_chemistry_specs`) only cover C, Si, Mn, S and P.

## 4. Critical schema additions (top 3)

Stack is Laravel + Postgres: Postgres DDL (convert to migrations) and JSON.

### A. Immutable certificates with test-method traceability

```sql
CREATE TABLE certificates (
  id uuid PRIMARY KEY,
  certificate_number varchar(40) NOT NULL,
  revision smallint NOT NULL DEFAULT 0,
  casting_lot_id uuid NOT NULL REFERENCES casting_lots(id),
  en10204_type varchar(4) NOT NULL CHECK (en10204_type IN ('2.1','2.2','3.1','3.2')),
  standard_ref varchar(80),                -- e.g. 'ASTM A536-84 (2019) 65-45-12'
  snapshot jsonb NOT NULL,                 -- chemistry, mechanical, NDT, HT, dimensional at issue
  signed_by uuid NOT NULL REFERENCES users(id),
  signed_at timestamptz NOT NULL,
  pdf_path text NOT NULL,
  pdf_sha256 char(64) NOT NULL,
  superseded_by uuid REFERENCES certificates(id),
  UNIQUE (certificate_number, revision)
);
ALTER TABLE measurement_sets
  ADD COLUMN test_standard varchar(40),        -- 'ASTM E8/E8M-22', 'ASTM E10-18'
  ADD COLUMN specimen_diameter_mm numeric(6,2),
  ADD COLUMN indenter varchar(10),             -- 'HBW10/3000'
  ADD COLUMN machine_equipment_id uuid REFERENCES equipment(id);
```
Block updates and deletes with a trigger. Gate issue on `lotReadiness` and gauge calibration.

### B. NDT examinations with qualified personnel

```sql
CREATE TABLE ndt_examinations (
  id uuid PRIMARY KEY,
  casting_lot_id uuid NOT NULL REFERENCES casting_lots(id),
  method varchar(3) NOT NULL CHECK (method IN ('RT','UT','MT','PT','VT','ET')),
  procedure_ref varchar(60) NOT NULL,
  acceptance_standard varchar(60) NOT NULL,    -- 'ASTM E446 Level 2'
  sample_size int, qty_examined int NOT NULL, qty_rejected int NOT NULL DEFAULT 0,
  examiner_id uuid NOT NULL REFERENCES employees(id),
  examiner_level varchar(10) NOT NULL,         -- 'ISO 9712 L2'
  qualification_expires_on date NOT NULL,
  equipment_id uuid REFERENCES equipment(id),
  quality_defect_id uuid REFERENCES quality_defects(id),
  examined_at timestamptz NOT NULL,
  result varchar(10) NOT NULL CHECK (result IN ('ACCEPT','REJECT','REPEAT')),
  report_attachment_id uuid
);
```
Reject if `qualification_expires_on < examined_at`.

### C. RCS silica exposure monitoring

```sql
CREATE TABLE rcs_samples (
  id uuid PRIMARY KEY,
  plant_area text NOT NULL,                    -- later FK to plant_nodes
  sample_type varchar(10) NOT NULL CHECK (sample_type IN ('PERSONAL','AREA')),
  employee_id uuid REFERENCES employees(id),
  sampled_on date NOT NULL, duration_min int NOT NULL,
  rcs_mg_m3 numeric(8,4) NOT NULL,             -- 8-hour TWA
  oel_mg_m3 numeric(8,4) NOT NULL DEFAULT 0.05,
  lab_ref varchar(80),
  exceeds_oel boolean GENERATED ALWAYS AS (rcs_mg_m3 > oel_mg_m3) STORED,
  capa_action_id uuid REFERENCES capa_actions(id)
);
```
An exceedance should raise a CAPA automatically. The OEL varies by jurisdiction, so make it a setting.

### Device ingest payload (dimension 3)

```json
POST /api/v1/ingest/chemistry
{ "idempotency_key": "SPEC01-20261022-0931-A",
  "device_id": "spectro-01", "heat_number": "H-26-1022-03",
  "sample_stage": "AFTER_TREATMENT", "measured_at": "2026-10-22T09:31:00Z",
  "elements": { "C": 3.62, "Si": 2.41, "Mn": 0.28, "S": 0.011, "Mg": 0.043, "Ni": 0.02 } }
```

## 5. Suggested order of work

A (certificates), then B (NDT), then the ingest API and `plant_nodes`, then PPAP/FMEA/MSA/SPC, then EHS and CMRT. Before building A and B, confirm which customers need 3.1 certificates and NDT.
