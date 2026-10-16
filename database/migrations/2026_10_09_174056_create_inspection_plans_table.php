
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('plan_number', 100)->unique();
            $table->string('revision_code', 50)->default('A');
            $table->string('title', 250);

            $table->foreignUuid('product_revision_id')
                ->nullable()
                ->constrained('product_revisions')
                ->restrictOnDelete();

            $table->foreignUuid('product_specification_id')
                ->nullable()
                ->constrained('product_specifications')
                ->restrictOnDelete();

            $table->string('plan_type', 40)->default('FINAL_INSPECTION');
            $table->string('status', 30)->default('DRAFT');

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

            $table->index(['product_revision_id', 'status']);
            $table->index(['product_specification_id', 'status']);
            $table->index(['status', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_plans');
    }
};