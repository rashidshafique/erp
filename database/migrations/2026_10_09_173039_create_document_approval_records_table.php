
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_approval_records', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('controlled_document_revision_id')
                ->constrained('controlled_document_revisions')
                ->restrictOnDelete();

            $table->unsignedInteger('approval_step')->default(1);
            $table->string('approval_type', 30)->default('APPROVAL');
            $table->string('decision', 30)->default('PENDING');

            $table->string('approver_name', 150)->nullable();
            $table->string('approver_role', 100)->nullable();
            $table->string('approver_identifier', 150)->nullable();

            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('decided_at')->nullable();

            $table->text('comments')->nullable();

            $table->timestamps();

            $table->index(
                ['controlled_document_revision_id', 'approval_step'],
                'document_approval_revision_step_index'
            );

            $table->index(['decision', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_approval_records');
    }
};