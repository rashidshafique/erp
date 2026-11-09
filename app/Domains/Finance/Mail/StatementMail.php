<?php

namespace App\Domains\Finance\Mail;

use App\Models\Customer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A customer's statement of account: the open invoices and what is owed.
 *
 * @phpstan-type Open array<int, \App\Domains\Finance\Models\Invoice>
 */
class StatementMail extends Mailable
{
    /** @param  iterable<int, \App\Domains\Finance\Models\Invoice>  $open */
    public function __construct(public Customer $customer, public iterable $open, public float $balance) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Statement of account from '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'finance::mail.statement', with: ['customer' => $this->customer, 'open' => $this->open, 'balance' => $this->balance]);
    }
}
