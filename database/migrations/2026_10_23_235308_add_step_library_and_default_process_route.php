<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Production stages become a library of steps with a kind and behaviours, and the foundry's process becomes its default route:
 * the active stages in their present order. Nothing changes on the floor; stages keep their order and on/off state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_stage_definitions', function (Blueprint $table) {
            $table->string('kind', 20)->default('PROCESS')->after('name');
            $table->boolean('knocks_out_mould')->default(false)->after('is_quality_gate');
            $table->boolean('records_weight')->default(false)->after('knocks_out_mould');
        });
        DB::table('production_stage_definitions')->where('code', 'SHAKEOUT')->update(['knocks_out_mould' => true]);
        DB::table('production_stage_definitions')->where(fn ($q) => $q->where('is_quality_gate', true)->orWhere('code', 'FINAL_INSPECTION'))->update(['kind' => 'INSPECTION']);

        Schema::table('process_routes', function (Blueprint $table) {
            $table->uuid('manufacturing_method_id')->nullable()->change();
            $table->string('applies_to', 20)->default('PRODUCT')->after('manufacturing_method_id');
            $table->index('applies_to');
        });
        Schema::table('operations', function (Blueprint $table) {
            $table->uuid('work_center_id')->nullable()->change();
            $table->foreignUuid('stage_definition_id')->nullable()->after('work_center_id')->constrained('production_stage_definitions')->restrictOnDelete();
        });

        $routeId = (string) Str::uuid();
        DB::table('process_routes')->insert([
            'id' => $routeId, 'manufacturing_method_id' => null, 'applies_to' => 'DEFAULT', 'code' => 'DEFAULT', 'name' => 'Our process', 'revision' => 'A',
            'status' => 'ACTIVE', 'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $position = 0;
        foreach (DB::table('production_stage_definitions')->where('is_active', true)->orderBy('sequence')->get() as $stage) {
            DB::table('operations')->insert([
                'id' => (string) Str::uuid(), 'process_route_id' => $routeId, 'stage_definition_id' => $stage->id, 'sequence' => ++$position,
                'code' => $stage->code, 'name' => $stage->name, 'operation_type' => $stage->kind, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $default = DB::table('process_routes')->where('applies_to', 'DEFAULT')->pluck('id');
        DB::table('operations')->whereIn('process_route_id', $default)->delete();
        DB::table('process_routes')->whereIn('id', $default)->delete();

        Schema::table('operations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stage_definition_id');
        });
        Schema::table('process_routes', function (Blueprint $table) {
            $table->dropIndex(['applies_to']);
            $table->dropColumn('applies_to');
        });
        Schema::table('production_stage_definitions', function (Blueprint $table) {
            $table->dropColumn(['kind', 'knocks_out_mould', 'records_weight']);
        });
    }
};
