<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A row on the day's moulding list can name the unfinished row of an earlier list it carries over. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moulding_work_list_items', function (Blueprint $table) {
            $table->foreignUuid('carried_from_item_id')->nullable()->after('remake_of_run_id')->constrained('moulding_work_list_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('moulding_work_list_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carried_from_item_id');
        });
    }
};
