
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->unique(
                ['id', 'material_id'],
                'material_lots_id_material_id_unique'
            );
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign(
                ['material_lot_id', 'material_id'],
                'stock_movements_lot_material_consistency_fk'
            )->references(['id', 'material_id'])
                ->on('material_lots')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(
                'stock_movements_lot_material_consistency_fk'
            );
        });

        Schema::table('material_lots', function (Blueprint $table) {
            $table->dropUnique(
                'material_lots_id_material_id_unique'
            );
        });
    }
};