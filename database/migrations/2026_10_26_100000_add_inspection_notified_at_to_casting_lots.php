<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casting_lots', function (Blueprint $table) {
            $table->timestampTz('inspection_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('casting_lots', function (Blueprint $table) {
            $table->dropColumn('inspection_notified_at');
        });
    }
};
