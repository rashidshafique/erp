<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Services\TaxRegister;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Purchasing\Models\Supplier;
use App\Domains\Tax\Models\PartyTaxProfile;
use App\Domains\Tax\Models\TaxComponent;
use App\Domains\Tax\Models\TaxRule;
use App\Domains\Tax\Services\TaxSetup;
use App\Models\Role;
use App\Models\User;
use App\Support\TaxJurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierWithholdingTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        app(TaxSetup::class)->loadDefaults(TaxJurisdiction::pack());
        $this->supplier = Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        PartyTaxProfile::updateOrCreate(['party_id' => $this->supplier->party_id], ['tax_status' => 'REGISTERED', 'region' => 'SD']);
    }

    /** @param list<array<string, mixed>> $lines */
    private function postedBill(array $lines): SupplierBill
    {
        $before = SupplierBill::pluck('id');
        $this->post(route('bills.store'), ['supplier_id' => $this->supplier->id, 'bill_date' => '2025-03-05', 'supplier_bill_number' => 'S-'.uniqid(),
            'lines' => array_map(fn ($l) => $l + ['quantity' => 1], $lines)])->assertSessionHasNoErrors();
        $bill = SupplierBill::whereNotIn('id', $before)->firstOrFail();
        $this->post(route('bills.post', $bill))->assertSessionHasNoErrors();

        return $bill->fresh();
    }

    private function net(string $code): float
    {
        return LedgerAccount::tree()->firstWhere('code', $code)->net;
    }

    public function test_as_a_withholding_agent_we_keep_back_tax_so_the_supplier_is_owed_less_and_the_authority_the_rest(): void
    {
        TaxSetup::saveCompany('PB', ['NTN' => '7654321-0'], true);

        // 180 sales tax charged; we keep back a fifth of it (36) and 5% income tax on the 1,180 with tax (59)
        $bill = $this->postedBill([['description' => 'Pig iron', 'unit_price' => 1000, 'tax_percent' => 18, 'supply_type' => 'GOODS']]);

        $this->assertSame([1180.0, 95.0, 1085.0, 1085.0], [$bill->total, $bill->tax_withheld, $bill->payableToSupplier(), $bill->balance()]);
        $this->assertSame([-1085.0, -95.0, 180.0], [$this->net('2110'), $this->net('2150'), $this->net('1161')]);
        $summary = app(TaxRegister::class)->summary(app(TaxRegister::class)->rows('purchases', '2025-03-01', '2025-03-31'));
        $this->assertSame([['ST_GOODS', false, 180.0], ['WHT_IT_GOODS', true, 59.0], ['WHT_ST', true, 36.0]],
            $summary->map(fn ($s) => [$s['code'], $s['withheld'], $s['amount']])->all());
        $this->get(route('bills.show', $bill))->assertOk()->assertSee('Supplier is paid');

        $this->post(route('supplier-payments.store'), ['supplier_id' => $this->supplier->id, 'paid_on' => '2025-03-10', 'amount' => 1086, 'alloc' => [$bill->id => 1086]])
            ->assertSessionHasErrors('payment');
    }

    public function test_services_bought_have_income_tax_withheld_on_the_value_with_tax(): void
    {
        TaxSetup::saveCompany('PB', [], true);

        $bill = $this->postedBill([['description' => 'Machining', 'unit_price' => 500, 'tax_percent' => 15, 'supply_type' => 'SERVICE']]);

        $this->assertSame([575.0, 51.75], [$bill->total, $bill->tax_withheld]);
    }

    public function test_without_the_company_being_a_withholding_agent_nothing_is_kept_back(): void
    {
        $bill = $this->postedBill([['description' => 'Pig iron', 'unit_price' => 1000, 'tax_percent' => 18, 'supply_type' => 'GOODS']]);

        $this->assertSame([0.0, 1180.0], [$bill->tax_withheld, $bill->balance()]);
        $this->assertSame(0.0, $this->net('2150'));
    }

    public function test_a_country_loaded_earlier_gets_the_new_taxes_and_rules_without_touching_edited_ones(): void
    {
        TaxComponent::where('code', 'WHT_IT_SERVICES')->delete();
        TaxRule::where('name', 'Income tax we withhold on services bought')->delete();
        TaxComponent::where('code', 'ST_GOODS')->update(['rate' => 17]);

        $this->assertSame(2, app(TaxSetup::class)->addMissing(TaxJurisdiction::pack()));
        $this->assertSame(0, app(TaxSetup::class)->addMissing(TaxJurisdiction::pack()));
        $this->assertEquals(17, TaxComponent::where('code', 'ST_GOODS')->value('rate'));
    }
}
