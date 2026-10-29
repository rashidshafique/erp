<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What the quote allowed for the pattern, and what it actually cost, so a development shows whether the tooling was priced right. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pattern_developments', function (Blueprint $table) {
            $table->decimal('quoted_tooling_cost', 14, 2)->nullable()->after('who_pays');
            $table->decimal('actual_cost', 14, 2)->nullable()->after('quoted_tooling_cost');
            $table->string('cost_currency', 3)->nullable()->after('actual_cost');
        });
    }

    public function down(): void
    {
        Schema::table('pattern_developments', function (Blueprint $table) {
            $table->dropColumn(['quoted_tooling_cost', 'actual_cost', 'cost_currency']);
        });
    }
};
