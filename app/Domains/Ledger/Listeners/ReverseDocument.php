<?php

namespace App\Domains\Ledger\Listeners;

use App\Domains\Finance\Events\DocumentVoided;
use App\Domains\Ledger\Services\PostingRules;

/** Reverses a voided document's journal, dated the day it is voided. */
class ReverseDocument
{
    public function __construct(private PostingRules $rules) {}

    public function handle(DocumentVoided $event): void
    {
        $this->rules->reverse($event->document, today()->toDateString());
    }
}
