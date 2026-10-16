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
        Schema::create('characteristic_limits', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('subject_type', 20);            // IRON_GRADE | SAND_SYSTEM
            $t->uuid('subject_id');
            $t->string('test_group', 20);              // MECHANICAL | MICROSTRUCTURE | SAND
            $t->string('characteristic_key', 60);
            $t->string('name', 120);
            $t->string('unit', 20)->nullable();
            $t->decimal('min_value', 14, 4)->nullable();
            $t->decimal('max_value', 14, 4)->nullable();
            $t->boolean('is_required_for_release')->default(false);
            $t->smallInteger('sort_order')->default(100);
            $t->timestamps();
            $t->unique(['subject_type', 'subject_id', 'characteristic_key']);
            $t->index(['subject_type', 'subject_id']);
        });
        DB::statement("ALTER TABLE characteristic_limits ADD CONSTRAINT char_limit_subject_chk CHECK (subject_type IN ('IRON_GRADE','SAND_SYSTEM'))");
        DB::statement("ALTER TABLE characteristic_limits ADD CONSTRAINT char_limit_range_chk CHECK (min_value IS NULL OR max_value IS NULL OR min_value <= max_value)");

        Schema::create('measurement_sets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('kind', 20);                    // MECHANICAL | MICROSTRUCTURE | SAND
            $t->foreignUuid('heat_id')->nullable()->constrained('heats')->nullOnDelete();
            $t->uuid('iron_grade_id')->nullable();
            $t->uuid('sand_system_id')->nullable();
            $t->uuid('moulding_run_id')->nullable();
            $t->uuid('moulding_station_id')->nullable();
            $t->string('sample_reference', 80)->nullable();
            $t->string('sample_type', 30)->nullable();
            $t->string('sample_point', 40)->nullable();
            $t->string('shift', 30)->nullable();
            $t->timestampTz('taken_at');
            $t->string('tested_by', 100)->nullable();
            $t->string('equipment_reference', 100)->nullable();
            $t->string('result', 12)->default('NOT_JUDGED');   // PASS | FAIL | NOT_JUDGED
            $t->json('findings')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['kind', 'taken_at']);
            $t->index('heat_id');
            $t->index('sand_system_id');
        });
        DB::statement("ALTER TABLE measurement_sets ADD CONSTRAINT meas_set_kind_chk CHECK (kind IN ('MECHANICAL','MICROSTRUCTURE','SAND'))");
        DB::statement("ALTER TABLE measurement_sets ADD CONSTRAINT meas_set_result_chk CHECK (result IN ('PASS','FAIL','NOT_JUDGED'))");

        Schema::create('measurements', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('measurement_set_id')->constrained('measurement_sets')->cascadeOnDelete();
            $t->string('characteristic_key', 60);
            $t->string('name', 120);
            $t->string('unit', 20)->nullable();
            $t->decimal('value', 14, 4)->nullable();
            $t->string('text_value', 200)->nullable();
            $t->decimal('min_value', 14, 4)->nullable();   // limits as they stood on the day
            $t->decimal('max_value', 14, 4)->nullable();
            $t->boolean('passed')->nullable();
            $t->smallInteger('sort_order')->default(100);
            $t->timestamps();
            $t->index(['measurement_set_id', 'characteristic_key']);
        });

        // Starting requirements from what each grade already states (not enforced until switched on).
        foreach (DB::table('iron_grades')->get() as $g) {
            $rows = [['tensile_mpa', 'Tensile strength Rm', 'MPa', $g->min_tensile_mpa, null, 10], ['yield_mpa', 'Yield strength Rp0.2', 'MPa', $g->min_yield_mpa, null, 20], ['elongation_pct', 'Elongation A', '%', $g->min_elongation_pct, null, 30]];
            foreach ($rows as [$k, $n, $u, $min, $max, $o]) {
                if ($min !== null) {
                    DB::table('characteristic_limits')->insert(['id' => (string) Str::uuid(), 'subject_type' => 'IRON_GRADE', 'subject_id' => $g->id, 'test_group' => 'MECHANICAL',
                        'characteristic_key' => $k, 'name' => $n, 'unit' => $u, 'min_value' => $min, 'max_value' => $max, 'is_required_for_release' => false, 'sort_order' => $o, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
        Schema::dropIfExists('measurement_sets');
        Schema::dropIfExists('characteristic_limits');
    }
};
