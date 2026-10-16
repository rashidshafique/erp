
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_control_plan_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('process_control_plan_id')
                ->constrained('process_control_plans')
                ->restrictOnDelete();

            $table->unsignedInteger('approval_step')->default(1);
            $table->string('approval_type', 30)->default('INTERNAL');
            $table->string('decision', 30)->default('PENDING');

            $table->string('approver_name', 150)->nullable();
            $table->string('approver_role', 100)->nullable();
            $table->string('approver_identifier', 150)->nullable();

            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('decided_at')->nullable();

            $table->string('approval_reference', 150)->nullable();
            $table->text('comments')->nullable();

            $table->timestamps();

            $table->index(
                ['process_control_plan_id', 'approval_step'],
                'process_control_approvals_step_index'
            );

            $table->index(['decision', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_control_plan_approvals');
    }
};