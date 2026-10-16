
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_locations', function (Blueprint $table) {
            $table->foreign('parent_location_id')
                ->references('id')
                ->on('stock_locations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_locations', function (Blueprint $table) {
            $table->dropForeign(['parent_location_id']);
        });
    }
};