<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $t->string('title', 200);
            $t->string('source_type', 14);
            $t->string('status', 12)->default('NEW');
            $t->date('received_on');
            $t->date('due_on')->nullable();
            $t->string('customer_reference', 100)->nullable();
            $t->string('contact_name', 120)->nullable();
            $t->text('summary')->nullable();
            $t->string('lost_reason', 300)->nullable();
            $t->timestampTz('closed_at')->nullable();
            $t->unsignedBigInteger('created_by_id')->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->timestamps();
            $t->index(['customer_id', 'status']);
        });
        DB::statement("ALTER TABLE enquiries ADD CONSTRAINT enquiries_source_valid CHECK (source_type IN ('REPEAT','SAMPLE','DRAWING','CUSTOMER_TOOL','CONCEPT','TRANSFER','CHANGE'))");
        DB::statement("ALTER TABLE enquiries ADD CONSTRAINT enquiries_status_valid CHECK (status IN ('NEW','FEASIBILITY','QUOTED','WON','LOST','CANCELLED'))");

        Schema::create('enquiry_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enquiry_id')->constrained('enquiries')->cascadeOnDelete();
            $t->unsignedSmallInteger('line_no');
            $t->string('description', 200);
            $t->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $t->string('drawing_ref', 100)->nullable();
            $t->string('drawing_rev', 20)->nullable();
            $t->string('material_text', 100)->nullable();
            $t->string('family', 8)->nullable();
            $t->unsignedInteger('annual_qty')->nullable();
            $t->unsignedInteger('first_batch_qty')->nullable();
            $t->decimal('target_price', 14, 2)->nullable();
            $t->decimal('weight_kg', 12, 3)->nullable();
            $t->boolean('heat_treatment')->default(false);
            $t->boolean('ndt')->default(false);
            $t->unsignedSmallInteger('cores_count')->nullable();
            $t->decimal('machining_cost', 14, 2)->default(0);
            $t->decimal('other_cost', 14, 2)->default(0);
            $t->decimal('tooling_cost', 14, 2)->default(0);
            $t->foreignUuid('casting_analysis_id')->nullable()->constrained('casting_analyses')->nullOnDelete();
            $t->string('feasibility_status', 14)->default('PENDING');
            $t->text('feasibility_notes')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['enquiry_id', 'line_no']);
        });
        DB::statement("ALTER TABLE enquiry_items ADD CONSTRAINT enquiry_items_feas_valid CHECK (feasibility_status IN ('PENDING','OK','CONDITIONAL','NOT_FEASIBLE'))");

        Schema::create('quotations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->foreignUuid('enquiry_id')->constrained('enquiries')->cascadeOnDelete();
            $t->unsignedSmallInteger('version')->default(1);
            $t->string('status', 12)->default('DRAFT');
            $t->date('valid_until')->nullable();
            $t->string('currency', 3)->default('PKR');
            $t->string('payment_terms', 200)->nullable();
            $t->unsignedSmallInteger('delivery_weeks')->nullable();
            $t->string('tooling_mode', 10)->default('NONE');
            $t->unsignedInteger('tooling_amortise_qty')->nullable();
            $t->text('notes')->nullable();
            $t->timestampTz('issued_at')->nullable();
            $t->string('issued_by_name', 150)->nullable();
            $t->timestampTz('decided_at')->nullable();
            $t->string('decision_note', 300)->nullable();
            $t->foreignUuid('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $t->unsignedBigInteger('created_by_id')->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->timestamps();
            $t->unique(['enquiry_id', 'version']);
        });
        DB::statement("ALTER TABLE quotations ADD CONSTRAINT quotations_status_valid CHECK (status IN ('DRAFT','ISSUED','ACCEPTED','REJECTED','EXPIRED','SUPERSEDED'))");
        DB::statement("ALTER TABLE quotations ADD CONSTRAINT quotations_tooling_valid CHECK (tooling_mode IN ('NONE','UPFRONT','AMORTISED'))");

        Schema::create('quotation_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $t->foreignUuid('enquiry_item_id')->constrained('enquiry_items')->cascadeOnDelete();
            $t->unsignedSmallInteger('line_no');
            $t->unsignedInteger('qty');
            $t->decimal('weight_kg', 12, 3);
            $t->decimal('poured_kg', 12, 3);
            $t->string('family', 8);
            $t->unsignedSmallInteger('cores_count')->default(0);
            $t->boolean('heat_treatment')->default(false);
            $t->boolean('ndt')->default(false);
            $t->decimal('machining_cost', 14, 2)->default(0);
            $t->decimal('other_cost', 14, 2)->default(0);
            $t->decimal('scrap_pct', 5, 2);
            $t->decimal('overhead_pct', 5, 2);
            $t->decimal('margin_pct', 5, 2);
            $t->decimal('tooling_cost', 14, 2)->default(0);
            $t->decimal('price_override', 14, 2)->nullable();
            $t->decimal('unit_cost', 14, 2)->default(0);
            $t->decimal('unit_price', 14, 2)->default(0);
            $t->decimal('effective_margin_pct', 6, 2)->default(0);
            $t->jsonb('breakdown')->nullable();
            $t->text('basis')->nullable();
            $t->timestamps();
            $t->unique(['quotation_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('enquiry_items');
        Schema::dropIfExists('enquiries');
    }
};
