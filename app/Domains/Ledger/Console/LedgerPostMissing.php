<?php

namespace App\Domains\Ledger\Console;

use App\Domains\Ledger\Services\PostingRules;
use Illuminate\Console\Command;

class LedgerPostMissing extends Command
{
    protected $signature = 'ledger:post-missing';

    protected $description = 'Post every issued invoice, posted bill, receipt and supplier payment that is not in the ledger yet (safe to repeat)';

    public function handle(PostingRules $rules): int
    {
        $result = $rules->postMissing();
        $this->info("Posted {$result['posted']} documents.");
        foreach ($result['skipped'] as $line) {
            $this->warn('Skipped '.$line);
        }

        return self::SUCCESS;
    }
}
