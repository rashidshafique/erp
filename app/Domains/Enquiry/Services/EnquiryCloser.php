<?php

namespace App\Domains\Enquiry\Services;

use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryEvent;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes an enquiry (design 14c). Won accepts the issued quotation, which creates the sales order and opens the pattern
 * developments the pattern decision needs (QuotationService). Lost and declined need a reason; lost may record the winning
 * price; both may set a date to follow up, when the owner is reminded. Open quotations are marked rejected, while drafts and the
 * feasibility review stay for reference.
 */
class EnquiryCloser
{
    public function __construct(private QuotationService $quotations) {}

    /**
     * @param  array{outcome: string, close_reason?: ?string, winning_price?: mixed, note?: ?string, follow_up_on?: ?string}  $d
     * @return array{order: ?SalesOrder, developments: \Illuminate\Support\Collection<int, PatternDevelopment>}
     *
     * @throws ValidationException
     */
    public function close(Enquiry $enquiry, array $d): array
    {
        abort_unless($enquiry->isOpen(), 422);

        return $d['outcome'] === 'WON' ? $this->win($enquiry, $d) : $this->loseOrDecline($enquiry, $d);
    }

    /** @return array{order: ?SalesOrder, developments: \Illuminate\Support\Collection<int, PatternDevelopment>} */
    private function win(Enquiry $enquiry, array $d): array
    {
        $quote = $enquiry->quotations()->where('status', 'ISSUED')->orderByDesc('version')->first();
        if (! $quote) {
            throw ValidationException::withMessages(['outcome' => 'Won needs an issued quotation for the customer to accept. Issue the quotation first.']);
        }

        return DB::transaction(function () use ($enquiry, $quote, $d) {
            try {
                $order = $this->quotations->accept($quote);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['outcome' => $e->getMessage()]);
            }
            $enquiry->refresh();
            $enquiry->update(['close_reason' => null, 'lost_reason' => $d['note'] ?? null]);
            EnquiryEvent::system($enquiry, "Closed as won. {$quote->code} accepted; sales order {$order->order_number} created.".(filled($d['note'] ?? null) ? ' '.$d['note'] : ''));

            return ['order' => $order, 'developments' => $enquiry->developments()->where('status', 'OPEN')->orderBy('code')->get()];
        });
    }

    /** @return array{order: null, developments: \Illuminate\Support\Collection<int, PatternDevelopment>} */
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
            $this->cancelDevelopments($enquiry, strtolower(Enquiry::OUTCOMES[$d['outcome']][0]));
            $enquiry->quotations()->whereIn('status', ['ISSUED'])->update(['status' => 'REJECTED', 'decided_at' => now(), 'decision_note' => Enquiry::CLOSE_REASONS[$d['close_reason']]]);
            EnquiryEvent::system($enquiry, 'Closed as '.strtolower(Enquiry::OUTCOMES[$d['outcome']][0]).': '.Enquiry::CLOSE_REASONS[$d['close_reason']].'.'
                .(filled($d['note'] ?? null) ? ' '.$d['note'] : '')
                .(filled($d['follow_up_on'] ?? null) ? ' Follow up on '.\Illuminate\Support\Carbon::parse($d['follow_up_on'])->displayDate().'.' : ''));
        });

        return ['order' => null, 'developments' => collect()];
    }

    /** Developments started before the enquiry was lost or declined have nothing left to make, so they are cancelled. */
    private function cancelDevelopments(Enquiry $enquiry, string $outcome): void
    {
        if (! class_exists(PatternDevelopment::class)) {
            return;
        }
        foreach ($enquiry->developments()->where('status', 'OPEN')->get() as $development) {
            $development->update(['status' => 'CANCELLED']);
            $development->events()->create(['kind' => 'CANCELLED', 'text' => "Cancelled: {$enquiry->code} was {$outcome}.", 'user_name' => auth()->user()?->name]);
            EnquiryEvent::system($enquiry, "Pattern development {$development->code} cancelled.");
        }
    }
}
