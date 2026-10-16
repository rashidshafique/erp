
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heat_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('heat_id')
                ->constrained('heats')
                ->restrictOnDelete();

            $table->string('event_type', 50);
            $table->timestampTz('occurred_at');

            $table->string('operator_name', 100)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->jsonb('details')->nullable();

            $table->timestamps();

            $table->index(
                ['heat_id', 'occurred_at'],
                'heat_events_heat_time_index'
            );

            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heat_events');
    }
};