<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiry_item_reviews', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('enquiry_item_id')->constrained('enquiry_items')->cascadeOnDelete();
            $t->string('department', 20);
            $t->string('verdict', 14);
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('signed_by_id')->nullable();
            $t->string('signed_by_name', 150)->nullable();
            $t->timestampTz('signed_at')->nullable();
            $t->timestamps();
            $t->unique(['enquiry_item_id', 'department']);
        });
        DB::statement("ALTER TABLE enquiry_item_reviews ADD CONSTRAINT enquiry_item_reviews_verdict_valid CHECK (verdict IN ('OK','CONDITIONAL','NOT_FEASIBLE'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_item_reviews');
    }
};
