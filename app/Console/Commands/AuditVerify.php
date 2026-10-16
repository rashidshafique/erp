<?php

namespace App\Console\Commands;

use App\Services\Audit\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditVerify extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Check the audit log hash chain for gaps or edits';

    public function handle(): int
    {
        $prev = str_repeat('0', 64);
        $n = 0;
        $lastId = 0;
        foreach (DB::table('audit_logs')->selectRaw("*, to_char(occurred_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US') as at_text")->orderBy('id')->cursor() as $r) {
            $n++;
            $row = (array) $r;
            if ($r->prev_hash !== $prev) {
                $this->error("Chain broken before entry {$r->id}: it does not follow the entry before it (an entry was removed or inserted).");

                return self::FAILURE;
            }
            if (Audit::hashRow($prev, $r->at_text, $row) !== $r->hash) {
                $this->error("Entry {$r->id} does not match its hash: it was altered.");

                return self::FAILURE;
            }
            $prev = $r->hash;
            $lastId = $r->id;
        }
        $this->info("Audit log intact: {$n} entries verified (last id {$lastId}).");

        return self::SUCCESS;
    }
}
