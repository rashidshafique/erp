
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controlled_document_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('controlled_document_id')
                ->constrained('controlled_documents')
                ->restrictOnDelete();

            $table->string('revision_code', 50);
            $table->string('status', 30)->default('DRAFT');

            $table->string('original_filename', 255)->nullable();
            $table->string('storage_disk', 50)->nullable();
            $table->string('storage_path', 1000)->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('file_sha256', 64)->nullable();

            $table->text('revision_summary')->nullable();
            $table->text('change_reason')->nullable();

            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('effective_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();

            $table->string('prepared_by', 150)->nullable();
            $table->string('approved_by', 150)->nullable();

            $table->timestamps();

            $table->unique(
                ['controlled_document_id', 'revision_code'],
                'controlled_document_revision_code_unique'
            );

            $table->index(['controlled_document_id', 'status']);
            $table->index(['status', 'effective_at']);
            $table->index('file_sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controlled_document_revisions');
    }
};