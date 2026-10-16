<?php

namespace App\Console\Commands;

use App\Services\Hr\LeaveAccrual;
use Illuminate\Console\Command;

class HrLeaveAccrue extends Command
{
    protected $signature = 'hr:leave-accrue';

    protected $description = 'Add this year\'s leave entitlements and every completed month of monthly leave (safe to repeat)';

    public function handle(LeaveAccrual $acc): int
    {
        $r = $acc->catchUp();
        $this->info("Granted {$r['granted']}, accrued {$r['accrued']}.");

        return self::SUCCESS;
    }
}
