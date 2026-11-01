<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who owns a core box and where it is, as for patterns (design 18h). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_boxes', function (Blueprint $table) {
            $table->string('owner_type', 20)->default('FOUNDRY');
            $table->foreignUuid('owner_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('custody', 20)->default('IN_STORE');
            $table->string('custody_location', 150)->nullable();
            $table->string('ownership_reference', 100)->nullable();
            $table->unsignedSmallInteger('return_notice_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('core_boxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_customer_id');
            $table->dropColumn(['owner_type', 'custody', 'custody_location', 'ownership_reference', 'return_notice_days']);
        });
    }
};
