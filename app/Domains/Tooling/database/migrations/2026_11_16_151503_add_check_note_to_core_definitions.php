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
        Schema::table('core_definitions', function (Blueprint $table) {
            $table->string('check_note', 500)->nullable()->after('engineering_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('core_definitions', function (Blueprint $table) {
            $table->dropColumn('check_note');
        });
    }
};
