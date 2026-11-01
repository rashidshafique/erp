<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\TaxRate;
use App\Domains\Finance\Services\ChargeBoxes;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Design 19n and 19o: charge and deduction boxes after tax on sales invoices and credit notes, their settings and their posting. */
class InvoiceChargesTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        $this->customer = Customer::create(['code' => 'KOV', 'name' => 'Kovar Pumps']);
    }

    private function id(string $code): string
    {
        return LedgerAccount::where('code', $code)->value('id');
    }

    /** @return array<string, float> */
    private function balances(): array
    {
        return LedgerAccount::tree()->where('is_group', false)->filter(fn ($a) => $a->net != 0.0)->mapWithKeys(fn ($a) => [$a->code => $a->net])->sortKeys()->all();
    }

    public function test_charges_and_a_deduction_change_the_net_and_post_to_their_accounts(): void
    {
        $rate = TaxRate::create(['name' => 'Sales tax', 'rate_percent' => 18]);
        $this->post(route('invoices.store'), ['customer_id' => $this->customer->id, 'invoice_date' => '2026-10-31',
            'lines' => [['description' => 'Pump casing 80×65-200', 'quantity' => 320, 'unit_price' => 5250, 'tax_rate_id' => $rate->id]],
            'adjustments' => [
                ['kind' => 'charge', 'account_id' => $this->id('42-01-03'), 'amount' => 18000],
                ['kind' => 'charge', 'account_id' => $this->id('42-01-04'), 'amount' => 6500],
                ['kind' => 'deduction', 'account_id' => $this->id('41-03-02'), 'amount' => 25000],
                ['kind' => 'charge', 'account_id' => $this->id('51-01-01'), 'amount' => 99],   // not a Deduction and charge account: dropped
            ]])->assertSessionHasNoErrors();
        $invoice = Invoice::sole();

        $this->assertSame([1680000.0, 302400.0, 1982400.0, 1981900.0], [$invoice->subtotal, $invoice->tax_total, $invoice->grossTotal(), $invoice->total]);
        $this->assertCount(3, $invoice->adjustmentLines());

        app(InvoiceService::class)->issue($invoice);

        $this->assertSame(['12-03-01' => -302400.0, '22-02-01' => 1981900.0, '41-01-01' => -1680000.0, '41-03-02' => 25000.0, '42-01-03' => -18000.0, '42-01-04' => -6500.0], $this->balances());
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSeeInOrder(['Freight recharged', 'Trade discount allowed', 'Net']);
        $this->get(route('invoices.print', $invoice))->assertOk()->assertSee('Packing and loading recharged')->assertSee('Less Trade discount allowed');
    }

    public function test_a_credit_note_charge_goes_the_other_way(): void
    {
        $invoice = app(InvoiceService::class)->issue(app(InvoiceService::class)->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => '2026-10-01',
            'adjustments' => [['kind' => 'charge', 'account_id' => $this->id('42-01-03'), 'amount' => 100]]],
            [['description' => 'Pump casing', 'quantity' => 1, 'unit_price' => 1000]]));
        app(InvoiceService::class)->creditNote($invoice->fresh(), [['description' => 'Pump casing', 'quantity' => 1, 'unit_price' => 1000]], 'Returned', [['kind' => 'charge', 'account_id' => $this->id('42-01-03'), 'amount' => 100]]);

        $this->assertSame(['41-01-01' => -1000.0, '41-03-01' => 1000.0], $this->balances());
    }

    public function test_the_settings_decide_how_many_boxes_show_and_on_which_documents(): void
    {
        $this->get(route('invoices.create'))->assertOk()->assertSee('Posting preview')->assertSee('42-01-03  Freight recharged');
        $this->get(route('ledger.settings.sales-documents'))->assertOk()->assertSee('Accounts available · 3')->assertSee('Trade discount allowed');

        $this->put(route('ledger.settings.sales-documents.update'), ['charges' => 1, 'deductions' => 0, 'documents' => ['credit_note']])->assertSessionHasNoErrors();

        $this->assertSame([1, 0, ['credit_note']], [ChargeBoxes::charges(), ChargeBoxes::deductions(), ChargeBoxes::documents()]);
        $this->get(route('invoices.create'))->assertOk()->assertDontSee('Posting preview');
    }
}
