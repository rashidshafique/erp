<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Numbers a record carried before documents were renumbered to a letter and five digits (I-10000). Kept so printed papers,
     * labels and stamped parts can still be found by the number on them, and so the renumbering can be undone.
     */
    public function up(): void
    {
        Schema::create('number_history', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 64);
            $table->string('column_name', 64);
            $table->string('record_id', 64);
            $table->string('old_number', 120);
            $table->string('new_number', 120);
            $table->timestamp('created_at')->nullable();

            $table->index('old_number');
            $table->index(['table_name', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_history');
    }
};
