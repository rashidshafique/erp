<?php

namespace App\Domains\Enquiry\Services;

use App\Domains\Core\ModuleRegistry;
use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryEvent;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes an enquiry (design 14c). Won accepts the issued quotation, which creates the sales order, and opens the pattern
 * development when a new pattern was decided and none is open yet. Lost and declined need a reason; lost may record the winning
 * price; both may set a date to follow up, when the owner is reminded. Open quotations are marked rejected, while drafts and the
 * feasibility review stay for reference.
 */
class EnquiryCloser
{
    public function __construct(private QuotationService $quotations, private ModuleRegistry $modules) {}

    /**
     * @param  array{outcome: string, close_reason?: ?string, winning_price?: mixed, note?: ?string, follow_up_on?: ?string}  $d
     * @return array{order: ?SalesOrder, development: ?PatternDevelopment}
     *
     * @throws ValidationException
     */
    public function close(Enquiry $enquiry, array $d): array
    {
        abort_unless($enquiry->isOpen(), 422);

        return $d['outcome'] === 'WON' ? $this->win($enquiry, $d) : $this->loseOrDecline($enquiry, $d);
    }

    /** @return array{order: ?SalesOrder, development: ?PatternDevelopment} */
    private function win(Enquiry $enquiry, array $d): array
    {
        $quote = $enquiry->quotations()->where('status', 'ISSUED')->orderByDesc('version')->first();
        if (! $quote) {
            throw ValidationException::withMessages(['outcome' => 'Won needs an issued quotation for the customer to accept. Issue the quotation first.']);
        }

        $firstTime = (bool) $enquiry->customer?->isFirstTimeCustomer();

        return DB::transaction(function () use ($enquiry, $quote, $d, $firstTime) {
            try {
                $order = $this->quotations->accept($quote);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['outcome' => $e->getMessage()]);
            }
            $enquiry->refresh();
            $enquiry->update(['close_reason' => null, 'lost_reason' => $d['note'] ?? null]);
            $development = $this->developmentFor($enquiry, $firstTime);
            EnquiryEvent::system($enquiry, "Closed as won. {$quote->code} accepted; sales order {$order->order_number} created."
                .($development ? " Pattern development {$development->code} opened." : '').(filled($d['note'] ?? null) ? ' '.$d['note'] : ''));

            return ['order' => $order, 'development' => $development];
        });
    }

    /** @return array{order: null, development: null} */
    private function loseOrDecline(Enquiry $enquiry, array $d): array
    {
        if (blank($d['close_reason'] ?? null)) {
            throw ValidationException::withMessages(['close_reason' => 'Choose why it was '.($d['outcome'] === 'DECLINED' ? 'declined.' : 'lost.')]);
        }

        DB::transaction(function () use ($enquiry, $d) {
            $enquiry->update([
                'status' => $d['outcome'], 'close_reason' => $d['close_reason'], 'lost_reason' => $d['note'] ?? null, 'closed_at' => now(),
                'winning_price' => $d['outcome'] === 'LOST' && filled($d['winning_price'] ?? null) ? $d['winning_price'] : null,
                'follow_up_on' => $d['follow_up_on'] ?? null, 'follow_up_sent_at' => null,
            ]);
            $enquiry->quotations()->whereIn('status', ['ISSUED'])->update(['status' => 'REJECTED', 'decided_at' => now(), 'decision_note' => Enquiry::CLOSE_REASONS[$d['close_reason']]]);
            EnquiryEvent::system($enquiry, 'Closed as '.strtolower(Enquiry::OUTCOMES[$d['outcome']][0]).': '.Enquiry::CLOSE_REASONS[$d['close_reason']].'.'
                .(filled($d['note'] ?? null) ? ' '.$d['note'] : '')
                .(filled($d['follow_up_on'] ?? null) ? ' Follow up on '.\Illuminate\Support\Carbon::parse($d['follow_up_on'])->displayDate().'.' : ''));
        });

        return ['order' => null, 'development' => null];
    }

    /** A won enquiry with a new pattern decided gets its development, unless one is already open or the module is off. A first-time customer's is a new part; an established customer's a new product. */
    private function developmentFor(Enquiry $enquiry, bool $firstTime): ?PatternDevelopment
    {
        if ($enquiry->pattern_route !== 'NEW' || ! $this->modules->isEnabled('development') || ! class_exists(PatternDevelopment::class)) {
            return null;
        }
        if ($enquiry->developments()->where('status', 'OPEN')->exists()) {
            return null;
        }
        $development = PatternDevelopment::create([
            'type' => $firstTime ? 'NEW_PART' : 'NEW_PRODUCT', 'title' => $enquiry->items()->first()?->description ?: $enquiry->title, 'enquiry_id' => $enquiry->id,
            'customer_id' => $enquiry->customer_id, 'owner_name' => $enquiry->owner?->name ?? Auth::user()?->name,
            'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name,
        ]);
        $development->events()->create(['kind' => 'CREATED', 'text' => "Opened when {$enquiry->code} was won. First stage: {$development->stageName()}.", 'user_name' => Auth::user()?->name]);

        return $development;
    }
}
