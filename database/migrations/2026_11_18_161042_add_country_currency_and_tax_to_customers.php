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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('country', 2)->default('PK');
            $table->string('currency', 3)->default('PKR');
            $table->string('supply', 10)->default('LOCAL');
            $table->boolean('withholds_tax')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['country', 'currency', 'supply', 'withholds_tax']));
    }
};
