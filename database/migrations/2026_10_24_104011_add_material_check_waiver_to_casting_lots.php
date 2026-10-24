<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casting_lots', function (Blueprint $table) {
            $table->timestampTz('material_waived_at')->nullable();
            $table->string('material_waived_by', 100)->nullable();
            $table->string('material_waiver_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('casting_lots', function (Blueprint $table) {
            $table->dropColumn(['material_waived_at', 'material_waived_by', 'material_waiver_reason']);
        });
    }
};
