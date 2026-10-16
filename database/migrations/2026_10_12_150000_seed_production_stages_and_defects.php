<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        if (DB::table('production_stage_definitions')->count() === 0) {
            $stages = [
                ['SHAKEOUT', 'Shakeout', 10, false, 'Castings knocked out of the sand after cooling. Record pieces recovered and any broken in shakeout.'],
                ['CUTTING', 'Gate and riser removal', 20, false, 'Runners, gates and risers removed.'],
                ['SHOT_BLAST', 'Shot blasting', 30, false, 'Sand and scale removed from the surface.'],
                ['FETTLING', 'Fettling and grinding', 40, false, 'Flash, fins and gate stubs ground; surface dressed.'],
                ['HEAT_TREATMENT', 'Heat treatment', 50, false, 'Annealing, normalising, quench and temper, or stress relief where the specification requires it.'],
                ['MACHINING', 'Machining', 60, false, 'Machined to drawing.'],
                ['COATING', 'Painting and coating', 70, false, 'Primer, paint or other protective coating.'],
                ['FINAL_INSPECTION', 'Final inspection', 80, true, 'Release inspection before packing.'],
            ];
            foreach ($stages as [$code, $name, $seq, $gate, $desc]) {
                DB::table('production_stage_definitions')->insert([
                    'id' => (string) Str::uuid(), 'code' => $code, 'name' => $name, 'sequence' => $seq, 'is_quality_gate' => $gate,
                    'requires_equipment' => false, 'requires_quantity_confirmation' => true, 'is_active' => true, 'description' => $desc,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        if (DB::table('quality_defects')->count() === 0) {
            // Common iron casting defects, grouped by cause. Severity is the starting point and can be changed per NCR.
            $defects = [
                ['SAND-INCL', 'Sand inclusion', 'SAND', 'MAJOR', 'Loose sand trapped in the casting. Check sand strength, mould hardness, and cleanliness of the runner system.'],
                ['SCAB', 'Scab / expansion defect', 'SAND', 'MAJOR', 'Raised rough area where the mould surface lifted. Check sand expansion, moisture and compactability.'],
                ['EROSION', 'Cut and wash / erosion', 'SAND', 'MAJOR', 'Metal stream eroded the mould. Check gating velocity and mould hardness.'],
                ['MOULD-CRUSH', 'Mould crush / drop', 'SAND', 'MAJOR', 'Mould or core damaged during closing or handling.'],
                ['BLOWHOLE', 'Blowhole', 'GAS', 'MAJOR', 'Smooth round cavity from trapped gas. Check venting, core gas, sand moisture and pouring rate.'],
                ['PINHOLE', 'Pinholes / subsurface porosity', 'GAS', 'MAJOR', 'Small gas holes near the surface. Check hydrogen and nitrogen pick-up, coating and moisture.'],
                ['SHRINK-CAV', 'Shrinkage cavity', 'SHRINKAGE', 'CRITICAL', 'Rough cavity in a hot spot. Check feeding, riser size, inoculation and carbon equivalent.'],
                ['MICRO-SHRINK', 'Micro-shrinkage / porosity', 'SHRINKAGE', 'MAJOR', 'Dispersed fine porosity. Check carbon equivalent, magnesium and mould rigidity.'],
                ['SINK', 'Sink / surface depression', 'SHRINKAGE', 'MINOR', 'Shallow depression over a hot spot.'],
                ['CHILL-CARBIDE', 'Carbides / chill', 'METALLURGICAL', 'CRITICAL', 'Hard white iron in thin sections. Check inoculation, TEmin and carbon equivalent; confirm with thermal analysis.'],
                ['LOW-NODULARITY', 'Low nodularity / degenerate graphite', 'METALLURGICAL', 'CRITICAL', 'Graphite not spheroidal. Check magnesium, residual sulphur, treatment recovery and fade time.'],
                ['MG-FADE', 'Magnesium fade', 'METALLURGICAL', 'CRITICAL', 'Nodularity lost because the time from treatment to pouring was too long.'],
                ['HARDNESS', 'Hardness out of range', 'METALLURGICAL', 'MAJOR', 'Matrix is off specification. Check pearlite/ferrite balance, Cu, Mn and heat treatment.'],
                ['COLD-SHUT', 'Cold shut', 'POURING', 'MAJOR', 'Two metal streams failed to fuse. Check pouring temperature and pouring speed.'],
                ['MISRUN', 'Misrun', 'POURING', 'CRITICAL', 'Casting not completely filled. Check pouring temperature, fluidity and gating.'],
                ['SLAG-DROSS', 'Slag / dross inclusion', 'POURING', 'MAJOR', 'Non-metallic inclusion. Check skimming, filter use and ladle condition.'],
                ['CORE-SHIFT', 'Core shift', 'DIMENSIONAL', 'MAJOR', 'Core moved, giving uneven wall thickness.'],
                ['MOULD-SHIFT', 'Mould shift / mismatch', 'DIMENSIONAL', 'MAJOR', 'Cope and drag not aligned.'],
                ['SWELL', 'Swell / oversize', 'DIMENSIONAL', 'MINOR', 'Mould wall movement from soft mould or high metal pressure.'],
                ['DIM-OOT', 'Dimension out of tolerance', 'DIMENSIONAL', 'MAJOR', 'A measured dimension is outside the drawing tolerance.'],
                ['HOT-TEAR', 'Hot tear / crack', 'CRACK', 'CRITICAL', 'Crack formed while the casting was still weak. Check restraint and section changes.'],
                ['COLD-CRACK', 'Cold crack', 'CRACK', 'CRITICAL', 'Crack from stress after solidification, often from early shakeout or heavy fettling.'],
                ['GRIND-DAMAGE', 'Grinding damage', 'FETTLING', 'MINOR', 'Metal removed beyond the limit or surface burned during fettling.'],
                ['SURFACE-ROUGH', 'Rough surface / burn-on', 'SURFACE', 'MINOR', 'Sand burnt on to the surface. Check coating and sand fineness.'],
                ['COATING-DEFECT', 'Coating defect', 'SURFACE', 'MINOR', 'Paint or primer thickness, adhesion or coverage is not acceptable.'],
            ];
            foreach ($defects as [$code, $name, $cat, $sev, $guide]) {
                DB::table('quality_defects')->insert([
                    'id' => (string) Str::uuid(), 'code' => $code, 'name' => $name, 'category' => $cat, 'default_severity' => $sev,
                    'inspection_guidance' => $guide, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('quality_defects')->whereIn('code', ['SAND-INCL', 'SCAB', 'EROSION', 'MOULD-CRUSH', 'BLOWHOLE', 'PINHOLE', 'SHRINK-CAV', 'MICRO-SHRINK', 'SINK', 'CHILL-CARBIDE', 'LOW-NODULARITY', 'MG-FADE', 'HARDNESS', 'COLD-SHUT', 'MISRUN', 'SLAG-DROSS', 'CORE-SHIFT', 'MOULD-SHIFT', 'SWELL', 'DIM-OOT', 'HOT-TEAR', 'COLD-CRACK', 'GRIND-DAMAGE', 'SURFACE-ROUGH', 'COATING-DEFECT'])->delete();
        DB::table('production_stage_definitions')->whereIn('code', ['SHAKEOUT', 'CUTTING', 'SHOT_BLAST', 'FETTLING', 'HEAT_TREATMENT', 'MACHINING', 'COATING', 'FINAL_INSPECTION'])->delete();
    }
};
