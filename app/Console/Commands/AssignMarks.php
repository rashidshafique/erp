<?php

namespace App\Console\Commands;

use App\Services\Codes\CodeGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignMarks extends Command
{
    protected $signature = 'foundry:assign-marks';

    protected $description = 'Give every tool without an engraving mark one (existing tooling from before marks were added)';

    public function handle(CodeGenerator $gen): int
    {
        $total = 0;
        foreach (CodeGenerator::TOOL_KINDS as $kind) {
            $table = CodeGenerator::kinds()[$kind][0];
            foreach (DB::table($table)->whereNull('mark_code')->whereNull('deleted_at')->orderBy('created_at')->pluck('id') as $id) {
                $gen->ensureMark($kind, $table, (string) $id);
                $total++;
            }
        }
        $this->info("Marks assigned: {$total}");

        return self::SUCCESS;
    }
}
