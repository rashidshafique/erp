<?php

namespace App\Domains\Development\Services;

use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryEvent;
use App\Domains\Enquiry\Models\EnquiryItem;
use App\Domains\Enquiry\Models\Quotation;
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
     * @param  Quotation|null  $quote  the accepted quote, which sets the tooling cost allowed and who pays
     * @return Collection<int, PatternDevelopment> the developments opened now
     */
    public function open(Enquiry $enquiry, bool $firstTime, bool $won, ?Quotation $quote = null): Collection
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
            $costs = $this->costs($type, $item, $quote);
            if ($existing) {
                $existing->update(['enquiry_item_id' => $item->id, 'product_id' => $existing->product_id ?? $item->product_id]
                    + ($quote ? $costs + ['who_pays' => $existing->who_pays ?? $costs['who_pays']] : []));

                continue;
            }
            $opened->push($this->create($enquiry, $item, $type, $won, $costs));
        }

        return $opened;
    }

    /**
     * A changed pattern decision before the win: open developments of another kind are cancelled, and those the new decision
     * needs are opened at their first stage.
     *
     * @return Collection<int, PatternDevelopment> the developments opened now
     */
    public function onDecision(Enquiry $enquiry): Collection
    {
        $type = self::typeFor($enquiry, (bool) $enquiry->customer?->isFirstTimeCustomer());
        $route = Enquiry::PATTERN_ROUTES[$enquiry->pattern_route][0] ?? 'none';
        foreach ($enquiry->developments()->where('status', 'OPEN')->where('type', '!=', $type ?? '')->get() as $stale) {
            $stale->update(['status' => 'CANCELLED']);
            $stale->events()->create(['kind' => 'CANCELLED', 'text' => "Cancelled: the pattern decision on {$enquiry->code} changed to {$route}.", 'user_name' => Auth::user()?->name]);
            EnquiryEvent::system($enquiry, "Pattern development {$stale->code} cancelled: the pattern decision changed.");
        }

        return $this->open($enquiry, (bool) $enquiry->customer?->isFirstTimeCustomer(), false);
    }

    /**
     * The tooling cost the quote allowed for the part and who pays for the pattern: the customer when tooling is charged up front
     * or spread over the castings, or when it is the customer's own pattern; the foundry when the quote does not charge it.
     *
     * @return array{quoted_tooling_cost?: ?float, cost_currency?: ?string, who_pays?: ?string}
     */
    private function costs(string $type, EnquiryItem $item, ?Quotation $quote): array
    {
        if (! $quote) {
            return [];
        }
        $line = $quote->lines->firstWhere('enquiry_item_id', $item->id);

        return [
            'quoted_tooling_cost' => $line ? (float) $line->tooling_cost : null, 'cost_currency' => $quote->currency,
            'who_pays' => $type === 'CUSTOMER_PATTERN' || $quote->tooling_mode !== 'NONE' ? 'CUSTOMER' : 'FOUNDRY',
        ];
    }

    private function create(Enquiry $enquiry, EnquiryItem $item, string $type, bool $won, array $costs = []): PatternDevelopment
    {
        $start = $won ? (int) (config("pattern_developments.types.$type.enquiry_stages") ?? 0) : 0;
        $development = PatternDevelopment::create($costs + [
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
