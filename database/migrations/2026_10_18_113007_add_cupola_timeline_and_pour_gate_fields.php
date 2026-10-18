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
        Schema::table('heats', function (Blueprint $table) {
            $table->timestamp('blast_on_at')->nullable();
            $table->timestamp('first_tap_at')->nullable();
            $table->timestamp('dropped_at')->nullable();
        });
        Schema::table('pours', function (Blueprint $table) {
            $table->string('gate_mode', 10)->nullable();
            $table->text('gate_note')->nullable();
            $table->boolean('is_first_iron')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pours', fn (Blueprint $table) => $table->dropColumn(['gate_mode', 'gate_note', 'is_first_iron']));
        Schema::table('heats', fn (Blueprint $table) => $table->dropColumn(['blast_on_at', 'first_tap_at', 'dropped_at']));
    }
};
