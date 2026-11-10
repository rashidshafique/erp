<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The Accounts role keeps the fixed-asset register, as it keeps the ledger. */
    public function up(): void
    {
        if (Schema::hasTable('roles') && ($role = DB::table('roles')->where('code', 'ACCOUNTS')->value('id'))) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission' => 'assets.manage']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('permission', 'assets.manage')->delete();
        }
    }
};
