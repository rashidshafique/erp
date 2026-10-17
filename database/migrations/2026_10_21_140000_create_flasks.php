<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flasks as tooling of their own. A flask size is a family of interchangeable boxes; each flask is one physical box with
 * its own stamped mark, position (drag, cope, cheek or stack section), status and use count. Stations list the sizes they
 * handle; pattern revisions list the sizes they fit, one of them the size they were designed for. Mould layers record the
 * flask they were made in. Flaskless lines (DISAMATIC, DISA Match) make moulds with no flask at all; at pouring only a cover
 * and weights may be used, noted on the pour line.
 */
return new class extends Migration
{
    private const TOOLING_COLUMNS = ['pattern_id', 'pattern_revision_id', 'core_definition_id', 'core_box_id', 'mould_configuration_id', 'mould_assembly_id', 'flask_id'];

    public function up(): void
    {
        Schema::create('flask_sizes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->decimal('inside_length_mm', 10, 2)->nullable();
            $table->decimal('inside_width_mm', 10, 2)->nullable();
            $table->decimal('cope_depth_mm', 10, 2)->nullable();
            $table->decimal('drag_depth_mm', 10, 2)->nullable();
            $table->decimal('pin_centres_mm', 10, 2)->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('flasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('mark_code', 20)->nullable();
            $table->foreignUuid('flask_size_id')->constrained('flask_sizes')->restrictOnDelete();
            $table->string('position', 20)->default('ANY');
            $table->string('construction', 20)->nullable();
            $table->string('material', 50)->nullable();
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->string('location', 100)->nullable();
            $table->uuid('partner_flask_id')->nullable();
            $table->string('status', 20)->default('AVAILABLE');
            $table->foreignUuid('current_mould_assembly_id')->nullable()->constrained('mould_assemblies')->nullOnDelete();
            $table->foreignUuid('current_moulding_run_id')->nullable()->constrained('moulding_runs')->nullOnDelete();
            $table->unsignedInteger('cycles')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['flask_size_id', 'position', 'status']);
        });
        Schema::table('flasks', fn (Blueprint $table) => $table->foreign('partner_flask_id')->references('id')->on('flasks')->nullOnDelete());
        DB::statement("ALTER TABLE flasks ADD CONSTRAINT flasks_position_valid CHECK (position IN ('DRAG','COPE','CHEEK','STACK','ANY'))");
        DB::statement("ALTER TABLE flasks ADD CONSTRAINT flasks_status_valid CHECK (status IN ('AVAILABLE','IN_MOULD','REPAIR','RETIRED','LOST'))");
        DB::statement("ALTER TABLE flasks ADD CONSTRAINT flasks_in_mould_has_mould CHECK (status <> 'IN_MOULD' OR current_mould_assembly_id IS NOT NULL OR current_moulding_run_id IS NOT NULL)");

        Schema::create('flask_size_moulding_station', function (Blueprint $table) {
            $table->foreignUuid('moulding_station_id')->constrained('moulding_stations')->cascadeOnDelete();
            $table->foreignUuid('flask_size_id')->constrained('flask_sizes')->cascadeOnDelete();
            $table->primary(['moulding_station_id', 'flask_size_id']);
        });
        Schema::create('flask_size_pattern_revision', function (Blueprint $table) {
            $table->foreignUuid('pattern_revision_id')->constrained('pattern_revisions')->cascadeOnDelete();
            $table->foreignUuid('flask_size_id')->constrained('flask_sizes')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->primary(['pattern_revision_id', 'flask_size_id']);
        });
        DB::statement('CREATE UNIQUE INDEX flask_size_pattern_revision_one_primary ON flask_size_pattern_revision (pattern_revision_id) WHERE is_primary');

        Schema::table('moulding_stations', function (Blueprint $table) {
            $table->boolean('makes_flaskless')->default(false);
        });
        Schema::table('mould_layers', function (Blueprint $table) {
            $table->foreignUuid('flask_id')->nullable()->constrained('flasks')->nullOnDelete();
        });
        Schema::table('mould_assemblies', function (Blueprint $table) {
            $table->boolean('is_flaskless')->default(false);
            $table->foreignUuid('flask_size_id')->nullable()->constrained('flask_sizes')->nullOnDelete();
        });
        Schema::table('pour_mould_assemblies', function (Blueprint $table) {
            $table->string('cover_reference', 40)->nullable();
            $table->unsignedSmallInteger('weights_count')->nullable();
        });

        // Flasks join the tooling life, usage and maintenance records.
        foreach (['tooling_life_limits', 'tooling_usage_records', 'tooling_maintenance_records'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignUuid('flask_id')->nullable()->constrained('flasks')->cascadeOnDelete();
            });
            DB::statement("ALTER TABLE {$t} DROP CONSTRAINT IF EXISTS {$t}_exactly_one_tooling_check");
            DB::statement("ALTER TABLE {$t} ADD CONSTRAINT {$t}_exactly_one_tooling_check CHECK (num_nonnulls(".implode(', ', self::TOOLING_COLUMNS).') = 1)');
        }
        DB::statement('CREATE UNIQUE INDEX tooling_life_limits_flask_id_unique ON tooling_life_limits (flask_id) WHERE flask_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tooling_life_limits_flask_id_unique');
        $without = array_diff(self::TOOLING_COLUMNS, ['flask_id']);
        foreach (['tooling_life_limits', 'tooling_usage_records', 'tooling_maintenance_records'] as $t) {
            DB::table($t)->whereNotNull('flask_id')->delete();
            DB::statement("ALTER TABLE {$t} DROP CONSTRAINT IF EXISTS {$t}_exactly_one_tooling_check");
            Schema::table($t, fn (Blueprint $table) => $table->dropConstrainedForeignId('flask_id'));
            DB::statement("ALTER TABLE {$t} ADD CONSTRAINT {$t}_exactly_one_tooling_check CHECK (num_nonnulls(".implode(', ', $without).') = 1)');
        }
        Schema::table('pour_mould_assemblies', fn (Blueprint $table) => $table->dropColumn(['cover_reference', 'weights_count']));
        Schema::table('mould_assemblies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flask_size_id');
            $table->dropColumn('is_flaskless');
        });
        Schema::table('mould_layers', fn (Blueprint $table) => $table->dropConstrainedForeignId('flask_id'));
        Schema::table('moulding_stations', fn (Blueprint $table) => $table->dropColumn('makes_flaskless'));
        Schema::dropIfExists('flask_size_pattern_revision');
        Schema::dropIfExists('flask_size_moulding_station');
        Schema::dropIfExists('flasks');
        Schema::dropIfExists('flask_sizes');
    }
};
