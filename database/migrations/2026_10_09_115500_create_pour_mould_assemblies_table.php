
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pour_mould_assemblies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('pour_id')
                ->constrained('pours')
                ->restrictOnDelete();

            $table->foreignUuid('mould_assembly_id')
                ->constrained('mould_assemblies')
                ->restrictOnDelete();

            $table->foreignUuid('production_order_id')
                ->constrained('production_orders')
                ->restrictOnDelete();

            $table->decimal('poured_weight_kg', 12, 3)->nullable();
            $table->unsignedInteger('castings_poured_qty')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                'mould_assembly_id',
                'pour_mould_assemblies_assembly_unique'
            );

            $table->index('pour_id');
            $table->index('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pour_mould_assemblies');
    }
};