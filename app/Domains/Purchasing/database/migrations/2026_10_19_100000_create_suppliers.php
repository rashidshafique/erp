<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('supplies', 200)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('approval_status', 14)->default('PENDING');
            $table->date('approved_on')->nullable();
            $table->date('next_review_on')->nullable();
            $table->unsignedSmallInteger('review_interval_days')->default(365);
            $table->decimal('latest_score', 5, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['approval_status', 'next_review_on']);
        });

        Schema::create('supplier_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('evaluated_on');
            $table->unsignedTinyInteger('quality_score');
            $table->unsignedTinyInteger('delivery_score');
            $table->unsignedTinyInteger('service_score');
            $table->decimal('overall_score', 5, 1);
            $table->string('decision', 14);
            $table->text('notes')->nullable();
            $table->string('recorded_by', 150)->nullable();
            $table->timestamps();
            $table->index(['supplier_id', 'evaluated_on']);
        });

        Schema::table('material_lots', function (Blueprint $table) {
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_lots', fn (Blueprint $table) => $table->dropConstrainedForeignId('supplier_id'));
        Schema::dropIfExists('supplier_evaluations');
        Schema::dropIfExists('suppliers');
    }
};
