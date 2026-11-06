<?php

namespace App\Console\Commands;

use App\Models\Heat;
use App\Services\Production\ChargeSchedule;
use App\Services\Production\FloorAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarnOverdueCharges extends Command
{
    protected $signature = 'heats:warn-overdue-charges {--grace=10 : Minutes past the due time before anyone is told}';

    protected $description = 'Tell the melt shop and planner when a charge on a running heat is late, once for each charge, and settle the notices whose charge is now in';

    public function handle(ChargeSchedule $schedule, FloorAlerts $alerts): int
    {
        $settled = $alerts->settleCharges();
        $told = 0;
        Heat::query()->whereIn('status', Heat::RUNNING_STATUSES)->whereNotNull('actual_start_at')->whereNotNull('charge_batch_id')
            ->whereNull('dropped_at')->whereNull('hold_reason')->get()
            ->each(function (Heat $heat) use ($schedule, $alerts, &$told) {
                $late = $schedule->for($heat)['rounds']->first(fn (array $round) => $round['state'] === 'due' && $round['due_at'] !== null
                    && $round['due_at']->diffInMinutes(now(), false) >= (int) $this->option('grace'));
                if ($late && Cache::add("charge-overdue:{$heat->id}:{$late['round']}", true, now()->addDay())) {
                    $told += $alerts->chargeOverdue($heat, $late['round'], (int) $late['due_at']->diffInMinutes(now()));
                }
            });
        $this->info("{$told} notice(s) sent, {$settled} settled.");

        return self::SUCCESS;
    }
}
