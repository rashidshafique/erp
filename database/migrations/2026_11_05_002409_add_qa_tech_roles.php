<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Two phone-app roles: one records tests, the other logs defects; each can be given to people separately. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['QA_TEST_TECH', 'QA_DEFECT_TECH'] as $code) {
            if (DB::table('roles')->where('code', $code)->exists()) {
                continue;
            }
            $def = config('permissions.roles.'.$code);
            $id = DB::table('roles')->insertGetId(['code' => $code, 'name' => $def['name'], 'is_system' => false, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($def['permissions'] as $p) {
                DB::table('role_permissions')->insert(['role_id' => $id, 'permission' => $p]);
            }
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('code', ['QA_TEST_TECH', 'QA_DEFECT_TECH'])->delete();
    }
};
