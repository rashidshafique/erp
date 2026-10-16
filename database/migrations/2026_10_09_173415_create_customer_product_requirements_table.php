
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_product_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->foreignUuid('product_revision_id')
                ->constrained('product_revisions')
                ->restrictOnDelete();

            $table->foreignUuid('product_specification_id')
                ->nullable()
                ->constrained('product_specifications')
                ->restrictOnDelete();

            $table->string('requirement_code', 100);
            $table->string('revision_code', 50)->default('A');
            $table->string('title', 250);
            $table->string('status', 30)->default('DRAFT');

            $table->string('customer_document_number', 150)->nullable();
            $table->string('customer_document_revision', 50)->nullable();
            $table->string('customer_grade', 100)->nullable();

            $table->string('market_or_destination', 150)->nullable();
            $table->string('language_code', 10)->default('en');

            $table->text('scope')->nullable();
            $table->text('special_instructions')->nullable();
            $table->text('notes')->nullable();

            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('customer_approved_at')->nullable();
            $table->timestampTz('effective_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();

            $table->string('prepared_by', 150)->nullable();
            $table->string('approved_by', 150)->nullable();
            $table->string('customer_approval_reference', 150)->nullable();

            $table->timestamps();

            $table->unique(
                ['customer_id', 'product_revision_id', 'requirement_code', 'revision_code'],
                'customer_product_requirements_revision_unique'
            );

            $table->index(['customer_id', 'status']);
            $table->index(['product_revision_id', 'status']);
            $table->index(['status', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_product_requirements');
    }
};