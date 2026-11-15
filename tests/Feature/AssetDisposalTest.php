<?php

namespace Tests\Feature;

use App\Domains\Assets\Models\AssetDisposal;
use App\Domains\Assets\Models\DepreciationRun;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Assets\Services\AssetPosting;
use App\Domains\Assets\Services\Depreciation;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\TaxRate;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/** Selling, scrapping and writing off an asset (design 28h): depreciation to the day, gain or loss, and the invoice to the buyer. */
class AssetDisposalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-12-02');
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
    }

    /** @return array<string, float> account code => debits less credits, for accounts that moved */
    private function balances(): array
    {
        return LedgerAccount::tree()->where('is_group', false)->filter(fn ($a) => $a->net != 0.0)->mapWithKeys(fn ($a) => [$a->code => round($a->net, 2)])->sortKeys()->all();
    }

    /** A furnace of 1,200,000 over 10 years (10,000 a month), brought in depreciated to 31 October. */
    private function furnace(array $extra = []): FixedAsset
    {
        return FixedAsset::factory()->create($extra + ['opening_depreciation' => 100000, 'opening_to' => '2026-10-31']);
    }

    public function test_selling_charges_depreciation_to_the_day_clears_the_asset_and_invoices_the_buyer_with_the_gain(): void
    {
        $asset = $this->furnace();
        $rate = TaxRate::create(['name' => 'FBR goods 18%', 'rate_percent' => 18, 'is_active' => true]);
        $this->get(route('assets.show', $asset))->assertOk()->assertSee('Sell or retire')->assertSee('Dispose of '.$asset->code);

        Livewire::test('assets::dispose', ['asset' => $asset])
            ->set('date', '2026-11-10')->set('price', '1200000')->set('taxRateId', $rate->id)
            ->assertSee('1,096,667')->assertSee('Gain on disposal')->assertSee('103,333')
            ->set('buyerName', 'Al-Noor Engineering Works')->set('buyerNtn', '2290147-1')
            ->call('save')->assertHasNoErrors()->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $disposal = AssetDisposal::sole();
        $this->assertSame(['SOLD', '2026-11-10'], [$asset->status, $asset->disposed_on->toDateString()]);
        $this->assertSame([3333.33, 103333.33, 1200000.0, 103333.33, 1096666.67], [$disposal->charge, $disposal->depreciation_cleared, $disposal->cost_removed, $disposal->gain, $disposal->book_value]);
        $buyer = Customer::sole();
        $invoice = Invoice::sole();
        $this->assertSame(['ISSUED', 'fixed_asset', $asset->id, $buyer->id, 1416000.0], [$invoice->status, $invoice->source_type, $invoice->source_id, $invoice->customer_id, $invoice->total]);
        $this->assertSame($disposal->invoice_id, $invoice->id);

        $this->assertSame([
            '12-03-01' => -216000.0, '21-01-03' => -1200000.0, '21-02-02' => 100000.0, '22-02-01' => 1416000.0, '42-01-05' => -103333.33, '51-06-04' => 3333.33,
        ], $this->balances());
        $this->assertSame(103333.33, $asset->fresh()->depreciatedTo());

        // the November run leaves it out: its charge to the sale date posted with the sale
        $this->assertTrue(app(Depreciation::class)->forMonth(Carbon::parse('2026-11-01'))->isEmpty());
        $this->get(route('assets.show', $asset))->assertOk()->assertSee($invoice->invoice_number)->assertSee('gain 103,333');

        // undoing the sale is not built, so its invoice is not voided; a credit note takes the price off the same account
        $this->expectException(InvalidArgumentException::class);
        app(InvoiceService::class)->void($invoice, 'Wrong buyer');
    }

    public function test_a_credit_note_on_an_asset_sale_takes_the_price_off_gain_on_disposal_not_sales_returns(): void
    {
        $asset = $this->furnace();
        Livewire::test('assets::dispose', ['asset' => $asset])->set('date', '2026-11-10')->set('price', '1200000')->set('buyerName', 'Al-Noor')->call('save')->assertHasNoErrors();

        app(InvoiceService::class)->creditNote(Invoice::sole(), [['description' => 'Price agreed down', 'quantity' => 1, 'unit_price' => 50000]], 'Price correction');

        $this->assertSame(-53333.33, $this->balances()['42-01-05']);
        $this->assertArrayNotHasKey(LedgerSettings::account('sales_returns')->code, $this->balances());
    }

    public function test_money_received_now_is_put_on_the_invoice_and_scrap_is_sold_by_weight(): void
    {
        $asset = $this->furnace();
        $buyer = Customer::create(['code' => 'SCR', 'name' => 'City Scrap Traders', 'active' => true]);

        Livewire::test('assets::dispose', ['asset' => $asset])
            ->set('kind', 'SCRAP')->set('date', '2026-11-30')->set('customerId', $buyer->id)->set('weight', '4200')->set('rate', '95')
            ->set('money', 'RECEIVED')->set('method', 'Cash')->set('reference', 'Weighbridge 118')
            ->call('save')->assertHasNoErrors();

        $disposal = AssetDisposal::sole();
        $this->assertSame(['SCRAPPED', 399000.0, 4200.0, 10000.0], [$asset->fresh()->status, $disposal->price, $disposal->weight_kg, $disposal->charge]);
        $this->assertSame(0.0, $disposal->invoice->balance());
        $this->assertSame(-691000.0, $disposal->gain);       // 399,000 less book value 1,100,000 − 10,000
        $this->assertSame(1, Customer::count());
    }

    public function test_writing_off_makes_the_book_value_a_loss_and_sets_the_equipment_disposed(): void
    {
        $equipment = Equipment::create(['code' => 'JS-3', 'name' => 'Jolt squeeze', 'equipment_type' => 'MOULDING_LINE', 'status' => 'IN_USE']);
        $asset = $this->furnace(['equipment_id' => $equipment->id]);

        Livewire::test('assets::dispose', ['asset' => $asset])->set('kind', 'WRITE_OFF')->set('date', '2026-11-15')->call('save')->assertHasErrors('reason')
            ->set('reason', 'Cracked frame, beyond repair')->call('save')->assertHasNoErrors();

        $this->assertSame(['WRITTEN_OFF', 'DISPOSED'], [$asset->fresh()->status, $equipment->fresh()->status]);
        $this->assertSame(0, Invoice::count());
        $this->assertSame(['21-01-03' => -1200000.0, '21-02-02' => 100000.0, '42-01-05' => 1095000.0, '51-06-04' => 5000.0], $this->balances());
    }

    public function test_an_asset_put_in_use_through_the_register_can_be_sold(): void
    {
        $asset = FixedAsset::factory()->inProgress()->create();
        app(AssetPosting::class)->putInUse($asset, '2026-11-01');

        Livewire::test('assets::dispose', ['asset' => $asset->fresh()])->set('date', '2026-11-20')->set('price', '1300000')->set('buyerName', 'Al-Noor')->call('save')->assertHasNoErrors();

        $this->assertSame(2, \App\Domains\Ledger\Models\Journal::whereIn('source_type', ['fixed_asset', 'fixed_asset_disposal'])->count());
        // in use 1 to 20 November: 20 of 30 days of 10,000, cleared again with the cost; 21-01-03 took the cost in and gave it back
        $this->assertSame(['21-04-01' => -1200000.0, '22-02-01' => 1300000.0, '42-01-05' => -106666.67, '51-06-04' => 6666.67], $this->balances());
    }

    public function test_a_disposal_waits_for_last_months_depreciation_and_a_posted_month_is_not_charged_twice(): void
    {
        $asset = FixedAsset::factory()->create();   // in use since January, no depreciation posted
        Livewire::test('assets::dispose', ['asset' => $asset])->set('date', '2026-11-10')->assertSee('Post the October 2026 depreciation run first')
            ->set('price', '500000')->set('buyerName', 'Al-Noor')->call('save')->assertHasErrors('dispose');
        $this->assertSame('IN_USE', $asset->fresh()->status);

        $asset = $this->furnace();
        $posting = app(AssetPosting::class);
        $november = $posting->post(Carbon::parse('2026-11-01'));
        $this->assertStringContainsString('November 2026 run already charged', app(\App\Domains\Assets\Services\Disposals::class)->block($asset, Carbon::parse('2026-11-20')));
        $this->assertStringContainsString('future', app(\App\Domains\Assets\Services\Disposals::class)->block($asset, Carbon::parse('2026-12-05')));

        // sold in December on November's book value: November can no longer be reversed
        Livewire::test('assets::dispose', ['asset' => $asset])->set('date', '2026-12-01')->set('price', '900000')->set('buyerName', 'Al-Noor')->call('save')->assertHasNoErrors();
        $this->assertSame(322.58, AssetDisposal::sole()->charge);   // 10,000 × 1 of 31 days
        $this->assertStringContainsString('disposed of on', $posting->reverseBlock($november)['message']);
        $this->post(route('assets.runs.reverse', $november), ['reason' => 'Wrong'])->assertSessionHasErrors('run');
        $this->assertSame(DepreciationRun::POSTED, $november->fresh()->status);
    }
}
