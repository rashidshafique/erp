<?php

namespace App\Domains\Sales\Console;

use App\Domains\Sales\Services\RepeatingLines;
use Illuminate\Console\Command;

class SalesRepeatDeliveries extends Command
{
    protected $signature = 'sales:repeat-deliveries';

    protected $description = 'Add the next weekly or monthly deliveries to repeating order lines, up to the repeat-ahead rule (safe to repeat)';

    public function handle(RepeatingLines $repeating): int
    {
        $this->info('Added '.$repeating->topUpAll().' deliveries.');

        return self::SUCCESS;
    }
}
