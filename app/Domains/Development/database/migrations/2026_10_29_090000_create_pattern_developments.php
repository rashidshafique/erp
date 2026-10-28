<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A pattern development: a new or replacement pattern worked through with the customer (design 11a, 11b, 12a). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pattern_developments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('type', 14);
            $table->string('title', 200);
            $table->unsignedTinyInteger('stage_index')->default(0);
            $table->string('status', 10)->default('OPEN');
            $table->foreignUuid('enquiry_id')->nullable()->constrained('enquiries')->nullOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUuid('pattern_id')->nullable()->constrained('patterns')->nullOnDelete();
            $table->string('pattern_by', 20)->default('DECIDE_LATER');
            $table->string('pattern_maker', 150)->nullable();
            $table->string('owner_name', 150)->nullable();
            $table->date('due_on')->nullable();
            $table->string('reason', 20)->nullable();
            $table->string('who_pays', 10)->nullable();
            $table->string('drawing_change', 300)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name', 150)->nullable();
            $table->timestamps();
            $table->index(['status', 'type']);
        });
        DB::statement("ALTER TABLE pattern_developments ADD CONSTRAINT pattern_developments_type_valid CHECK (type IN ('NEW_PART','NEW_PRODUCT','REPLACEMENT','MODIFICATION'))");
        DB::statement("ALTER TABLE pattern_developments ADD CONSTRAINT pattern_developments_status_valid CHECK (status IN ('OPEN','DONE','CANCELLED'))");

        Schema::create('pattern_development_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pattern_development_id')->constrained('pattern_developments')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->text('text');
            $table->string('user_name', 150)->nullable();
            $table->timestamps();
            $table->index('pattern_development_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_development_events');
        Schema::dropIfExists('pattern_developments');
    }
};
