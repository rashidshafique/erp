<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Customer codes now start with C- instead of CUS-. An installation that saved the Codes settings page holds the old default
 * as its prefix; that is moved over, while a prefix someone chose on purpose is left alone. Existing customer codes are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->where('key', 'codes.prefix.customer')->where('value', 'CUS')->update(['value' => 'C', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'codes.prefix.customer')->where('value', 'C')->update(['value' => 'CUS', 'updated_at' => now()]);
    }
};
