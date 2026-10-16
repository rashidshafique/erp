<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_active')->default(true);
            $t->timestampTz('last_login_at')->nullable();
            $t->boolean('must_change_password')->default(false);
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name', 100);
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });
        Schema::create('role_user', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['user_id', 'role_id']);
        });
        Schema::create('role_permissions', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->string('permission', 60);
            $t->primary(['role_id', 'permission']);
        });

        foreach (config('permissions.roles') as $code => $def) {
            $id = DB::table('roles')->insertGetId(['code' => $code, 'name' => $def['name'], 'is_system' => $code === 'ADMIN', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($def['permissions'] as $p) {
                if ($p !== '*') {
                    DB::table('role_permissions')->insert(['role_id' => $id, 'permission' => $p]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_active', 'last_login_at', 'must_change_password']));
    }
};
