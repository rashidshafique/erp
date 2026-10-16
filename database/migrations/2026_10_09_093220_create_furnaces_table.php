
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('furnaces', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code', 40)->unique();
            $table->string('name');

            $table->string('furnace_type', 30);
            // CUPOLA, INDUCTION, OTHER

            $table->string('status', 20)->default('ACTIVE');

            $table->decimal('rated_capacity_kg', 12, 3)->nullable();
            $table->decimal('nominal_melting_rate_kg_per_hour', 12, 3)->nullable();
            $table->decimal('rated_power_kw', 12, 3)->nullable();

            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('furnaces');
    }
};