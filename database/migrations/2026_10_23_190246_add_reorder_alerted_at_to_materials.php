<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When the reorder alert for a material was raised; cleared once stock is back above its minimum, so each dip alerts once. */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->timestampTz('reorder_alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('reorder_alerted_at');
        });
    }
};
