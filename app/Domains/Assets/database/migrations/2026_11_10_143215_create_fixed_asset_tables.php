<?php

use App\Domains\Assets\Support\AssetClasses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The fixed-asset register (design 28c, 28d) and the monthly depreciation run (28f, 28k). */
    public function up(): void
    {
        Schema::create('asset_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('kind', 20);
            $table->foreignUuid('cost_account_id')->constrained('ledger_accounts');
            $table->foreignUuid('depreciation_account_id')->nullable()->constrained('ledger_accounts');
            $table->foreignUuid('expense_account_id')->nullable()->constrained('ledger_accounts');
            $table->string('method', 20);
            $table->decimal('life_years', 6, 2)->nullable();
            $table->decimal('rate_percent', 6, 2)->nullable();
            $table->string('tax_class', 60)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->foreignUuid('asset_class_id')->constrained('asset_classes');
            $table->string('name', 150);
            $table->string('kind', 20);
            $table->string('status', 20)->default('IN_PROGRESS');
            $table->date('in_use_on')->nullable();
            $table->string('location', 150)->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->decimal('residual_value', 15, 2)->default(0);
            $table->string('method', 20);
            $table->decimal('life_years', 6, 2)->nullable();
            $table->decimal('rate_percent', 6, 2)->nullable();
            $table->unsignedInteger('life_fills')->nullable();
            $table->decimal('opening_depreciation', 15, 2)->default(0);
            $table->unsignedInteger('opening_fills')->default(0);
            $table->string('tax_class', 60)->nullable();
            $table->foreignUuid('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->foreignUuid('pattern_id')->nullable()->constrained('patterns')->nullOnDelete();
            $table->foreignUuid('core_box_id')->nullable()->constrained('core_boxes')->nullOnDelete();
            $table->foreignUuid('pattern_development_id')->nullable()->constrained('pattern_developments')->nullOnDelete();
            $table->date('disposed_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'kind']);
        });

        Schema::create('fixed_asset_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->string('source_type', 30);
            $table->uuid('source_id')->nullable();
            $table->string('description', 200);
            $table->string('reference', 120)->nullable();
            $table->date('incurred_on')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        Schema::create('fixed_asset_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->date('happened_on');
            $table->string('note', 300)->nullable();
            $table->foreignUuid('journal_id')->nullable()->constrained('ledger_journals')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('depreciation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('period');
            $table->string('status', 20);
            $table->decimal('total', 15, 2)->default(0);
            $table->foreignUuid('journal_id')->nullable()->constrained('ledger_journals')->nullOnDelete();
            $table->foreignUuid('reversal_journal_id')->nullable()->constrained('ledger_journals')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reverse_reason', 120)->nullable();
            $table->string('reverse_detail', 300)->nullable();
            $table->timestamps();

            $table->index(['period', 'status']);
        });

        Schema::create('depreciation_run_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('depreciation_run_id')->constrained('depreciation_runs')->cascadeOnDelete();
            $table->foreignUuid('fixed_asset_id')->constrained('fixed_assets');
            $table->unsignedInteger('fills')->nullable();
            $table->decimal('rate', 15, 4)->nullable();
            $table->decimal('charge', 15, 2);
            $table->decimal('book_before', 15, 2);
            $table->timestamps();

            $table->index('fixed_asset_id');
        });

        AssetClasses::install();
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_run_lines');
        Schema::dropIfExists('depreciation_runs');
        Schema::dropIfExists('fixed_asset_events');
        Schema::dropIfExists('fixed_asset_costs');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('asset_classes');
    }
};
