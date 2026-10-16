
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code', 40)->unique();
            $table->string('name');

            $table->string('equipment_type', 40);
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();

            $table->string('status', 30)->default('AVAILABLE');

            $table->date('commissioned_on')->nullable();
            $table->date('warranty_until')->nullable();

            $table->decimal('rated_power_kw', 12, 3)->nullable();
            $table->decimal('rated_capacity_kg', 12, 3)->nullable();

            $table->text('location_description')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['equipment_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};