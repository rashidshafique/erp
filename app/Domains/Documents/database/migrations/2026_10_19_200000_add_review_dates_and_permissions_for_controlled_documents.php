<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controlled_documents', function (Blueprint $table) {
            $table->unsignedSmallInteger('review_interval_months')->nullable();
            $table->date('next_review_on')->nullable();
            $table->date('last_reviewed_on')->nullable();
            $table->string('last_reviewed_by', 150)->nullable();
            $table->index('next_review_on');
        });

        // The quality manager keeps the document system; other roles can be given the permissions on the Roles screen.
        $role = DB::table('roles')->where('code', 'QUALITY_MANAGER')->first();
        if ($role) {
            foreach (['documents.manage', 'documents.approve'] as $permission) {
                if (! DB::table('role_permissions')->where('role_id', $role->id)->where('permission', $permission)->exists()) {
                    DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('controlled_documents', function (Blueprint $table) {
            $table->dropIndex(['next_review_on']);
            $table->dropColumn(['review_interval_months', 'next_review_on', 'last_reviewed_on', 'last_reviewed_by']);
        });
    }
};
