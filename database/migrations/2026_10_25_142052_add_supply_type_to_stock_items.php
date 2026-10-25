<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Whether an item is goods or a service: in many countries they are taxed by different authorities at different rates. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['materials', 'products'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('supply_type', 10)->default('GOODS'));
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_supply_type_valid CHECK (supply_type IN ('GOODS','SERVICE'))");
        }
    }

    public function down(): void
    {
        foreach (['materials', 'products'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_supply_type_valid");
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('supply_type'));
        }
    }
};
