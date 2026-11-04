<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Seeing prices and seeing costs become permissions. Every role that exists keeps seeing both, so nothing changes until an administrator takes them away. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('roles')->pluck('id') as $id) {
            foreach (['fields.prices', 'fields.costs'] as $permission) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $id, 'permission' => $permission]);
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', ['fields.prices', 'fields.costs'])->delete();
    }
};
