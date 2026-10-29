<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A development belongs to one part of its enquiry, and a pattern the customer supplies or moves from another foundry has its own type. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pattern_developments', function (Blueprint $table) {
            $table->foreignUuid('enquiry_item_id')->nullable()->after('enquiry_id')->constrained('enquiry_items')->nullOnDelete();
        });
        DB::statement('ALTER TABLE pattern_developments DROP CONSTRAINT pattern_developments_type_valid');
        DB::statement('ALTER TABLE pattern_developments ALTER COLUMN type TYPE varchar(20)');
        DB::statement("ALTER TABLE pattern_developments ADD CONSTRAINT pattern_developments_type_valid CHECK (type IN ('NEW_PART','NEW_PRODUCT','REPLACEMENT','MODIFICATION','CUSTOMER_PATTERN'))");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM pattern_developments WHERE type = 'CUSTOMER_PATTERN'");
        DB::statement('ALTER TABLE pattern_developments DROP CONSTRAINT pattern_developments_type_valid');
        DB::statement("ALTER TABLE pattern_developments ADD CONSTRAINT pattern_developments_type_valid CHECK (type IN ('NEW_PART','NEW_PRODUCT','REPLACEMENT','MODIFICATION'))");
        Schema::table('pattern_developments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enquiry_item_id');
        });
    }
};
