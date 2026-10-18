<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Every file is a document with a version number. With versioning switched off nothing shows it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $t) {
            $t->string('document_id', 36)->nullable()->index();   // shared by every version of the same document
            $t->unsignedInteger('version')->default(1);
            $t->string('revision_label', 30)->nullable();
            $t->string('change_note', 300)->nullable();
            $t->timestampTz('superseded_at')->nullable();
            $t->string('superseded_by_id', 36)->nullable();
        });
        DB::table('attachments')->whereNull('document_id')->update(['document_id' => DB::raw('id')]);
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $t) {
            $t->dropIndex(['document_id']);
            $t->dropColumn(['document_id', 'version', 'revision_label', 'change_note', 'superseded_at', 'superseded_by_id']);
        });
    }
};
