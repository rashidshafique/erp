<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('moulding_work_list_items', function (Blueprint $table) {
            $table->uuid('remake_of_run_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moulding_work_list_items', function (Blueprint $table) {
            $table->dropColumn('remake_of_run_id');
        });
    }
};
