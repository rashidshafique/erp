<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->string('weekly_off', 10)->nullable()->default(null)->change();      // null = follow the company weekly holiday
        });
        DB::table('employees')->where('weekly_off', '0')->update(['weekly_off' => null]);
    }

    public function down(): void
    {
        DB::table('employees')->whereNull('weekly_off')->update(['weekly_off' => '0']);
        Schema::table('employees', function (Blueprint $t) {
            $t->string('weekly_off', 10)->default('0')->change();
        });
    }
};
