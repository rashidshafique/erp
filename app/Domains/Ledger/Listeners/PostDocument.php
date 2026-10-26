<?php

namespace App\Domains\Ledger\Listeners;

use App\Domains\Finance\Events\DocumentPosted;
use App\Domains\Ledger\Services\PostingRules;

/** Writes the journal of a Finance document the moment it counts. A refusal (a closed period, say) undoes the document too. */
class PostDocument
{
    public function __construct(private PostingRules $rules) {}

    public function handle(DocumentPosted $event): void
    {
        $this->rules->post($event->document);
    }
}
