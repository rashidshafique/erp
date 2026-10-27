<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\WithholdingCertificate;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Finance\Services\ReceiptService;
use App\Domains\Finance\Services\Receivables;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Tax\Models\PartyTaxProfile;
use App\Domains\Tax\Services\TaxSetup;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\TaxJurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithholdingCertificateTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        app(TaxSetup::class)->loadDefaults(TaxJurisdiction::pack());
        $this->customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        PartyTaxProfile::updateOrCreate(['party_id' => $this->customer->party_id], ['tax_status' => 'REGISTERED', 'region' => 'PB', 'is_withholding_agent' => true,
            'registrations' => ['NTN' => '1234567-8', 'STRN' => '32-77-8761-123-45']]);
    }

    /** An issued invoice of 1,180 to a withholding agent: 36 sales tax and 59 income tax withheld, 1,085 paid in money. */
    private function paidInvoice(): Invoice
    {
        $service = app(InvoiceService::class);
        $invoice = $service->issue($service->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => '2026-10-01'],
            [['description' => 'Pump casing', 'quantity' => 1, 'uom' => 'pcs', 'unit_price' => 1000, 'supply_type' => 'GOODS', 'tariff_code' => '7325.1000']]));
        app(ReceiptService::class)->record(['customer_id' => $this->customer->id, 'received_on' => '2026-10-10', 'amount' => 1085], [$invoice->id => 1085]);

        return $invoice->fresh();
    }

    private function certify(Invoice $invoice, string $taxCode, float $amount): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('withholding-certificates.store'), ['customer_id' => $this->customer->id, 'tax_code' => $taxCode,
            'reference' => 'CPR-0042', 'certificate_date' => '2026-10-20', 'alloc' => [$invoice->id => $amount]]);
    }

    private function net(string $code): float
    {
        return LedgerAccount::tree()->firstWhere('code', $code)->net;
    }

    public function test_the_withheld_part_stays_owing_until_certificates_settle_it_into_tax_withheld_by_customers(): void
    {
        $invoice = $this->paidInvoice();
        $this->assertSame([95.0, 95.0, 0.0], [$invoice->balance(), $invoice->withheldOpen(), $invoice->cashDue()]);
        $this->assertSame(95.0, app(Receivables::class)->ageing()[0]['withheld']);

        $this->certify($invoice, 'WHT_IT_GOODS', 59)->assertSessionHasNoErrors();

        $certificate = WithholdingCertificate::sole();
        $this->assertSame(['WHC-'.now()->format('Y').'-0001', 'Income tax withheld on supply of goods', 59.0], [$certificate->certificate_number, $certificate->tax_label, $certificate->amount]);
        $this->assertSame([36.0, 36.0, 0.0], [$invoice->balance(), $invoice->withheldOpen('WHT_ST'), $invoice->withheldOpen('WHT_IT_GOODS')]);
        $this->assertSame([59.0, 36.0], [$this->net('1163'), $this->net('1130')]);
        $this->assertSame('2026-10-20', Journal::where('source_type', 'withholding_certificate')->sole()->journal_date->toDateString());
        $this->assertSame(36.0, app(Receivables::class)->statement($this->customer)['balance']);

        $this->certify($invoice, 'WHT_ST', 36)->assertSessionHasNoErrors();
        $this->assertSame(0.0, $invoice->balance());
        $this->assertSame([], app(Receivables::class)->ageing());
    }

    public function test_a_certificate_cannot_settle_more_of_a_tax_than_was_withheld(): void
    {
        $invoice = $this->paidInvoice();

        $this->certify($invoice, 'WHT_IT_GOODS', 60)->assertSessionHasErrors('certificate');

        $this->assertSame(0, WithholdingCertificate::count());
        $this->assertSame(0, Journal::where('source_type', 'withholding_certificate')->count());
    }

    public function test_voiding_a_certificate_puts_the_tax_back_on_the_invoice_and_reverses_the_journal(): void
    {
        $invoice = $this->paidInvoice();
        $this->certify($invoice, 'WHT_IT_GOODS', 59);
        $certificate = WithholdingCertificate::sole();

        $this->post(route('invoices.void', $invoice), ['reason' => 'Raised twice'])->assertSessionHasErrors('invoice');
        $this->post(route('withholding-certificates.void', $certificate), ['reason' => 'Wrong customer'])->assertSessionHasNoErrors();

        $this->assertSame([95.0, 0.0, 95.0], [$invoice->balance(), $this->net('1163'), $this->net('1130')]);
        $this->assertSame(1, Journal::where('reverses_id', Journal::where('source_type', 'withholding_certificate')->where('kind', 'POST')->value('id'))->count());
    }

    public function test_other_tax_withheld_can_settle_an_invoice_the_tax_rules_did_not_withhold_on(): void
    {
        PartyTaxProfile::where('party_id', $this->customer->party_id)->update(['is_withholding_agent' => false]);
        $service = app(InvoiceService::class);
        $invoice = $service->issue($service->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => '2026-10-01'],
            [['description' => 'Pump casing', 'quantity' => 1, 'uom' => 'pcs', 'unit_price' => 1000, 'supply_type' => 'GOODS', 'tariff_code' => '7325.1000']]));

        $this->get(route('withholding-certificates.create', ['customer' => $this->customer->id]))->assertOk()->assertSee('Other tax withheld');
        $this->certify($invoice, WithholdingCertificate::OTHER, 1181)->assertSessionHasErrors('certificate');
        $this->certify($invoice, WithholdingCertificate::OTHER, 50)->assertSessionHasNoErrors();

        $this->assertSame(1130.0, $invoice->fresh()->balance());
    }

    public function test_the_pages_show_the_certificate_and_what_awaits_one(): void
    {
        $invoice = $this->paidInvoice();
        $this->get(route('receivables.index'))->assertOk()->assertSee('Awaiting tax certificate');
        $this->get(route('withholding-certificates.create', ['customer' => $this->customer->id, 'tax' => 'WHT_ST']))->assertOk()->assertSee($invoice->invoice_number);

        $this->certify($invoice, 'WHT_ST', 36);
        $certificate = WithholdingCertificate::sole();

        $this->get(route('withholding-certificates.show', $certificate))->assertOk()->assertSee('CPR-0042');
        $this->get(route('withholding-certificates.index'))->assertOk()->assertSee($certificate->certificate_number);
        $this->get(route('receivables.statement', $this->customer))->assertOk()->assertSee('Tax certificate');
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Withheld tax certified');
    }
}
