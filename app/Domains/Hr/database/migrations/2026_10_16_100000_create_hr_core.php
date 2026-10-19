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
        Schema::create('hr_grades', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->string('name', 100);
            $t->unsignedSmallInteger('rank')->default(1);
            $t->decimal('min_salary', 12, 2)->nullable();
            $t->decimal('max_salary', 12, 2)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('hr_designations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->string('name', 120);
            $t->foreignUuid('grade_id')->nullable()->constrained('hr_grades')->nullOnDelete();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('hr_locations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->string('name', 120);
            $t->string('kind', 12)->default('PLANT');
            $t->string('city', 80)->nullable();
            $t->string('address', 300)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('hr_cost_centres', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->string('name', 120);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('hr_departments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->string('name', 120);
            $t->uuid('parent_id')->nullable();
            $t->foreignUuid('cost_centre_id')->nullable()->constrained('hr_cost_centres')->nullOnDelete();
            $t->uuid('head_employee_id')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('first_name', 80);
            $t->string('last_name', 80)->nullable();
            $t->string('father_name', 120)->nullable();
            $t->string('gender', 1)->nullable();
            $t->date('date_of_birth')->nullable();
            $t->string('marital_status', 10)->nullable();
            $t->string('cnic', 20)->nullable()->unique();
            $t->string('nationality', 60)->nullable();
            $t->string('blood_group', 5)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('personal_email', 160)->nullable();
            $t->string('work_email', 160)->nullable();
            $t->string('address', 400)->nullable();
            $t->string('city', 80)->nullable();
            $t->string('emergency_name', 120)->nullable();
            $t->string('emergency_relation', 60)->nullable();
            $t->string('emergency_phone', 40)->nullable();
            $t->foreignUuid('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $t->foreignUuid('designation_id')->nullable()->constrained('hr_designations')->nullOnDelete();
            $t->foreignUuid('grade_id')->nullable()->constrained('hr_grades')->nullOnDelete();
            $t->foreignUuid('location_id')->nullable()->constrained('hr_locations')->nullOnDelete();
            $t->foreignUuid('cost_centre_id')->nullable()->constrained('hr_cost_centres')->nullOnDelete();
            $t->uuid('manager_id')->nullable();
            $t->string('employment_type', 12)->default('PERMANENT');
            $t->string('status', 12)->default('ONBOARDING');
            $t->date('joined_on');
            $t->date('probation_ends_on')->nullable();
            $t->date('confirmed_on')->nullable();
            $t->date('contract_ends_on')->nullable();
            $t->date('exit_on')->nullable();
            $t->string('bank_name', 100)->nullable();
            $t->string('bank_account_no', 60)->nullable();
            $t->string('ntn', 30)->nullable();
            $t->string('eobi_no', 30)->nullable();
            $t->string('social_security_no', 30)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['status', 'department_id']);
        });
        Schema::table('employees', fn (Blueprint $t) => $t->foreign('manager_id')->references('id')->on('employees')->nullOnDelete());
        Schema::table('hr_departments', fn (Blueprint $t) => $t->foreign('parent_id')->references('id')->on('hr_departments')->nullOnDelete());
        Schema::table('hr_departments', fn (Blueprint $t) => $t->foreign('head_employee_id')->references('id')->on('employees')->nullOnDelete());
        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_status_valid CHECK (status IN ('ONBOARDING','PROBATION','ACTIVE','NOTICE','EXITED'))");

        Schema::create('employee_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->string('kind', 16);
            $t->date('effective_on');
            $t->json('changes')->nullable();
            $t->string('note', 500)->nullable();
            $t->unsignedBigInteger('created_by_id')->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'effective_on']);
        });

        Schema::create('employee_documents', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->string('kind', 14);
            $t->string('title', 200)->nullable();
            $t->string('number', 80)->nullable();
            $t->date('issued_on')->nullable();
            $t->date('expires_on')->nullable();
            $t->timestamps();
        });

        Schema::create('hr_checklist_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('kind', 12);
            $t->string('title', 200);
            $t->string('owner', 20)->default('HR');
            $t->smallInteger('due_days')->default(0);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('employee_tasks', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->string('kind', 12);
            $t->string('title', 200);
            $t->string('owner', 20)->default('HR');
            $t->date('due_on')->nullable();
            $t->timestampTz('done_at')->nullable();
            $t->string('done_by_name', 150)->nullable();
            $t->string('note', 300)->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
            $t->index(['employee_id', 'kind']);
        });

        Schema::create('hr_policies', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->string('title', 200);
            $t->string('category', 12)->default('GENERAL');
            $t->unsignedSmallInteger('version')->default(1);
            $t->string('status', 10)->default('DRAFT');
            $t->date('effective_on')->nullable();
            $t->boolean('requires_ack')->default(true);
            $t->text('body')->nullable();
            $t->timestampTz('published_at')->nullable();
            $t->timestamps();
        });
        DB::statement("ALTER TABLE hr_policies ADD CONSTRAINT hr_policies_status_valid CHECK (status IN ('DRAFT','PUBLISHED','RETIRED'))");
        Schema::create('hr_policy_acks', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('policy_id')->constrained('hr_policies')->cascadeOnDelete();
            $t->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->unsignedSmallInteger('version');
            $t->timestampTz('acknowledged_at');
            $t->string('recorded_by_name', 150)->nullable();
            $t->timestamps();
            $t->unique(['policy_id', 'employee_id', 'version']);
        });

        Schema::create('employee_exits', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->string('exit_type', 16);
            $t->date('notice_given_on')->nullable();
            $t->date('last_working_day');
            $t->string('reason', 500)->nullable();
            $t->string('status', 10)->default('INITIATED');
            $t->string('settlement_status', 10)->default('PENDING');
            $t->text('exit_interview')->nullable();
            $t->boolean('rehire_eligible')->nullable();
            $t->timestampTz('completed_at')->nullable();
            $t->unsignedBigInteger('created_by_id')->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'status']);
        });
        DB::statement("ALTER TABLE employee_exits ADD CONSTRAINT employee_exits_status_valid CHECK (status IN ('INITIATED','COMPLETED','CANCELLED'))");

        // Starting checklists, editable under People → Organisation → Checklists.
        $now = now();
        foreach (config('hr.checklists') as $kind => $items) {
            foreach ($items as $i => [$title, $owner, $days]) {
                DB::table('hr_checklist_items')->insert(['id' => (string) Str::uuid(), 'kind' => $kind, 'title' => $title, 'owner' => $owner, 'due_days' => $days, 'sort' => ($i + 1) * 10, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // The HR manager role, for databases that already have roles.
        if (Schema::hasTable('roles') && ! DB::table('roles')->where('code', 'HR_MANAGER')->exists()) {
            $id = DB::table('roles')->insertGetId(['code' => 'HR_MANAGER', 'name' => 'HR manager', 'is_system' => false, 'created_at' => $now, 'updated_at' => $now]);
            foreach (config('permissions.roles.HR_MANAGER.permissions') as $p) {
                DB::table('role_permissions')->insert(['role_id' => $id, 'permission' => $p]);
            }
        }
    }

    public function down(): void
    {
        foreach (['employee_exits', 'hr_policy_acks', 'hr_policies', 'employee_tasks', 'hr_checklist_items', 'employee_documents', 'employee_events'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('hr_departments', fn (Blueprint $t) => $t->dropForeign(['head_employee_id']));
        Schema::dropIfExists('employees');
        foreach (['hr_departments', 'hr_cost_centres', 'hr_locations', 'hr_designations', 'hr_grades'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
