<?php

namespace App\Console\Commands;

use App\Services\Hr\AttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class HrCloseAttendance extends Command
{
    protected $signature = 'hr:close-attendance {date? : Day to close, default yesterday}';

    protected $description = 'Give everyone without an attendance record for a day an absent, day-off, holiday or leave record';

    public function handle(AttendanceService $svc): int
    {
        $d = Carbon::parse($this->argument('date') ?: now()->subDay()->toDateString())->toDateString();
        $this->info("$d: ".$svc->closeDay($d).' record(s) added.');

        return self::SUCCESS;
    }
}
