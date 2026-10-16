<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_shifts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->string('name', 100);
            $t->time('starts_at');
            $t->time('ends_at');
            $t->unsignedSmallInteger('break_minutes')->default(0);
            $t->unsignedSmallInteger('grace_minutes')->default(10);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::table('employees', function (Blueprint $t) {
            $t->foreignUuid('shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();
            $t->string('weekly_off', 10)->default('0');
        });

        Schema::create('hr_roster_entries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->date('work_date');
            $t->foreignUuid('shift_id')->nullable()->constrained('hr_shifts')->cascadeOnDelete();   // null = day off
            $t->timestamps();
            $t->unique(['employee_id', 'work_date']);
        });

        Schema::create('hr_holidays', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->date('holiday_on');
            $t->string('name', 120);
            $t->string('kind', 10)->default('PUBLIC');
            $t->timestamps();
            $t->unique(['holiday_on', 'name']);
        });

        Schema::create('attendance_records', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->date('work_date');
            $t->foreignUuid('shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();
            $t->string('day_kind', 8)->default('WORK');          // what the day was meant to be: WORK, OFF, HOLIDAY, LEAVE
            $t->timestampTz('check_in')->nullable();
            $t->timestampTz('check_out')->nullable();
            $t->string('status', 10)->default('PRESENT');
            $t->unsignedSmallInteger('late_minutes')->default(0);
            $t->unsignedSmallInteger('early_out_minutes')->default(0);
            $t->unsignedSmallInteger('worked_minutes')->default(0);
            $t->unsignedSmallInteger('overtime_minutes')->default(0);
            $t->string('overtime_status', 10)->default('NONE');   // NONE, PENDING, APPROVED, REJECTED
            $t->unsignedSmallInteger('overtime_approved_minutes')->default(0);
            $t->json('flags')->nullable();
            $t->string('exception_status', 10)->default('NONE');  // NONE, OPEN, RESOLVED
            $t->string('resolution', 300)->nullable();
            $t->string('source', 8)->default('MANUAL');           // MANUAL, IMPORT, LEAVE
            $t->string('note', 300)->nullable();
            $t->unsignedBigInteger('updated_by_id')->nullable();
            $t->string('updated_by_name', 150)->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'work_date']);
            $t->index(['work_date', 'exception_status']);
            $t->index(['overtime_status', 'work_date']);
        });
        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_status_valid CHECK (status IN ('PRESENT','HALF_DAY','ABSENT','LEAVE','OFF','HOLIDAY'))");

        Schema::create('leave_types', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 12)->unique();
            $t->string('name', 100);
            $t->boolean('paid')->default(true);
            $t->decimal('annual_days', 6, 2)->default(0);
            $t->string('accrual', 8)->default('NONE');            // NONE, MONTHLY, UPFRONT
            $t->decimal('carry_forward_max', 6, 2)->default(0);
            $t->boolean('allow_half_day')->default(true);
            $t->boolean('counts_days_off')->default(false);
            $t->boolean('after_probation_only')->default(false);
            $t->string('gender', 1)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('leave_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->foreignUuid('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $t->date('from_date');
            $t->date('to_date');
            $t->boolean('half_day')->default(false);
            $t->decimal('days', 6, 2);
            $t->string('reason', 500)->nullable();
            $t->string('status', 10)->default('PENDING');          // PENDING, APPROVED, REJECTED, CANCELLED
            $t->unsignedBigInteger('requested_by_id')->nullable();
            $t->string('requested_by_name', 150)->nullable();
            $t->unsignedBigInteger('decided_by_id')->nullable();
            $t->string('decided_by_name', 150)->nullable();
            $t->timestampTz('decided_at')->nullable();
            $t->string('decision_note', 300)->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'from_date']);
            $t->index(['status', 'from_date']);
        });
        DB::statement("ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_status_valid CHECK (status IN ('PENDING','APPROVED','REJECTED','CANCELLED'))");

        Schema::create('leave_ledger', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->foreignUuid('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $t->date('entry_date');
            $t->string('kind', 10);                                // GRANT, ACCRUAL, TAKEN, RESTORED, ADJUST, CARRY, EXPIRE
            $t->decimal('days', 7, 2);                             // + adds, - uses
            $t->string('ref', 60)->nullable();                     // makes automatic entries safe to run twice
            $t->foreignUuid('leave_request_id')->nullable()->constrained('leave_requests')->nullOnDelete();
            $t->string('note', 300)->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'leave_type_id', 'ref']);
            $t->index(['employee_id', 'leave_type_id']);
        });

        $now = now();
        foreach (config('hr.default_shifts') as $i => [$name, $in, $out, $break, $grace]) {
            DB::table('hr_shifts')->insert(['id' => (string) Str::uuid(), 'code' => 'SHF-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT), 'name' => $name, 'starts_at' => $in, 'ends_at' => $out, 'break_minutes' => $break, 'grace_minutes' => $grace, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (config('hr.leave_types') as [$code, $name, $paid, $days, $accrual, $carry, $half, $off, $prob, $gender]) {
            DB::table('leave_types')->insert(['id' => (string) Str::uuid(), 'code' => $code, 'name' => $name, 'paid' => $paid, 'annual_days' => $days, 'accrual' => $accrual, 'carry_forward_max' => $carry, 'allow_half_day' => $half, 'counts_days_off' => $off, 'after_probation_only' => $prob, 'gender' => $gender, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        foreach (['leave_ledger', 'leave_requests', 'leave_types', 'attendance_records', 'hr_holidays', 'hr_roster_entries'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('employees', function (Blueprint $t) {
            $t->dropForeign(['shift_id']);
            $t->dropColumn(['shift_id', 'weekly_off']);
        });
        Schema::dropIfExists('hr_shifts');
    }
};
