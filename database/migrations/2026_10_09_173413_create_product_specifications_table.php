
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_specifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('product_revision_id')
                ->constrained('product_revisions')
                ->restrictOnDelete();

            $table->foreignUuid('controlled_document_revision_id')
                ->nullable()
                ->constrained('controlled_document_revisions')
                ->restrictOnDelete();

            $table->string('specification_code', 100);
            $table->string('revision_code', 50)->default('A');
            $table->string('title', 250);
            $table->string('specification_type', 50)->default('GENERAL');
            $table->string('status', 30)->default('DRAFT');

            $table->string('governing_standard_code', 100)->nullable();
            $table->string('governing_standard_edition', 100)->nullable();
            $table->string('material_grade', 100)->nullable();

            $table->string('drawing_number', 100)->nullable();
            $table->string('drawing_revision', 50)->nullable();

            $table->text('scope')->nullable();
            $table->text('notes')->nullable();

            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('effective_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();

            $table->string('prepared_by', 150)->nullable();
            $table->string('approved_by', 150)->nullable();

            $table->timestamps();

            $table->unique(
                ['product_revision_id', 'specification_code', 'revision_code'],
                'product_specifications_revision_unique'
            );

            $table->index(['product_revision_id', 'status']);
            $table->index(['governing_standard_code', 'governing_standard_edition']);
            $table->index(['status', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specifications');
    }
};