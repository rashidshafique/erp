<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\DocumentPosted;
use App\Domains\Finance\Events\DocumentVoided;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\WithholdingCertificate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Customer certificates for tax they withheld: which taxes are waiting for one, recording one against invoices, voiding it. */
class WithholdingService
{
    public function __construct(private InvoiceService $invoices, private Receivables $receivables) {}

    /**
     * The withheld taxes on a customer's open invoices that are not yet covered by a certificate, plus "Other" for tax the
     * invoices did not work out.
     *
     * @return array<string, array{label: string, authority: ?string, open: float}> tax code => what is waiting
     */
    public function taxesFor(string $customerId): array
    {
        $taxes = [];
        foreach ($this->receivables->open($customerId) as $invoice) {
            foreach ($invoice->taxBreakdown()->where('withheld', true) as $t) {
                $taxes[$t['code']] ??= ['label' => $t['label'], 'authority' => $t['authority'], 'open' => 0.0];
                $taxes[$t['code']]['open'] = round($taxes[$t['code']]['open'] + $invoice->withheldOpen($t['code']), 2);
            }
        }

        return $taxes + [WithholdingCertificate::OTHER => ['label' => 'Other tax withheld', 'authority' => null, 'open' => 0.0]];
    }

    /**
     * Open invoices of the customer with how much of this tax each can still take from a certificate, oldest due first.
     *
     * @return Collection<int, array{invoice: Invoice, open: float}>
     */
    public function invoicesFor(string $customerId, string $taxCode): Collection
    {
        return $this->receivables->open($customerId)
            ->map(fn (Invoice $i) => ['invoice' => $i, 'open' => $this->room($i, $taxCode)])
            ->filter(fn ($row) => $row['open'] > 0)->values();
    }

    /**
     * @param  array<string, mixed>  $head  customer_id, tax_code, reference, certificate_date, notes
     * @param  array<string, float|int|string>  $allocations  invoice id => tax this certificate covers on it
     */
    public function record(array $head, array $allocations): WithholdingCertificate
    {
        $allocations = array_filter(array_map(fn ($a) => round((float) $a, 2), $allocations), fn ($a) => $a > 0);
        if ($allocations === []) {
            throw new InvalidArgumentException('Enter the tax the certificate covers on at least one invoice.');
        }
        $taxes = $this->taxesFor($head['customer_id']);
        $code = $head['tax_code'];
        if (! isset($taxes[$code])) {
            throw new InvalidArgumentException('None of this customer\'s open invoices has that tax withheld.');
        }

        return DB::transaction(function () use ($head, $allocations, $taxes, $code) {
            $certificate = WithholdingCertificate::create([
                'certificate_number' => $this->invoices->nextNumber('WHC', 'withholding_certificates', 'certificate_number'),
                'customer_id' => $head['customer_id'], 'tax_code' => $code, 'tax_label' => $taxes[$code]['label'], 'authority' => $taxes[$code]['authority'],
                'reference' => $head['reference'], 'certificate_date' => $head['certificate_date'], 'amount' => round(array_sum($allocations), 2), 'notes' => $head['notes'] ?? null,
            ]);
            foreach ($allocations as $invoiceId => $part) {
                $invoice = Invoice::whereKey($invoiceId)->lockForUpdate()->firstOrFail();
                if ($invoice->customer_id !== $head['customer_id'] || $invoice->status !== 'ISSUED' || $invoice->isCreditNote()) {
                    throw new InvalidArgumentException("Invoice {$invoice->invoice_number} cannot take a certificate from this customer.");
                }
                $room = $this->room($invoice, $code);
                if ($part > $room) {
                    throw new InvalidArgumentException("Invoice {$invoice->invoice_number} has only ".number_format($room, 2).' of this tax left to settle.');
                }
                $certificate->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $part]);
            }
            event(new DocumentPosted($certificate));

            return $certificate->fresh('allocations');
        });
    }

    /** Puts the withheld tax back on the invoices; the ledger reverses the certificate. */
    public function void(WithholdingCertificate $certificate, string $reason): WithholdingCertificate
    {
        if ($certificate->isVoid()) {
            throw new InvalidArgumentException('This certificate is already void.');
        }
        DB::transaction(function () use ($certificate, $reason) {
            $certificate->update(['status' => 'VOID', 'voided_at' => now(), 'void_reason' => $reason]);
            event(new DocumentVoided($certificate));
        });

        return $certificate->fresh();
    }

    /** How much of this tax a certificate can still settle on the invoice: the withheld amount not yet covered, or for "Other" anything still owed. */
    private function room(Invoice $invoice, string $taxCode): float
    {
        return $taxCode === WithholdingCertificate::OTHER ? $invoice->balance() : $invoice->withheldOpen($taxCode);
    }
}
