<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every record says who created it, for the audit trail and for roles limited to their own records. The column is filled for records
 * made from now on; older records stay without an owner. System tables and tables that already name a creator are left alone.
 */
return new class extends Migration
{
    private const SKIP = ['users', 'roles', 'jobs', 'job_batches', 'failed_jobs', 'notifications', 'password_reset_tokens', 'app_settings', 'telescope_entries', 'sessions', 'cache', 'cache_locks', 'migrations'];

    /** Tables people filter on by owner. */
    private const INDEXED = ['sales_orders', 'quotations', 'enquiries', 'quality_inspections', 'nonconformance_reports'];

    /** @return list<string> */
    private function tables(): array
    {
        return collect(Schema::getTableListing())->map(fn ($t) => preg_replace('/^public\./', '', $t))
            ->filter(fn ($t) => ! in_array($t, self::SKIP, true) && Schema::hasColumn($t, 'created_at'))->values()->all();
    }

    public function up(): void
    {
        foreach ($this->tables() as $table) {
            if (Schema::hasColumn($table, 'created_by_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                $col = $t->unsignedBigInteger('created_by_id')->nullable();
                if (in_array($table, self::INDEXED, true)) {
                    $col->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $table) {
            if (Schema::hasColumn($table, 'created_by_id') && ! in_array($table, ['casting_analyses', 'employee_events', 'employee_exits', 'employee_salaries', 'enquiries', 'import_batches', 'pattern_developments', 'quotations'], true)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('created_by_id'));
            }
        }
    }
};
