
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sand_recipe_items', function (Blueprint $table) {
            $table->foreignUuid('material_id')
                ->nullable()
                ->constrained('materials')
                ->restrictOnDelete();

            $table->index('material_id');
        });

        // Match existing recipe ingredients to material master records
        // by material code. Unmatched rows remain unchanged and nullable.
        DB::statement("
            UPDATE sand_recipe_items AS sri
            SET material_id = m.id
            FROM materials AS m
            WHERE sri.material_code = m.code
              AND sri.material_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('sand_recipe_items', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
            $table->dropIndex(['material_id']);
            $table->dropColumn('material_id');
        });
    }
};