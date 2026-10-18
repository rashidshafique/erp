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
        Schema::table('patterns', function (Blueprint $table) {
            $table->string('condition_grade', 1)->nullable();
            $table->date('condition_graded_on')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            $table->dropColumn(['condition_grade', 'condition_graded_on']);
        });
    }
};
