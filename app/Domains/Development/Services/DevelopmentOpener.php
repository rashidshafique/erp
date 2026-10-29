<?php

namespace App\Domains\Development\Services;

use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryEvent;
use App\Domains\Enquiry\Models\EnquiryItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Opens the pattern developments an enquiry needs, one per part, from the pattern decision on it: a new pattern is a new part for
 * a first-time customer and a new product otherwise; an existing pattern for a change to a part we make is a modification; a
 * pattern the customer builds or supplies, or one moved from another foundry, is a customer pattern. A part that already has an
 * open development keeps it and gets its product filled in. Each one opened is written to the enquiry's log.
 */
class DevelopmentOpener
{
    /** The kind of development a part of the enquiry needs, or null when the decision needs none (an existing pattern used as it is). */
    public static function typeFor(Enquiry $enquiry, bool $firstTime): ?string
    {
        return match ($enquiry->pattern_route) {
            'NEW' => $firstTime ? 'NEW_PART' : 'NEW_PRODUCT',
            'CUSTOMER' => 'CUSTOMER_PATTERN',
            'EXISTING' => match ($enquiry->source_type) {
                'CHANGE' => 'MODIFICATION',
                'TRANSFER' => 'CUSTOMER_PATTERN',
                default => null,
            },
            default => null,
        };
    }

    /**
     * @param  bool  $won  opened on a won enquiry, so the stages the enquiry already covered are skipped
     * @return Collection<int, PatternDevelopment> the developments opened now
     */
    public function open(Enquiry $enquiry, bool $firstTime, bool $won): Collection
    {
        $type = self::typeFor($enquiry, $firstTime);
        if ($type === null) {
            return collect();
        }
        $items = $enquiry->items()->get();
        $open = $enquiry->developments()->where('status', 'OPEN')->get();
        $opened = collect();

        foreach ($items as $item) {
            $existing = $open->firstWhere('enquiry_item_id', $item->id) ?? ($items->count() === 1 ? $open->firstWhere('enquiry_item_id', null) : null);
            if ($existing) {
                $existing->update(['enquiry_item_id' => $item->id, 'product_id' => $existing->product_id ?? $item->product_id]);

                continue;
            }
            $opened->push($this->create($enquiry, $item, $type, $won));
        }

        return $opened;
    }

    private function create(Enquiry $enquiry, EnquiryItem $item, string $type, bool $won): PatternDevelopment
    {
        $start = $won ? (int) (config("pattern_developments.types.$type.enquiry_stages") ?? 0) : 0;
        $development = PatternDevelopment::create([
            'type' => $type, 'title' => $item->description ?: $enquiry->title, 'enquiry_id' => $enquiry->id, 'enquiry_item_id' => $item->id,
            'customer_id' => $enquiry->customer_id, 'product_id' => $item->product_id, 'pattern_id' => $type === 'MODIFICATION' || $type === 'CUSTOMER_PATTERN' ? $enquiry->pattern_id : null,
            'pattern_by' => $type === 'CUSTOMER_PATTERN' ? 'CUSTOMER_SUPPLIED' : 'DECIDE_LATER', 'stage_index' => $start,
            'owner_name' => $enquiry->owner?->name ?? Auth::user()?->name, 'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name,
        ]);
        $development->events()->create(['kind' => 'CREATED', 'user_name' => Auth::user()?->name,
            'text' => ($won ? "Opened when {$enquiry->code} was won" : "Opened when the pattern was decided on {$enquiry->code}").". First stage: {$development->stageName()}."]);
        EnquiryEvent::system($enquiry, "Pattern development {$development->code} opened for {$development->title}.");

        return $development;
    }
}
