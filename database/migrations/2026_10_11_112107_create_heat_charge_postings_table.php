<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heat_charge_postings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('heat_id')
                ->unique()
                ->constrained('heats')
                ->restrictOnDelete();

            $table->string('status', 20)->default('POSTED');
            $table->decimal('total_quantity_kg', 14, 3);
            $table->decimal('total_cost_pkr', 16, 4);

            $table->string('posted_by', 100);
            $table->timestampTz('posted_at');
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heat_charge_postings');
    }
};