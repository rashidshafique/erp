<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capa_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('source_type', 20)->default('OTHER');
            $table->uuid('source_id')->nullable();
            $table->string('source_label', 200)->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('severity', 10)->default('MINOR');
            $table->string('status', 24)->default('OPEN');
            $table->text('containment_action')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->text('preventive_action')->nullable();
            $table->string('owner', 150)->nullable();
            $table->date('due_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->text('implementation_notes')->nullable();
            $table->date('verification_due_on')->nullable();
            $table->string('verification_result', 14)->nullable();
            $table->text('verification_notes')->nullable();
            $table->string('verified_by', 150)->nullable();
            $table->date('verified_on')->nullable();
            $table->text('verification_log')->nullable();
            $table->string('raised_by', 150)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->jsonb('custom_fields')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_on']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capa_actions');
    }
};
