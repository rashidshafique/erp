<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A customer that asks for PPAP from the start says so when the account is created, and with the submission level (1 to 5). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('ppap_requested')->default(false);
            $table->unsignedTinyInteger('ppap_level')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['ppap_requested', 'ppap_level']));
    }
};
