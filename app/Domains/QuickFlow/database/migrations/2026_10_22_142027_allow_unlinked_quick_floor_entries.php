<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moulds made or poured can be entered before their order exists: the entry waits without an order and is linked later,
 * by hand or when a matching order opens. The pattern revision is kept so the moulding run can be written at that point.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_floor_entries', function (Blueprint $table) {
            $table->uuid('production_order_id')->nullable()->change();
            $table->foreignUuid('pattern_revision_id')->nullable()->after('pattern_id')->constrained('pattern_revisions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quick_floor_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pattern_revision_id');
            $table->uuid('production_order_id')->nullable(false)->change();
        });
    }
};
