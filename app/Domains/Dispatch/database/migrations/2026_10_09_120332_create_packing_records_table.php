
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_records', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('packing_number', 50)->unique();

            $table->string('status', 30)->default('DRAFT');

            $table->timestampTz('packed_at')->nullable();
            $table->string('packed_by', 150)->nullable();
            $table->string('checked_by', 150)->nullable();

            $table->string('package_type', 50)->nullable();
            $table->unsignedInteger('package_count')->default(0);

            $table->decimal('total_net_weight_kg', 12, 3)->default(0);
            $table->decimal('total_gross_weight_kg', 12, 3)->default(0);

            $table->text('packaging_notes')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'packed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_records');
    }
};