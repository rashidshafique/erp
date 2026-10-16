
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_inspection_results', function (Blueprint $table) {
            $table->foreignUuid('inspection_plan_item_id')
                ->nullable()
                ->after('quality_inspection_id')
                ->constrained('inspection_plan_items')
                ->restrictOnDelete();

            $table->index(
                'inspection_plan_item_id',
                'qir_inspection_plan_item_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('quality_inspection_results', function (Blueprint $table) {
            $table->dropIndex('qir_inspection_plan_item_index');
            $table->dropForeign(['inspection_plan_item_id']);
            $table->dropColumn('inspection_plan_item_id');
        });
    }
};