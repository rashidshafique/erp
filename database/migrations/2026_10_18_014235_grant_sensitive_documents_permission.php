<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** ID documents and contracts now need their own permission; HR managers keep seeing them. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }
        $id = DB::table('roles')->where('code', 'HR_MANAGER')->value('id');
        if ($id) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $id, 'permission' => 'documents.sensitive']);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'documents.sensitive')->delete();
    }
};
