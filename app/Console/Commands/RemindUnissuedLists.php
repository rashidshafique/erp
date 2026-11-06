<?php

namespace App\Console\Commands;

use App\Models\MouldingWorkList;
use App\Services\Production\FloorAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RemindUnissuedLists extends Command
{
    protected $signature = 'moulding:remind-unissued';

    protected $description = 'Remind the planner of moulding lists for today or tomorrow that still have rows but are not issued, once a day for each list';

    public function handle(FloorAlerts $alerts): int
    {
        $told = 0;
        MouldingWorkList::query()->where('status', 'DRAFT')->whereBetween('list_date', [today(), today()->addDay()])->has('items')->get()
            ->each(function (MouldingWorkList $list) use ($alerts, &$told) {
                if (Cache::add('list-not-issued:'.$list->id.':'.today()->toDateString(), true, now()->addDay())) {
                    $told += $alerts->listNotIssued($list);
                }
            });
        $this->info("{$told} notice(s) sent.");

        return self::SUCCESS;
    }
}
