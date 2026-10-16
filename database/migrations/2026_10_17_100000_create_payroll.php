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
        Schema::table('employees', function (Blueprint $t) {
            $t->string('pay_mode', 8)->default('BANK');
            $t->boolean('tax_applies')->default(true);
            $t->boolean('eobi_applies')->default(true);
            $t->boolean('ss_applies')->default(false);
            $t->boolean('overtime_eligible')->default(true);
        });

        Schema::create('pay_components', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 20)->unique();
            $t->string('name', 100);
            $t->string('kind', 10)->default('EARNING');       // EARNING, DEDUCTION
            $t->string('calc', 14)->default('FIXED');         // FIXED, PERCENT_BASIC
            $t->decimal('default_value', 12, 2)->default(0);
            $t->boolean('taxable')->default(true);
            $t->boolean('prorate')->default(true);            // cut for absent days
            $t->boolean('is_basic')->default(false);
            $t->boolean('active')->default(true);
            $t->unsignedSmallInteger('sort')->default(10);
            $t->timestamps();
        });

        Schema::create('employee_salaries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->date('effective_from');
            $t->decimal('basic', 12, 2);
            $t->string('reason', 200)->nullable();
            $t->unsignedBigInteger('created_by_id')->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'effective_from']);
        });
        Schema::create('employee_salary_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('salary_id')->constrained('employee_salaries')->cascadeOnDelete();
            $t->foreignUuid('component_id')->constrained('pay_components')->restrictOnDelete();
            $t->decimal('amount', 12, 2);
            $t->unique(['salary_id', 'component_id']);
        });

        Schema::create('employee_benefits', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('kind', 8)->default('CASH');           // CASH, IN_KIND
            $t->decimal('monthly_value', 12, 2)->default(0);
            $t->boolean('taxable')->default(true);
            $t->date('starts_on');
            $t->date('ends_on')->nullable();
            $t->string('note', 300)->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_periods', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 20)->unique();
            $t->date('month')->unique();                       // first day of the month
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('status', 12)->default('DRAFT');        // DRAFT, CALCULATED, APPROVED, PAID
            $t->json('summary')->nullable();
            $t->timestampTz('calculated_at')->nullable();
            $t->timestampTz('approved_at')->nullable();
            $t->string('approved_by_name', 150)->nullable();
            $t->timestampTz('paid_at')->nullable();
            $t->string('notes', 300)->nullable();
            $t->timestamps();
        });
        DB::statement("ALTER TABLE payroll_periods ADD CONSTRAINT payroll_periods_status_valid CHECK (status IN ('DRAFT','CALCULATED','APPROVED','PAID'))");

        Schema::create('payslips', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $t->foreignUuid('employee_id')->constrained('employees')->restrictOnDelete();
            $t->string('employee_code', 30);
            $t->string('employee_name', 200);
            $t->uuid('department_id')->nullable();
            $t->string('department_name', 120)->nullable();
            $t->uuid('cost_centre_id')->nullable();
            $t->string('cost_centre_name', 120)->nullable();
            $t->string('designation_name', 120)->nullable();
            $t->decimal('basic', 12, 2)->default(0);
            $t->decimal('period_days', 5, 2)->default(0);      // the divisor: days a full month is worth
            $t->decimal('payable_days', 5, 2)->default(0);
            $t->decimal('unpaid_days', 5, 2)->default(0);
            $t->decimal('absent_days', 5, 2)->default(0);
            $t->decimal('unpaid_leave_days', 5, 2)->default(0);
            $t->decimal('late_count', 5, 0)->default(0);
            $t->unsignedInteger('overtime_minutes')->default(0);
            $t->decimal('gross', 12, 2)->default(0);
            $t->decimal('taxable_income', 12, 2)->default(0);
            $t->decimal('income_tax', 12, 2)->default(0);
            $t->decimal('eobi_employee', 12, 2)->default(0);
            $t->decimal('eobi_employer', 12, 2)->default(0);
            $t->decimal('ss_employer', 12, 2)->default(0);
            $t->decimal('advance_recovery', 12, 2)->default(0);
            $t->decimal('total_earnings', 12, 2)->default(0);
            $t->decimal('total_deductions', 12, 2)->default(0);
            $t->decimal('net', 12, 2)->default(0);
            $t->string('pay_mode', 8)->default('BANK');
            $t->string('bank_name', 120)->nullable();
            $t->string('bank_account_no', 60)->nullable();
            $t->string('status', 10)->default('UNPAID');       // UNPAID, HELD, PAID
            $t->string('hold_reason', 200)->nullable();
            $t->date('paid_on')->nullable();
            $t->string('payment_ref', 100)->nullable();
            $t->json('flags')->nullable();
            $t->timestamps();
            $t->unique(['period_id', 'employee_id']);
        });
        DB::statement("ALTER TABLE payslips ADD CONSTRAINT payslips_status_valid CHECK (status IN ('UNPAID','HELD','PAID'))");

        Schema::create('payslip_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('payslip_id')->constrained('payslips')->cascadeOnDelete();
            $t->string('kind', 10);                            // EARNING, DEDUCTION, EMPLOYER
            $t->string('code', 20)->nullable();
            $t->string('name', 160);
            $t->decimal('amount', 12, 2);
            $t->string('source', 12)->default('STRUCTURE');    // STRUCTURE, BENEFIT, OVERTIME, ADJUST, TAX, EOBI, SS, ADVANCE
            $t->uuid('ref_id')->nullable();
            $t->boolean('taxable')->default(false);
            $t->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('payroll_adjustments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->date('month');                                  // first day of the month it belongs to
            $t->string('kind', 10)->default('EARNING');         // EARNING, DEDUCTION
            $t->string('name', 120);
            $t->decimal('amount', 12, 2);
            $t->boolean('taxable')->default(true);
            $t->string('note', 300)->nullable();
            $t->uuid('payslip_id')->nullable();                 // set once a run is approved with it
            $t->string('created_by_name', 150)->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'month']);
        });

        Schema::create('employee_advances', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->foreignUuid('employee_id')->constrained('employees')->restrictOnDelete();
            $t->string('kind', 8)->default('ADVANCE');         // ADVANCE, LOAN
            $t->decimal('amount', 12, 2);
            $t->unsignedSmallInteger('installments')->default(1);
            $t->decimal('installment_amount', 12, 2);
            $t->date('first_recovery_month');
            $t->string('reason', 300)->nullable();
            $t->string('status', 10)->default('PENDING');      // PENDING, ACTIVE, CLEARED, REJECTED, CANCELLED
            $t->boolean('paused')->default(false);
            $t->date('disbursed_on')->nullable();
            $t->string('requested_by_name', 150)->nullable();
            $t->string('decided_by_name', 150)->nullable();
            $t->timestampTz('decided_at')->nullable();
            $t->timestamps();
        });
        DB::statement("ALTER TABLE employee_advances ADD CONSTRAINT employee_advances_status_valid CHECK (status IN ('PENDING','ACTIVE','CLEARED','REJECTED','CANCELLED'))");
        Schema::create('advance_ledger', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('advance_id')->constrained('employee_advances')->cascadeOnDelete();
            $t->string('kind', 10);                            // DISBURSE, RECOVERY, WAIVER, ADJUST
            $t->decimal('amount', 12, 2);                      // signed: + adds to what is owed, - reduces it
            $t->date('entry_date');
            $t->uuid('payslip_id')->nullable();
            $t->uuid('settlement_id')->nullable();
            $t->string('note', 200)->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->timestamps();
        });

        Schema::create('employee_settlements', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('exit_id')->unique()->constrained('employee_exits')->cascadeOnDelete();
            $t->foreignUuid('employee_id')->constrained('employees')->restrictOnDelete();
            $t->string('status', 10)->default('DRAFT');        // DRAFT, APPROVED, PAID
            $t->date('last_working_day');
            $t->decimal('total_due', 12, 2)->default(0);
            $t->decimal('total_recovery', 12, 2)->default(0);
            $t->decimal('net', 12, 2)->default(0);
            $t->timestampTz('calculated_at')->nullable();
            $t->timestampTz('approved_at')->nullable();
            $t->string('approved_by_name', 150)->nullable();
            $t->date('paid_on')->nullable();
            $t->string('payment_method', 8)->nullable();
            $t->string('payment_ref', 100)->nullable();
            $t->string('notes', 300)->nullable();
            $t->timestamps();
        });
        DB::statement("ALTER TABLE employee_settlements ADD CONSTRAINT employee_settlements_status_valid CHECK (status IN ('DRAFT','APPROVED','PAID'))");
        Schema::create('settlement_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('settlement_id')->constrained('employee_settlements')->cascadeOnDelete();
            $t->string('kind', 9);                             // DUE, RECOVERY
            $t->string('code', 20)->nullable();
            $t->string('name', 160);
            $t->decimal('amount', 12, 2);
            $t->boolean('auto')->default(true);
            $t->string('note', 300)->nullable();
            $t->uuid('ref_id')->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
        });

        $now = now();
        foreach (config('payroll.default_components') as $i => [$code, $name, $kind, $calc, $val, $tax, $pro, $basic]) {
            DB::table('pay_components')->insert(['id' => (string) Str::uuid(), 'code' => $code, 'name' => $name, 'kind' => $kind, 'calc' => $calc, 'default_value' => $val, 'taxable' => $tax, 'prorate' => $pro, 'is_basic' => $basic, 'active' => true, 'sort' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        foreach (['settlement_lines', 'employee_settlements', 'advance_ledger', 'employee_advances', 'payroll_adjustments', 'payslip_lines', 'payslips', 'payroll_periods', 'employee_benefits', 'employee_salary_lines', 'employee_salaries', 'pay_components'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn(['pay_mode', 'tax_applies', 'eobi_applies', 'ss_applies', 'overtime_eligible']));
    }
};
