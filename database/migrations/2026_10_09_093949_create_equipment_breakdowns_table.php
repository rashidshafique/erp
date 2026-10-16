
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_breakdowns', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('breakdown_number', 40)->unique();

            $table->foreignUuid('equipment_id')
                ->constrained('equipment')
                ->restrictOnDelete();

            $table->foreignUuid('maintenance_work_order_id')
                ->nullable()
                ->constrained('maintenance_work_orders')
                ->restrictOnDelete();

            $table->string('severity', 20)->default('MINOR');
            $table->string('status', 20)->default('OPEN');

            $table->timestampTz('started_at');
            $table->timestampTz('reported_at')->nullable();
            $table->timestampTz('restored_at')->nullable();

            $table->string('failure_category', 40)->nullable();
            $table->text('failure_description');

            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();

            $table->string('reported_by')->nullable();
            $table->string('restored_by')->nullable();

            $table->timestamps();

            $table->index(['equipment_id', 'started_at']);
            $table->index(['status', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_breakdowns');
    }
};