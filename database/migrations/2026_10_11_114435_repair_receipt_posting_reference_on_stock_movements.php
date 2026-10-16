<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stock_movements', 'receipt_lot_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->uuid('receipt_lot_id')->nullable()->unique();

                $table->foreign('receipt_lot_id')
                    ->references('id')
                    ->on('material_lots')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_movements', 'receipt_lot_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropForeign(['receipt_lot_id']);
                $table->dropUnique(['receipt_lot_id']);
                $table->dropColumn('receipt_lot_id');
            });
        }
    }
};