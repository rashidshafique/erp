<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\DocumentEmailed;
use App\Domains\Finance\Mail\InvoiceMail;
use App\Domains\Finance\Mail\StatementMail;
use App\Domains\Finance\Models\Invoice;
use App\Models\Customer;
use InvalidArgumentException;
use Illuminate\Support\Facades\Mail;

/**
 * Sends invoices, credit notes and statements by email when someone presses Send. Email is a placeholder until a real mailer is set up
 * (MAIL_MAILER and its settings in .env): with the log or array mailer the message only goes to the log. Each send raises DocumentEmailed.
 */
class DocumentEmailer
{
    /** Whether a real mailer is set up; false means messages only go to the log. */
    public function configured(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    /** What the person sees after a send, never claiming more than happened. */
    private function outcome(string $what, string $to): string
    {
        return $this->configured()
            ? "{$what} sent to {$to}."
            : "Email is not set up yet, so {$what} was not sent. A copy of the message to {$to} was kept in the log.";
    }

    public function invoice(Invoice $invoice): string
    {
        if ($invoice->status !== 'ISSUED') {
            throw new InvalidArgumentException('Only an issued document can be emailed.');
        }
        $to = $this->address($invoice->customer);
        Mail::to($to)->send(new InvoiceMail($invoice));
        event(new DocumentEmailed($invoice, $to, $this->configured()));

        return $this->outcome($invoice->invoice_number, $to);
    }

    public function statement(Customer $customer, Receivables $receivables): string
    {
        $to = $this->address($customer);
        $statement = $receivables->statement($customer);
        Mail::to($to)->send(new StatementMail($customer, $receivables->open($customer->id), (float) $statement['balance']));
        event(new DocumentEmailed($customer, $to, $this->configured()));

        return $this->outcome('the statement', $to);
    }

    private function address(?Customer $customer): string
    {
        if (! $customer?->email) {
            throw new InvalidArgumentException('This customer has no email address. Add one on the customer first.');
        }

        return $customer->email;
    }
}
