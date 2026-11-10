<?php

namespace Tests\Feature;

use App\Domains\Assets\Models\AssetClass;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Models\Customer;
use App\Models\Pattern;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The fixed-asset register (design 28c, 28d): adding assets the foundry already has, cost rows, and putting an asset in use. */
class FixedAssetRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
    }

    /** @return array<string, float> account code => debits less credits, for accounts that moved */
    private function balances(): array
    {
        return LedgerAccount::tree()->where('is_group', false)->filter(fn ($a) => $a->net != 0.0)->mapWithKeys(fn ($a) => [$a->code => $a->net])->sortKeys()->all();
    }

    public function test_the_starting_classes_follow_the_chart_and_land_is_not_depreciated(): void
    {
        $classes = AssetClass::with(['costAccount', 'depreciationAccount', 'expenseAccount'])->get()->keyBy('name');

        $this->assertCount(8, $classes);
        $this->assertSame(['NONE', null], [$classes['Freehold land']->method, $classes['Freehold land']->depreciationAccount]);
        $this->assertSame(['21-01-07', '21-02-05', '61-02-04'], [$classes['Vehicles and forklifts']->costAccount->code, $classes['Vehicles and forklifts']->depreciationAccount->code, $classes['Vehicles and forklifts']->expenseAccount->code]);
        $this->assertSame(['21-03-01', '21-03-02', '51-06-03', 'USAGE'], [$classes['Patterns and core boxes (own)']->costAccount->code, $classes['Patterns and core boxes (own)']->depreciationAccount->code,
            $classes['Patterns and core boxes (own)']->expenseAccount->code, $classes['Patterns and core boxes (own)']->method]);
    }

    public function test_an_asset_the_foundry_already_has_is_added_with_its_cost_and_depreciation_to_date_and_posts_nothing(): void
    {
        $pattern = Pattern::create(['code' => 'PT-0302', 'name' => 'Manifold pattern', 'pattern_type' => 'LOOSE', 'owner_type' => 'FOUNDRY', 'amortise_qty' => 20000]);

        $this->post(route('assets.store'), [
            'asset_class_id' => AssetClass::where('name', 'Patterns and core boxes (own)')->value('id'), 'name' => 'Manifold pattern, 2 cav.', 'kind' => 'PATTERN',
            'status' => 'IN_USE', 'in_use_on' => '2024-01-09', 'location' => 'Pattern store', 'cost' => 2480000, 'method' => 'USAGE', 'life_fills' => 20000,
            'opening_depreciation' => 1488000, 'opening_fills' => 12000, 'pattern_id' => $pattern->id,
        ])->assertSessionHasNoErrors();

        $asset = FixedAsset::sole();
        $this->assertMatchesRegularExpression('/^FA-\d+$/', $asset->code);
        $this->assertSame([2480000.0, 992000.0, 12000], [$asset->cost, $asset->bookValue(), $asset->fillsUsed()]);
        $this->assertSame([['OPENING', 2480000.0]], $asset->costs->map(fn ($c) => [$c->source_type, $c->amount])->all());
        $this->assertSame(0, Journal::count());
        $this->get(route('assets.show', $asset))->assertOk()->assertSee('Manifold pattern, 2 cav.')->assertSee('992,000')->assertSee('12,000 of 20,000 fills');

        $this->post(route('assets.store'), ['asset_class_id' => AssetClass::where('name', 'Factory buildings')->value('id'), 'name' => 'Shed', 'kind' => 'BUILDING',
            'status' => 'IN_USE', 'in_use_on' => '2020-01-01', 'cost' => 100, 'method' => 'STRAIGHT_LINE', 'life_years' => 30, 'opening_depreciation' => 150])
            ->assertSessionHasErrors('opening_depreciation');
    }

    public function test_putting_an_asset_in_use_moves_its_cost_out_of_capital_work_in_progress_and_starts_depreciation(): void
    {
        $asset = FixedAsset::factory()->inProgress()->create(['cost' => 860000]);
        $asset->costs()->create(['source_type' => 'SHOP_HOURS', 'description' => 'Fitting and trial', 'amount' => 40000]);
        $asset->refreshCost();
        $this->assertSame(900000.0, $asset->fresh()->cost);

        $this->get(route('assets.show', $asset))->assertOk()->assertSee('Put in use');
        $this->get(route('assets.edit', $asset))->assertOk();
        $this->put(route('assets.update', $asset), ['asset_class_id' => $asset->asset_class_id, 'name' => 'Renamed', 'kind' => 'PLANT', 'status' => 'IN_USE',
            'method' => 'STRAIGHT_LINE', 'life_years' => 10])->assertSessionHasNoErrors();
        $this->assertSame(['Renamed', 'IN_PROGRESS', 900000.0], [$asset->fresh()->name, $asset->fresh()->status, $asset->fresh()->cost]);

        $this->post(route('assets.in-use', $asset), ['in_use_on' => '2026-11-02'])->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame(['IN_USE', '2026-11-02'], [$asset->status, $asset->in_use_on->toDateString()]);
        $this->assertSame(['21-01-03' => 900000.0, '21-04-01' => -900000.0], $this->balances());
        $this->assertSame('fixed_asset', $asset->events()->where('kind', 'IN_USE')->sole()->journal->source_type);
        $this->post(route('assets.in-use', $asset), ['in_use_on' => '2026-11-03'])->assertSessionHasErrors('in_use_on');
    }

    public function test_the_register_lists_assets_by_tab_and_leaves_out_customer_tools(): void
    {
        $pattern = Pattern::create(['code' => 'PT-0430', 'name' => 'Pump body pattern', 'pattern_type' => 'LOOSE', 'owner_type' => 'FOUNDRY']);
        Pattern::create(['code' => 'PT-9000', 'name' => 'Customer pattern', 'pattern_type' => 'LOOSE', 'owner_type' => 'CUSTOMER', 'owner_customer_id' => Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps'])->id]);
        FixedAsset::factory()->create(['name' => 'Induction furnace IF-1']);
        FixedAsset::factory()->pattern($pattern)->create(['cost' => 1240000]);
        FixedAsset::factory()->inProgress()->create(['name' => 'New sand mixer']);

        $this->get(route('assets.index'))->assertOk()->assertSee('Induction furnace IF-1');
        Livewire::test('assets::register')->assertSee('Induction furnace IF-1')->assertSee('Pump body pattern')
            ->call('showTab', 'TOOLS')->assertSee('Pump body pattern')->assertDontSee('Induction furnace IF-1')->assertSee('Customer-owned tools (1)')
            ->call('showTab', 'IN_PROGRESS')->assertSee('New sand mixer')->assertDontSee('Pump body pattern');
        $this->get(route('assets.create'))->assertOk()->assertSee('PT-0430')->assertDontSee('PT-9000');
    }
}
