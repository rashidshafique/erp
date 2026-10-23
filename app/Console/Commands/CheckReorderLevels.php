<?php

namespace App\Console\Commands;

use App\Services\Stock\ReorderWatcher;
use Illuminate\Console\Command;

class CheckReorderLevels extends Command
{
    protected $signature = 'stock:check-reorder';

    protected $description = 'Raise reorder notices for materials at or below their minimum stock';

    public function handle(ReorderWatcher $watcher): int
    {
        $this->info($watcher->checkAll().' reorder notice(s) raised.');

        return self::SUCCESS;
    }
}
