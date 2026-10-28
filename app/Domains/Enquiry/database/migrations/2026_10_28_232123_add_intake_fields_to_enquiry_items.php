<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What the enquiry intake screen (design 1b) asks beyond the part: process, supply condition, call-offs, start of production and the quality requirements. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiry_items', function (Blueprint $table) {
            $table->string('process', 20)->nullable();
            $table->string('supply_condition', 40)->nullable();
            $table->string('call_off', 60)->nullable();
            $table->date('sop_on')->nullable();
            $table->unsignedTinyInteger('programme_years')->nullable();
            $table->json('quality')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('enquiry_items', fn (Blueprint $table) => $table->dropColumn(['process', 'supply_condition', 'call_off', 'sop_on', 'programme_years', 'quality']));
    }
};
