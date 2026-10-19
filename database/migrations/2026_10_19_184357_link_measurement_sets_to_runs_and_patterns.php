<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A sand test started from a moulding run is linked to that run (moulding_run_id already exists), to its pattern, or both. */
    public function up(): void
    {
        Schema::table('measurement_sets', function (Blueprint $table) {
            $table->foreignUuid('pattern_id')->nullable()->constrained('patterns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('measurement_sets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pattern_id');
        });
    }
};
