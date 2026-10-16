
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('process_type', 30);
            $table->string('status', 30)->default('DRAFT');

            $table->decimal('target_metal_weight_kg', 14, 3)->nullable();
            $table->decimal('target_total_charge_weight_kg', 14, 3)->nullable();

            $table->string('revision', 20)->default('A');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->text('instructions')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['process_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_batches');
    }
};