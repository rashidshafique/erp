<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('review_number')->default(1);
            $table->string('status', 20)->default('DRAFT');      // DRAFT, IN_REVIEW, APPROVED, REJECTED, SUPERSEDED
            $table->string('verdict', 10)->default('PASS');      // PASS, WARN, BLOCK
            $table->timestamp('evaluated_at')->nullable();
            $table->string('sales_signed_by', 100)->nullable();
            $table->timestamp('sales_signed_at')->nullable();
            $table->string('quality_signed_by', 100)->nullable();
            $table->timestamp('quality_signed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['sales_order_id', 'review_number']);
        });
        DB::statement("ALTER TABLE contract_reviews ADD CONSTRAINT contract_reviews_status_valid CHECK (status IN ('DRAFT','IN_REVIEW','APPROVED','REJECTED','SUPERSEDED'))");
        DB::statement("ALTER TABLE contract_reviews ADD CONSTRAINT contract_reviews_verdict_valid CHECK (verdict IN ('PASS','WARN','BLOCK'))");

        Schema::create('contract_review_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contract_review_id')->constrained('contract_reviews')->cascadeOnDelete();
            $table->foreignUuid('sales_order_line_id')->nullable()->constrained('sales_order_lines')->cascadeOnDelete();
            $table->string('check_code', 40);
            $table->string('severity', 10);                      // PASS, WARN, BLOCK
            $table->text('message');
            $table->text('acknowledgement')->nullable();         // reason a WARN was accepted
            $table->timestamps();
            $table->index(['contract_review_id', 'severity']);
        });
        DB::statement("ALTER TABLE contract_review_items ADD CONSTRAINT contract_review_items_severity_valid CHECK (severity IN ('PASS','WARN','BLOCK'))");

        Schema::create('planned_heats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('plan_number', 40)->unique();
            $table->foreignUuid('iron_grade_id')->constrained('iron_grades')->restrictOnDelete();
            $table->foreignUuid('furnace_id')->nullable()->constrained('furnaces')->restrictOnDelete();
            $table->date('planned_date');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->decimal('planned_metal_kg', 14, 3);
            $table->string('status', 12)->default('PLANNED');   // PLANNED, RELEASED, CANCELLED
            $table->foreignUuid('heat_id')->nullable()->constrained('heats')->restrictOnDelete();
            $table->boolean('changeover_required')->default(false);
            $table->boolean('late_risk')->default(false);
            $table->text('flags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['planned_date', 'furnace_id']);
        });
        DB::statement("ALTER TABLE planned_heats ADD CONSTRAINT planned_heats_status_valid CHECK (status IN ('PLANNED','RELEASED','CANCELLED'))");
        DB::statement("ALTER TABLE planned_heats ADD CONSTRAINT planned_heats_kg_positive CHECK (planned_metal_kg > 0)");

        Schema::create('planned_heat_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('planned_heat_id')->constrained('planned_heats')->cascadeOnDelete();
            $table->foreignUuid('production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->decimal('metal_kg', 14, 3);
            $table->timestamps();
            $table->unique(['planned_heat_id', 'production_order_id']);
        });
    }

    public function down(): void
    {
        foreach (['planned_heat_orders', 'planned_heats', 'contract_review_items', 'contract_reviews'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
