<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Which moulds of a pour each lot's castings came from: one row for a per-mould lot, several for a grouped lot. */
    public function up(): void
    {
        Schema::create('casting_lot_pour_mould_assembly', function (Blueprint $table) {
            $table->foreignUuid('casting_lot_id')->constrained('casting_lots')->cascadeOnDelete();
            $table->foreignUuid('pour_mould_assembly_id')->constrained('pour_mould_assemblies')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
            $table->primary(['casting_lot_id', 'pour_mould_assembly_id']);
        });

        DB::table('casting_lots')->orderBy('id')->each(function ($lot) {
            DB::table('casting_lot_pour_mould_assembly')->insert([
                'casting_lot_id' => $lot->id, 'pour_mould_assembly_id' => $lot->pour_mould_assembly_id,
                'quantity' => (int) $lot->quantity_poured, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casting_lot_pour_mould_assembly');
    }
};
