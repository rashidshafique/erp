<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How much a customer may owe (no limit when empty), a hold that stops new orders and dispatches outright, and a release that
 * lets them through anyway until a date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->decimal('credit_limit', 14, 2)->nullable();
            $t->boolean('credit_hold')->default(false);
            $t->string('credit_hold_reason', 300)->nullable();
            $t->date('credit_release_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['credit_limit', 'credit_hold', 'credit_hold_reason', 'credit_release_until']));
    }
};
