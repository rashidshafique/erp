<?php

namespace App\Console\Commands;

use App\Models\Heat;
use App\Services\Production\ChargeRecorder;
use App\Services\Production\ChargeSchedule;
use App\Support\PouringRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class AddDueCharges extends Command
{
    protected $signature = 'heats:add-due-charges';

    protected $description = 'Record the charges that have come due on running heats, from their recipe, at the times the furnace speed sets';

    public function handle(ChargeSchedule $schedule, ChargeRecorder $recorder): int
    {
        if (! PouringRules::autoCharges()) {
            return self::SUCCESS;
        }

        $added = 0;
        Heat::query()->whereNotNull('actual_start_at')->whereNotNull('charge_batch_id')->whereNull('dropped_at')->whereNull('hold_reason')
            ->whereNotIn('status', ['CLOSED', 'CANCELLED'])->get()
            ->each(function (Heat $heat) use ($schedule, $recorder, &$added) {
                try {
                    $added += $schedule->addDue($heat, $recorder);
                } catch (Throwable $e) {
                    // One heat without stock for a material must not stop the others; its charges stay due for someone to confirm.
                    Log::warning("Charges not added automatically to heat {$heat->heat_number}: ".$e->getMessage());
                }
            });
        $this->info("{$added} charge(s) added.");

        return self::SUCCESS;
    }
}
