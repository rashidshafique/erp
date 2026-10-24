<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_stage_definitions', function (Blueprint $table) {
            $table->boolean('product_specific')->default(false);
        });

        Schema::create('product_revision_steps', function (Blueprint $table) {
            $table->foreignUuid('product_revision_id')->constrained('product_revisions')->cascadeOnDelete();
            $table->foreignUuid('stage_definition_id')->constrained('production_stage_definitions')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['product_revision_id', 'stage_definition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_revision_steps');
        Schema::table('production_stage_definitions', function (Blueprint $table) {
            $table->dropColumn('product_specific');
        });
    }
};
