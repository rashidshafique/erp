<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** What a bill line is treated as (design 28g): cost of an asset in progress, or of a pattern development. No foreign keys: assets and developments are their own modules. */
    public function up(): void
    {
        Schema::table('supplier_bill_lines', function (Blueprint $table) {
            $table->uuid('fixed_asset_id')->nullable()->index();
            $table->uuid('pattern_development_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bill_lines', function (Blueprint $table) {
            $table->dropColumn(['fixed_asset_id', 'pattern_development_id']);
        });
    }
};
