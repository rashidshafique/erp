
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_record_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('packing_record_id')
                ->constrained('packing_records')
                ->restrictOnDelete();

            $table->foreignUuid('casting_lot_id')
                ->constrained('casting_lots')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity_packed');
            $table->decimal('net_weight_kg', 12, 3)->default(0);

            $table->unsignedInteger('package_count')->default(0);
            $table->string('package_reference', 100)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['casting_lot_id', 'packing_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_record_items');
    }
};