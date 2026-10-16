<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iron_grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('family', 12);          // GREY, DUCTILE, CGI, WHITE, ALLOY
            $table->string('standard_code', 40)->nullable();
            $table->unsignedSmallInteger('min_tensile_mpa')->nullable();
            $table->unsignedSmallInteger('min_yield_mpa')->nullable();
            $table->decimal('min_elongation_pct', 5, 1)->nullable();
            foreach (['c', 'si', 'mn', 'p', 's', 'cr', 'cu', 'mo', 'mg'] as $el) {
                $table->decimal("{$el}_min", 6, 3)->nullable();
                $table->decimal("{$el}_max", 6, 3)->nullable();
            }
            $table->decimal('ce_min', 5, 2)->nullable();
            $table->decimal('ce_max', 5, 2)->nullable();
            $table->unsignedSmallInteger('tap_temp_min_c')->nullable();
            $table->unsignedSmallInteger('tap_temp_max_c')->nullable();
            $table->unsignedSmallInteger('pour_temp_min_c')->nullable();
            $table->unsignedSmallInteger('pour_temp_max_c')->nullable();
            $table->boolean('needs_mg_treatment')->default(false);
            $table->boolean('needs_inoculation')->default(true);
            $table->string('status', 12)->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE iron_grades ADD CONSTRAINT iron_grades_family_valid CHECK (family IN ('GREY','DUCTILE','CGI','WHITE','ALLOY'))");

        Schema::create('iron_grade_aliases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('iron_grade_id')->constrained('iron_grades')->cascadeOnDelete();
            $table->string('standard', 30);        // e.g. ASTM A48, ISO 185, IS 210, JIS G5501
            $table->string('designation', 40);     // e.g. Class 35B, JL/250, FG 260
            $table->boolean('is_exact')->default(false);
            $table->string('normalized', 60)->index();
            $table->timestamps();
            $table->unique(['standard', 'designation']);
        });

        Schema::create('furnace_grade_capabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('furnace_id')->constrained('furnaces')->cascadeOnDelete();
            $table->foreignUuid('iron_grade_id')->constrained('iron_grades')->cascadeOnDelete();
            $table->boolean('allowed')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['furnace_id', 'iron_grade_id']);
        });

        Schema::create('thermal_windows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('iron_grade_id')->constrained('iron_grades')->cascadeOnDelete();
            $table->string('cup_type', 20)->default('STABLE');
            $table->decimal('tl_min_c', 6, 1)->nullable();
            $table->decimal('tl_max_c', 6, 1)->nullable();
            $table->decimal('ce_min', 5, 2)->nullable();
            $table->decimal('ce_max', 5, 2)->nullable();
            $table->decimal('te_min_floor_c', 6, 1)->nullable();
            $table->decimal('recal_min_c', 5, 1)->nullable();
            $table->decimal('recal_max_c', 5, 1)->nullable();
            $table->decimal('solidus_min_c', 6, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['iron_grade_id', 'cup_type']);
        });

        Schema::create('thermal_calibrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cup_type', 20)->default('STABLE');
            $table->decimal('tl_intercept_c', 8, 3);
            $table->decimal('tl_slope_c_per_ce', 8, 3);
            $table->decimal('inoculant_gain_c_per_0p1pct', 6, 2)->nullable();
            $table->unsignedInteger('sample_count');
            $table->decimal('r_squared', 6, 4)->nullable();
            $table->decimal('residual_sd_ce', 6, 4)->nullable();
            $table->timestamp('fitted_at');
            $table->timestamps();
            $table->index(['cup_type', 'fitted_at']);
        });

        Schema::create('thermal_analysis_tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('heat_id')->constrained('heats')->restrictOnDelete();
            $table->foreignUuid('iron_grade_id')->nullable()->constrained('iron_grades')->restrictOnDelete();
            $table->string('sample_reference', 60)->nullable();
            $table->string('stage', 20)->default('POST_TREATMENT');   // BASE, POST_TREATMENT, PRE_POUR
            $table->string('cup_type', 20)->default('STABLE');
            $table->timestamp('taken_at');
            $table->string('taken_by', 100)->nullable();
            $table->json('curve');                                    // [[t_seconds, temp_c], ...]
            $table->decimal('tl_c', 6, 1)->nullable();
            $table->decimal('te_min_c', 6, 1)->nullable();
            $table->decimal('te_max_c', 6, 1)->nullable();
            $table->decimal('recalescence_c', 5, 1)->nullable();
            $table->decimal('solidus_c', 6, 1)->nullable();
            $table->decimal('ce_from_tl', 5, 2)->nullable();
            $table->decimal('carbon_from_tl', 5, 2)->nullable();
            $table->decimal('silicon_used', 5, 2)->nullable();
            $table->string('result', 12)->default('NOT_JUDGED');     // PASS, FAIL, NOT_JUDGED
            $table->json('findings')->nullable();
            $table->decimal('inoculant_added_pct', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['heat_id', 'taken_at']);
        });
        DB::statement("ALTER TABLE thermal_analysis_tests ADD CONSTRAINT thermal_result_valid CHECK (result IN ('PASS','FAIL','NOT_JUDGED'))");

        Schema::table('heats', function (Blueprint $table) {
            $table->foreignUuid('iron_grade_id')->nullable()->after('charge_batch_id')->constrained('iron_grades')->restrictOnDelete();
        });

        $this->seedGrades();
    }

    public function down(): void
    {
        Schema::table('heats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('iron_grade_id');
        });
        foreach (['thermal_analysis_tests', 'thermal_calibrations', 'thermal_windows', 'furnace_grade_capabilities', 'iron_grade_aliases', 'iron_grades'] as $t) {
            Schema::dropIfExists($t);
        }
    }

    /**
     * Starting chemistry windows. These are generic ductile/grey iron practice values and
     * MUST be reviewed against your own control plans and the customer's specification.
     */
    private function seedGrades(): void
    {
        $g = [
            ['EN-GJL-200', 'Grey iron EN-GJL-200', 'GREY', 'EN 1561', 200, null, null, ['c' => [3.2, 3.6], 'si' => [1.8, 2.5], 'mn' => [0.4, 0.8], 'p' => [null, 0.15], 's' => [null, 0.12]], [3.9, 4.4], false,
                [['ASTM A48', 'Class 30B'], ['ISO 185', 'JL/200'], ['IS 210', 'FG 200'], ['JIS G5501', 'FC200']]],
            ['EN-GJL-250', 'Grey iron EN-GJL-250', 'GREY', 'EN 1561', 250, null, null, ['c' => [3.1, 3.4], 'si' => [1.6, 2.3], 'mn' => [0.5, 0.9], 'p' => [null, 0.15], 's' => [null, 0.12]], [3.7, 4.1], false,
                [['ASTM A48', 'Class 35B'], ['ISO 185', 'JL/250'], ['IS 210', 'FG 260'], ['JIS G5501', 'FC250']]],
            ['EN-GJL-300', 'Grey iron EN-GJL-300', 'GREY', 'EN 1561', 300, null, null, ['c' => [2.9, 3.3], 'si' => [1.4, 2.1], 'mn' => [0.6, 1.0], 'p' => [null, 0.12], 's' => [null, 0.12]], [3.5, 3.9], false,
                [['ASTM A48', 'Class 40B'], ['ISO 185', 'JL/300'], ['IS 210', 'FG 300'], ['JIS G5501', 'FC300']]],
            ['EN-GJS-400-15', 'Ductile iron EN-GJS-400-15', 'DUCTILE', 'EN 1563', 400, 250, 15.0, ['c' => [3.5, 3.9], 'si' => [2.2, 2.9], 'mn' => [null, 0.30], 'p' => [null, 0.05], 's' => [null, 0.015], 'mg' => [0.030, 0.060]], [4.3, 4.7], true,
                [['ASTM A536', '60-40-18'], ['ISO 1083', 'JS/400-15'], ['IS 1865', 'SG 400/15'], ['JIS G5502', 'FCD400']]],
            ['EN-GJS-500-7', 'Ductile iron EN-GJS-500-7', 'DUCTILE', 'EN 1563', 500, 320, 7.0, ['c' => [3.5, 3.8], 'si' => [2.2, 2.8], 'mn' => [null, 0.50], 'p' => [null, 0.05], 's' => [null, 0.015], 'cu' => [0.2, 0.6], 'mg' => [0.030, 0.060]], [4.3, 4.6], true,
                [['ASTM A536', '70-50-05'], ['ISO 1083', 'JS/500-7'], ['IS 1865', 'SG 500/7'], ['JIS G5502', 'FCD500']]],
            ['EN-GJS-600-3', 'Ductile iron EN-GJS-600-3', 'DUCTILE', 'EN 1563', 600, 370, 3.0, ['c' => [3.5, 3.8], 'si' => [2.2, 2.7], 'mn' => [null, 0.60], 'p' => [null, 0.05], 's' => [null, 0.015], 'cu' => [0.4, 0.8], 'mg' => [0.030, 0.060]], [4.3, 4.6], true,
                [['ASTM A536', '80-55-06'], ['ISO 1083', 'JS/600-3'], ['IS 1865', 'SG 600/3'], ['JIS G5502', 'FCD600']]],
            ['EN-GJS-700-2', 'Ductile iron EN-GJS-700-2', 'DUCTILE', 'EN 1563', 700, 420, 2.0, ['c' => [3.5, 3.8], 'si' => [2.1, 2.6], 'mn' => [null, 0.60], 'p' => [null, 0.05], 's' => [null, 0.015], 'cu' => [0.6, 1.0], 'mg' => [0.030, 0.060]], [4.3, 4.6], true,
                [['ASTM A536', '100-70-03'], ['ISO 1083', 'JS/700-2'], ['IS 1865', 'SG 700/2'], ['JIS G5502', 'FCD700']]],
        ];

        foreach ($g as [$code, $name, $family, $std, $ts, $ys, $el, $chem, $ce, $mg, $aliases]) {
            $id = (string) Str::uuid();
            $row = [
                'id' => $id, 'code' => $code, 'name' => $name, 'family' => $family, 'standard_code' => $std,
                'min_tensile_mpa' => $ts, 'min_yield_mpa' => $ys, 'min_elongation_pct' => $el,
                'ce_min' => $ce[0], 'ce_max' => $ce[1],
                'needs_mg_treatment' => $mg, 'needs_inoculation' => true, 'status' => 'ACTIVE',
                'notes' => 'Starting window: review against your control plan and the customer specification.',
                'created_at' => now(), 'updated_at' => now(),
            ];
            foreach ($chem as $k => [$lo, $hi]) {
                $row["{$k}_min"] = $lo;
                $row["{$k}_max"] = $hi;
            }
            DB::table('iron_grades')->insert($row);

            DB::table('iron_grade_aliases')->insert([
                'id' => (string) Str::uuid(), 'iron_grade_id' => $id, 'standard' => $std, 'designation' => str_replace('EN-', '', $code),
                'is_exact' => true, 'normalized' => self::normalize($code), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($aliases as [$s, $d]) {
                DB::table('iron_grade_aliases')->insert([
                    'id' => (string) Str::uuid(), 'iron_grade_id' => $id, 'standard' => $s, 'designation' => $d,
                    'is_exact' => false,   // nearest equivalent only; confirm against the contract
                    'normalized' => self::normalize($d), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public static function normalize(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));

        return preg_replace('/^EN/', '', $s);
    }
};
