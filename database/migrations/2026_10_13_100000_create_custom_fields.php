<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables that can carry user-defined descriptive fields. */
    private array $tables = ['heats', 'patterns', 'products', 'sales_orders', 'moulding_runs', 'pours', 'casting_lots', 'nonconformance_reports', 'quality_inspections'];

    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('entity_type', 40);
            $t->string('field_key', 60);
            $t->string('label', 120);
            $t->string('data_type', 12);
            $t->json('options')->nullable();
            $t->string('unit', 20)->nullable();
            $t->decimal('min_value', 14, 4)->nullable();
            $t->decimal('max_value', 14, 4)->nullable();
            $t->boolean('is_required')->default(false);
            $t->string('help_text', 255)->nullable();
            $t->smallInteger('sort_order')->default(100);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['entity_type', 'field_key']);
        });
        DB::statement("ALTER TABLE custom_field_definitions ADD CONSTRAINT custom_field_type_chk CHECK (data_type IN ('TEXT','NUMBER','BOOLEAN','DATE','SELECT'))");

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'custom_fields')) {
                DB::statement("ALTER TABLE \"{$table}\" ADD COLUMN custom_fields jsonb NOT NULL DEFAULT '{}'::jsonb");
                DB::statement("CREATE INDEX \"{$table}_custom_fields_gin\" ON \"{$table}\" USING gin (custom_fields)");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'custom_fields')) {
                DB::statement("DROP INDEX IF EXISTS \"{$table}_custom_fields_gin\"");
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('custom_fields'));
            }
        }
        Schema::dropIfExists('custom_field_definitions');
    }
};
