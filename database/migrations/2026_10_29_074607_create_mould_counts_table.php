<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The moulds counted on the floor before a pour, per pattern, against what the records say is waiting; a shortfall is written off. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mould_counts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pattern_revision_id')->constrained('pattern_revisions');
            $table->foreignUuid('heat_id')->nullable()->constrained('heats')->nullOnDelete();
            $table->unsignedInteger('expected');
            $table->unsignedInteger('counted');
            $table->unsignedInteger('written_off')->default(0);
            $table->string('reason', 20)->nullable();
            $table->string('note', 300)->nullable();
            $table->string('counted_by', 150)->nullable();
            $table->timestamp('counted_at');
            $table->timestamps();
            $table->index('counted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mould_counts');
    }
};
