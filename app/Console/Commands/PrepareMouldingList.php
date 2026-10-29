<?php

namespace App\Console\Commands;

use App\Services\Production\DailyMouldingList;
use App\Support\WorkListRules;
use Illuminate\Console\Command;
use InvalidArgumentException;

class PrepareMouldingList extends Command
{
    protected $signature = 'moulding:prepare-list {--day= : The day to prepare (default: from the work list rule, tomorrow or today)} {--force : Prepare even when the rule is off}';

    protected $description = 'Prepare the day\'s moulding list from open orders as a draft, carrying over unfinished rows';

    public function handle(DailyMouldingList $lists): int
    {
        $rule = WorkListRules::get('auto_prepare');
        if ($rule === 'OFF' && ! $this->option('force')) {
            $this->info('Preparing lists automatically is off.');

            return self::SUCCESS;
        }
        $day = $this->option('day') ? now()->parse($this->option('day')) : ($rule === 'SAME_DAY' ? today() : today()->addDay());

        try {
            $res = $lists->prepare($day);
        } catch (InvalidArgumentException $e) {
            $this->info($e->getMessage());

            return self::SUCCESS;
        }
        $this->info($res['created']
            ? "Prepared {$res['list']->list_number} for {$day->toDateString()} with {$res['list']->items()->count()} rows ({$res['carried']} carried over)."
            : "{$res['list']->list_number} already exists for {$day->toDateString()}.");

        return self::SUCCESS;
    }
}
