<?php

namespace App\Console\Commands;

use App\Models\MouldingRun;
use App\Services\Production\MouldAssembler;
use Illuminate\Console\Command;

class AssembleFlasklessMoulds extends Command
{
    protected $signature = 'foundry:assemble-flaskless';

    protected $description = 'Assemble the accepted flaskless moulds recorded before moulds were assembled automatically, so they show as ready in Mould readiness';

    public function handle(MouldAssembler $assembler): int
    {
        $made = 0;
        MouldingRun::where('status', '!=', 'CANCELLED')->where('accepted_mould_qty', '>', 0)->orderBy('created_at')
            ->each(function (MouldingRun $run) use ($assembler, &$made) {
                $made += $assembler->assembleFlaskless($run);
            });
        $this->info("Flaskless moulds assembled: {$made}");

        return self::SUCCESS;
    }
}
