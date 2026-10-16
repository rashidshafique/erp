
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_distribution_records', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('controlled_document_revision_id')
                ->constrained('controlled_document_revisions')
                ->restrictOnDelete();

            $table->string('recipient_type', 30)->default('PERSON');
            $table->string('recipient_identifier', 150);
            $table->string('recipient_name', 150)->nullable();
            $table->string('department', 100)->nullable();

            $table->string('distribution_method', 30)->default('SYSTEM');
            $table->timestampTz('distributed_at')->nullable();
            $table->string('distributed_by', 150)->nullable();

            $table->boolean('acknowledgement_required')->default(false);
            $table->timestampTz('acknowledged_at')->nullable();
            $table->string('acknowledged_by', 150)->nullable();

            $table->string('status', 30)->default('ISSUED');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(
                ['controlled_document_revision_id', 'status'],
                'document_distribution_revision_status_index'
            );

            $table->index(['recipient_identifier', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_distribution_records');
    }
};