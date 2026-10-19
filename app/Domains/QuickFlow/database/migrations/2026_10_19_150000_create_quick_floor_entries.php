<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_floor_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 8);
            $table->foreignUuid('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignUuid('moulding_run_id')->nullable()->constrained('moulding_runs')->nullOnDelete();
            $table->foreignUuid('heat_id')->nullable()->constrained('heats')->nullOnDelete();
            $table->foreignUuid('pour_id')->nullable()->constrained('pours')->nullOnDelete();
            $table->unsignedInteger('moulds');
            $table->unsignedSmallInteger('pieces_per_mould')->default(1);
            $table->date('entry_date');
            $table->string('entered_by', 100)->nullable();
            $table->string('notes', 300)->nullable();
            $table->timestamps();
            $table->index(['production_order_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_floor_entries');
    }
};
