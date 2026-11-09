<?php

namespace Tests\Feature;

use App\Domains\Finance\Events\BillPosted;
use App\Domains\Finance\Events\BillVoided;
use App\Domains\Finance\Events\DocumentEmailed;
use App\Domains\Finance\Events\InvoiceIssued;
use App\Domains\Finance\Events\InvoiceVoided;
use App\Domains\Finance\Events\ReceiptRecorded;
use App\Domains\Finance\Events\SupplierPaymentMade;
use App\Domains\Finance\Mail\InvoiceMail;
use App\Domains\Finance\Mail\StatementMail;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Services\ReceiptService;
use App\Domains\Finance\Services\SupplierPaymentService;
use App\Domains\Finance\Models\TaxRate;
use App\Models\Customer;
use App\Domains\Purchasing\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The accounting side's email placeholder (Send buttons on the log mailer) and the after-commit events a mailer, webhook or other plug-in can listen to. */
class FinanceEmailAndEventsTest extends TestCase
{
    use RefreshDatabase;

    private function draft(?string $email = 'ap@acme.test'): Invoice
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'active' => true, 'payment_terms' => 'Net 30', 'email' => $email]);
        $tax = TaxRate::create(['name' => 'Sales tax', 'rate_percent' => 10, 'is_default' => true]);
        $this->post(route('invoices.store'), ['customer_id' => $customer->id, 'invoice_date' => today()->toDateString(),
            'lines' => [['description' => 'Pump body', 'quantity' => 2, 'unit_price' => 100, 'tax_rate_id' => $tax->id]]])->assertSessionHasNoErrors();

        return Invoice::firstOrFail();
    }

    private function issued(?string $email = 'ap@acme.test'): Invoice
    {
        $invoice = $this->draft($email);
        $this->post(route('invoices.issue', $invoice))->assertSessionHasNoErrors();

        return $invoice->fresh();
    }

    public function test_an_issued_invoice_is_emailed_on_the_button_and_the_message_says_email_is_not_set_up(): void
    {
        Mail::fake();
        Event::fake([DocumentEmailed::class]);
        $invoice = $this->issued();

        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee(route('invoices.email', $invoice), false);
        $this->post(route('invoices.email', $invoice))->assertSessionHas('warning', fn ($m) => str_contains($m, 'Email is not set up yet') && str_contains($m, 'was not sent'));

        Mail::assertSent(InvoiceMail::class, fn ($mail) => $mail->hasTo('ap@acme.test') && $mail->invoice->is($invoice));
        Event::assertDispatched(DocumentEmailed::class, fn ($e) => $e->to === 'ap@acme.test' && $e->sent === false);
    }

    public function test_a_real_mailer_reports_the_message_as_sent(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $invoice = $this->issued();

        $this->post(route('invoices.email', $invoice))->assertSessionHas('success', fn ($m) => str_contains($m, 'sent to ap@acme.test'));
    }

    public function test_a_draft_or_a_customer_without_an_email_cannot_be_emailed(): void
    {
        Mail::fake();
        $draft = $this->draft();

        $this->get(route('invoices.show', $draft))->assertOk()->assertDontSee(route('invoices.email', $draft), false);
        $this->post(route('invoices.email', $draft))->assertSessionHasErrors('invoice');

        $this->post(route('invoices.issue', $draft))->assertSessionHasNoErrors();
        $draft->customer->update(['email' => null]);
        $this->get(route('invoices.show', $draft))->assertOk()->assertSee('Email: no address')->assertDontSee(route('invoices.email', $draft), false);
        $this->post(route('invoices.email', $draft))->assertSessionHasErrors('invoice');

        Mail::assertNothingSent();
    }

    public function test_a_statement_is_emailed_from_the_customer_page(): void
    {
        Mail::fake();
        $invoice = $this->issued();

        $this->get(route('receivables.statement', $invoice->customer))->assertOk()->assertSee('Email statement');
        $this->post(route('receivables.email', $invoice->customer))->assertSessionHas('warning');

        Mail::assertSent(StatementMail::class, fn ($mail) => $mail->hasTo('ap@acme.test') && $mail->customer->is($invoice->customer));
    }

    public function test_document_events_fire_after_commit_for_each_posting_and_void(): void
    {
        $seen = [];
        foreach ([InvoiceIssued::class, InvoiceVoided::class, BillPosted::class, BillVoided::class, ReceiptRecorded::class, SupplierPaymentMade::class] as $class) {
            Event::listen($class, function ($e) use (&$seen, $class) {
                $seen[class_basename($class)][] = $e->document->getKey();
            });
        }
        $invoice = $this->issued();
        $this->assertSame([$invoice->id], $seen['InvoiceIssued']);

        $receipt = app(ReceiptService::class)->record(['customer_id' => $invoice->customer_id, 'received_on' => today()->toDateString(), 'amount' => 50, 'method' => 'BANK'], [$invoice->id => 50]);
        $this->assertSame([$receipt->id], $seen['ReceiptRecorded']);

        $supplier = Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        $this->post(route('bills.store'), ['supplier_id' => $supplier->id, 'bill_date' => today()->toDateString(), 'supplier_bill_number' => 'S-1',
            'lines' => [['description' => 'Pig iron', 'quantity' => 1, 'unit_price' => 1000, 'tax_percent' => 0]]])->assertSessionHasNoErrors();
        $bill = SupplierBill::sole();
        $this->post(route('bills.post', $bill))->assertSessionHasNoErrors();
        $this->assertSame([$bill->id], $seen['BillPosted']);

        $payment = app(SupplierPaymentService::class)->record(['supplier_id' => $supplier->id, 'paid_on' => today()->toDateString(), 'amount' => 100, 'method' => 'BANK'], []);
        $this->assertSame([$payment->id], $seen['SupplierPaymentMade']);

        $this->post(route('bills.void', $bill), ['reason' => 'Wrong bill'])->assertSessionHasNoErrors();
        $this->assertSame([$bill->id], $seen['BillVoided']);
    }

    public function test_an_invoice_void_raises_its_event(): void
    {
        $invoice = $this->issued();
        $seen = [];
        Event::listen(InvoiceVoided::class, function ($e) use (&$seen) {
            $seen[] = $e->document->id;
        });

        $this->post(route('invoices.void', $invoice), ['reason' => 'Wrong customer'])->assertSessionHasNoErrors();

        $this->assertSame([$invoice->id], $seen);
    }

    public function test_a_listener_that_fails_cannot_undo_the_issued_invoice(): void
    {
        $invoice = $this->draft();
        $ran = false;
        Event::listen(InvoiceIssued::class, function () use (&$ran) {
            $ran = true;

            throw new \RuntimeException('mail server down');
        });

        try {
            $this->withoutExceptionHandling()->post(route('invoices.issue', $invoice));
        } catch (\RuntimeException) {
            // the plug-in failed after the commit; the invoice must stay issued
        }

        $this->assertSame('ISSUED', $invoice->fresh()->status);   // and the listener really ran, so this is not passing for want of an event
        $this->assertTrue($ran);
        $this->assertNotNull($invoice->fresh()->invoice_number);
    }
}
