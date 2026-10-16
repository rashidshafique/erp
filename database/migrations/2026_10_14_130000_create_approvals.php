<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_policies', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('action_key')->unique();
            $t->boolean('is_active')->default(false);
            $t->string('permission')->default('approvals.decide');
            $t->unsignedSmallInteger('required_approvals')->default(1);
            $t->boolean('allow_self_approval')->default(false);
            $t->timestamps();
        });
        Schema::create('approval_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('action_key');
            $t->string('subject_type', 60);
            $t->string('subject_id', 64);
            $t->string('subject_label')->nullable();
            $t->string('status', 20)->default('PENDING');
            $t->unsignedSmallInteger('required_approvals')->default(1);
            $t->boolean('allow_self_approval')->default(false);
            $t->string('permission')->default('approvals.decide');
            $t->unsignedBigInteger('requested_by_id')->nullable();
            $t->string('requested_by_name')->nullable();
            $t->text('note')->nullable();
            $t->timestampTz('consumed_at')->nullable();
            $t->timestamps();
            $t->index(['action_key', 'subject_type', 'subject_id', 'status']);
        });
        DB::statement("ALTER TABLE approval_requests ADD CONSTRAINT approval_requests_status_chk CHECK (status IN ('PENDING','APPROVED','REJECTED','USED','CANCELLED'))");
        Schema::create('approval_decisions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('approval_request_id')->constrained()->cascadeOnDelete();
            $t->string('decision', 10);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('user_name')->nullable();
            $t->text('comment')->nullable();
            $t->timestamps();
            $t->unique(['approval_request_id', 'user_id']);
        });
        DB::statement("ALTER TABLE approval_decisions ADD CONSTRAINT approval_decisions_chk CHECK (decision IN ('APPROVE','REJECT'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_policies');
    }
};
