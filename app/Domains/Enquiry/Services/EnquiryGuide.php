<?php

namespace App\Domains\Enquiry\Services;

use App\Domains\Enquiry\Models\Enquiry;

/** The checklist shown on an enquiry: what usually happens next for this kind of request, and what is already done. */
class EnquiryGuide
{
    /** @return array<int, array{label:string, done:?bool}>  done null means advice that only a person can tick off */
    public static function steps(Enquiry $e): array
    {
        $e->loadMissing('items.analysis', 'items.product.revisions', 'quotations');
        $sc = config('enquiry_scenarios.'.$e->source_type);
        $items = $e->items;
        $out = [];
        $out[] = ['label' => 'Parts listed', 'done' => $items->isNotEmpty()];
        foreach ($sc['advice'] ?? [] as $a) {
            $out[] = ['label' => $a, 'done' => null];
        }
        if (in_array($e->source_type, ['DRAWING', 'SAMPLE', 'CONCEPT', 'TRANSFER', 'CUSTOMER_TOOL', 'CHANGE'], true)) {
            $out[] = ['label' => 'Casting analysis linked to every part', 'done' => $items->isNotEmpty() && $items->every(fn ($i) => $i->casting_analysis_id)];
        }
        if ($e->source_type === 'REPEAT') {
            $out[] = ['label' => 'Every part has an approved revision', 'done' => $items->isNotEmpty() && $items->every(fn ($i) => $i->product?->revisions->whereIn('status', ['APPROVED', 'ACTIVE'])->isNotEmpty())];
        }
        $out[] = ['label' => 'Feasibility decided for every part', 'done' => $items->isNotEmpty() && $items->every(fn ($i) => $i->feasibility_status !== 'PENDING')];
        $out[] = ['label' => 'Quotation issued', 'done' => $e->quotations->whereIn('status', ['ISSUED', 'ACCEPTED', 'SUPERSEDED', 'EXPIRED', 'REJECTED'])->isNotEmpty()];
        $out[] = ['label' => 'Customer decision recorded', 'done' => in_array($e->status, ['WON', 'LOST', 'CANCELLED'], true)];

        return $out;
    }
}
