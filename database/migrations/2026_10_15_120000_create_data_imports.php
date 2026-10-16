<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('entity', 40);
            $t->string('source_type', 10);          // FILE, GOOGLE, PASTE
            $t->string('source_label', 300)->nullable();
            $t->string('file_disk', 30);
            $t->string('file_path', 300)->nullable();
            $t->string('file_ext', 10);             // csv or xlsx (pasted and Google data are stored as csv)
            $t->unsignedSmallInteger('sheet_index')->default(0);
            $t->unsignedSmallInteger('header_row')->default(1);
            $t->jsonb('mapping')->nullable();       // field => {col, default}
            $t->jsonb('options')->nullable();       // mode, match_by, decimal_comma, date_order, stop_on_error, value_map
            $t->string('status', 12)->default('UPLOADED');
            $t->unsignedInteger('progress')->default(0);   // rows already handled while an import is running
            $t->jsonb('counts')->nullable();
            $t->jsonb('report')->nullable();        // one line per row after commit
            $t->timestamp('committed_at')->nullable();
            $t->string('created_by_id', 40)->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->timestamps();
        });
        DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_status_chk CHECK (status IN ('UPLOADED','MAPPED','RUNNING','COMMITTED','DISCARDED'))");
        DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_source_chk CHECK (source_type IN ('FILE','GOOGLE','PASTE'))");

        Schema::create('import_mapping_templates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('entity', 40);
            $t->string('name', 120);
            $t->jsonb('headers');                   // header names seen when it was saved, to match columns by name next time
            $t->jsonb('mapping');                   // field => {header, default}
            $t->jsonb('options')->nullable();
            $t->timestamps();
            $t->unique(['entity', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mapping_templates');
        Schema::dropIfExists('import_batches');
    }
};
