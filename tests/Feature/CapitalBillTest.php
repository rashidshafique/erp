<?php

namespace Tests\Feature;

use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Purchasing\Models\Supplier;
use App\Domains\Tooling\Models\ToolingLifeLimit;
use App\Models\Customer;
use App\Models\Pattern;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Supplier bills for capital work (designs 28a, 28b, 28g) and capitalising a development we paid for (28e). */
class CapitalBillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
    }

    /** @param list<array<string, mixed>> $lines */
    private function postBill(array $lines): SupplierBill
    {
        $supplier = Supplier::create(['name' => 'Indus Foundry Machines', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        $this->post(route('bills.store'), ['supplier_id' => $supplier->id, 'bill_date' => '2026-11-04', 'supplier_bill_number' => 'IFM-'.uniqid(), 'lines' => $lines])->assertSessionHasNoErrors();
        $bill = SupplierBill::latest('created_at')->firstOrFail();
        $this->post(route('bills.post', $bill))->assertSessionHasNoErrors();

        return $bill->fresh('lines');
    }

    /** @return array<string, float> account code => debit of the bill's journal */
    private function debits(SupplierBill $bill): array
    {
        $journal = Journal::where('source_type', 'supplier_bill')->where('source_id', $bill->id)->sole();

        return $journal->lines()->with('account')->get()->filter(fn ($l) => $l->debit > 0)->mapWithKeys(fn ($l) => [$l->account->code => (float) $l->debit])->all();
    }

    private function development(string $whoPays): PatternDevelopment
    {
        $customer = Customer::create(['code' => 'MER', 'name' => 'Meridian Auto Systems', 'active' => true]);
        $this->post(route('developments.store'), ['type' => 'NEW_PRODUCT', 'title' => 'Pump body', 'customer_id' => $customer->id, 'pattern_by' => 'SUBCONTRACT', 'pattern_maker' => 'Erie Pattern Works']);
        $d = PatternDevelopment::sole();
        $d->update(['who_pays' => $whoPays]);
        $d->tools()->update(['who_pays' => $whoPays]);

        return $d;
    }

    public function test_an_asset_line_goes_to_capital_work_in_progress_and_adds_to_the_assets_cost(): void
    {
        $mixer = FixedAsset::factory()->inProgress()->create(['name' => 'Continuous sand mixer', 'cost' => 0]);

        $bill = $this->postBill([
            ['description' => 'Installation and commissioning', 'quantity' => 1, 'unit_price' => 180000, 'tax_percent' => 0, 'treat' => 'asset:'.$mixer->id],
            ['description' => 'Spare paddle set', 'quantity' => 1, 'unit_price' => 85000, 'tax_percent' => 0],
        ]);

        $this->assertSame(['21-04-01' => 180000.0, '61-02-01' => 85000.0], collect($this->debits($bill))->sortKeys()->all());
        $this->assertSame(180000.0, $mixer->fresh()->cost);
        $this->assertSame('BILL_LINE', $mixer->costs()->sole()->source_type);

        $this->post(route('bills.void', $bill), ['reason' => 'Entered twice'])->assertSessionHasNoErrors();
        $this->assertSame(0.0, $mixer->fresh()->cost);
    }

    public function test_a_bill_cannot_add_to_an_asset_already_in_use(): void
    {
        $furnace = FixedAsset::factory()->create();
        $supplier = Supplier::create(['name' => 'Indus Foundry Machines', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        $this->post(route('bills.store'), ['supplier_id' => $supplier->id, 'bill_date' => '2026-11-04', 'supplier_bill_number' => 'IFM-1',
            'lines' => [['description' => 'Coil', 'quantity' => 1, 'unit_price' => 1000, 'tax_percent' => 0, 'treat' => 'asset:'.$furnace->id]]]);

        $this->post(route('bills.post', SupplierBill::sole()))->assertSessionHasErrors();
        $this->assertSame('DRAFT', SupplierBill::sole()->status);
    }

    public function test_a_development_bill_waits_in_work_in_progress_when_the_customer_pays_for_the_tool(): void
    {
        $bill = $this->postBill([['description' => 'Pattern build, stage 3 of 3', 'quantity' => 1, 'unit_price' => 516000, 'tax_percent' => 0, 'treat' => 'dev:'.$this->development('CUSTOMER')->id]]);

        $this->assertSame(['22-08-04' => 516000.0], $this->debits($bill));
    }

    public function test_approving_a_development_we_paid_for_turns_its_bills_into_an_asset_in_use(): void
    {
        $d = $this->development('FOUNDRY');
        $bill = $this->postBill([['description' => 'Pump body pattern build', 'quantity' => 1, 'unit_price' => 248000, 'tax_percent' => 0, 'treat' => 'dev:'.$d->id]]);
        $this->assertSame(['21-04-01' => 248000.0], $this->debits($bill));

        $pattern = Pattern::create(['code' => 'PT-0430', 'name' => 'Pump body pattern', 'pattern_type' => 'LOOSE', 'owner_type' => 'FOUNDRY']);
        $d->tools()->update(['pattern_id' => $pattern->id, 'status' => 'TRIAL_PASSED']);
        ToolingLifeLimit::create(['tooling_type' => 'PATTERN', 'pattern_id' => $pattern->id, 'usage_unit' => 'MOULDS', 'service_life_quantity' => 5000,
            'warning_threshold_percent' => 80, 'usage_at_last_inspection' => 0, 'is_active' => true]);
        while ($d->fresh()->isOpen()) {
            $this->post(route('developments.advance', $d), ['override' => 1])->assertSessionHasNoErrors();
        }

        $asset = FixedAsset::where('pattern_development_id', $d->id)->sole();
        $this->assertSame(['IN_USE', 'PATTERN', 'USAGE', 5000, 248000.0, $pattern->id], [$asset->status, $asset->kind, $asset->method, $asset->life_fills, $asset->cost, $asset->pattern_id]);
        $this->assertSame(0.0, LedgerAccount::where('code', '21-04-01')->first()->balance());
        $this->assertSame(248000.0, LedgerAccount::where('code', '21-03-01')->first()->balance());
    }
}
