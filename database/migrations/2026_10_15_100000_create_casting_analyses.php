<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casting_analyses', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->string('name', 200);
            $t->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $t->foreignUuid('iron_grade_id')->nullable()->constrained('iron_grades')->nullOnDelete();
            $t->string('status', 12)->default('QUEUED');
            $t->string('source_type', 8);
            $t->string('original_name', 200);
            $t->string('file_disk', 30);
            $t->string('file_path', 300);
            $t->string('file_sha256', 64);
            $t->unsignedBigInteger('file_size');
            $t->string('units', 3)->default('mm');
            $t->string('quality', 10)->default('STANDARD');
            $t->string('family_requested', 8)->default('AUTO');
            $t->string('family', 8)->nullable();
            $t->jsonb('options')->nullable();
            $t->unsignedSmallInteger('score')->nullable();
            $t->string('score_letter', 1)->nullable();
            $t->text('error')->nullable();
            $t->jsonb('result')->nullable();
            $t->jsonb('recommendation')->nullable();
            $t->jsonb('viewer')->nullable();
            $t->string('field_path', 300)->nullable();
            $t->unsignedInteger('duration_ms')->nullable();
            $t->unsignedBigInteger('created_by_id')->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->timestamps();
            $t->index('product_id');
            $t->index('status');
        });
        DB::statement("ALTER TABLE casting_analyses ADD CONSTRAINT casting_analyses_status_valid CHECK (status IN ('QUEUED','COMPLETE','FAILED'))");
        DB::statement("ALTER TABLE casting_analyses ADD CONSTRAINT casting_analyses_source_valid CHECK (source_type IN ('STL','STEP'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('casting_analyses');
    }
};
