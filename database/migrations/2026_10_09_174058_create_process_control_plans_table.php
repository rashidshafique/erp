
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_control_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('plan_number', 100)->unique();
            $table->string('revision_code', 50)->default('A');
            $table->string('title', 250);

            $table->foreignUuid('product_revision_id')
                ->nullable()
                ->constrained('product_revisions')
                ->restrictOnDelete();

            $table->foreignUuid('manufacturing_method_id')
                ->nullable()
                ->constrained('manufacturing_methods')
                ->restrictOnDelete();

            $table->foreignUuid('process_route_id')
                ->nullable()
                ->constrained('process_routes')
                ->restrictOnDelete();

            $table->foreignUuid('operation_id')
                ->nullable()
                ->constrained('operations')
                ->restrictOnDelete();

            $table->string('process_area', 60)->nullable();
            $table->string('status', 30)->default('DRAFT');

            $table->text('scope')->nullable();
            $table->text('process_flow_summary')->nullable();
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
            $table->index(['manufacturing_method_id', 'status']);
            $table->index(['process_route_id', 'operation_id']);
            $table->index(['process_area', 'status']);
            $table->index(['status', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_control_plans');
    }
};