<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heats', function (Blueprint $table) {
            $table->string('hold_reason', 20)->nullable();
            $table->string('hold_note', 500)->nullable();
            $table->timestamp('held_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('heats', fn (Blueprint $table) => $table->dropColumn(['hold_reason', 'hold_note', 'held_at']));
    }
};
