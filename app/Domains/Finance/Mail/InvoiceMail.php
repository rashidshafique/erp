<?php

namespace App\Domains\Finance\Mail;

use App\Domains\Finance\Models\Invoice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** An issued invoice or credit note sent to the customer. The message is a short note with the figures; the printed page is not attached. */
class InvoiceMail extends Mailable
{
    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        $what = $this->invoice->isCreditNote() ? 'Credit note' : 'Invoice';

        return new Envelope(subject: "{$what} {$this->invoice->invoice_number} from ".config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'finance::mail.invoice', with: ['invoice' => $this->invoice, 'credit' => $this->invoice->isCreditNote()]);
    }
}
