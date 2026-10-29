<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductionStageDefinition;
use App\Models\Role;
use App\Models\User;
use App\Services\Production\AfterPour;
use App\Services\Production\LotService;
use App\Services\Production\ProcessRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class ProductRouteTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        app(ProcessRoutes::class)->saveDefault(array_map(fn ($code) => $this->id($code), ['SHAKEOUT', 'CUTTING', 'SHOT_BLAST', 'FINAL_INSPECTION']));
    }

    private function id(string $code): string
    {
        return ProductionStageDefinition::where('code', $code)->value('id');
    }

    /** @return list<string> */
    private function codes(?string $revisionId): array
    {
        return app(ProcessRoutes::class)->stepsFor($revisionId)->pluck('code')->all();
    }

    public function test_a_product_follows_the_default_route_until_it_is_customised_and_then_its_own_route_only(): void
    {
        $lot = $this->castingLot();
        $other = $this->castingLot();
        $default = $this->codes($lot->product_revision_id);
        $this->assertSame(['SHAKEOUT', 'CUTTING', 'SHOT_BLAST', 'FINAL_INSPECTION'], $default);

        $routes = app(ProcessRoutes::class);
        $routes->saveForProduct($lot->product, [$this->id('SHAKEOUT'), $this->id('MACHINING'), $this->id('CUTTING'), $this->id('FINAL_INSPECTION')]);

        $this->assertSame(['SHAKEOUT', 'MACHINING', 'CUTTING', 'FINAL_INSPECTION'], $this->codes($lot->product_revision_id));
        $this->assertSame($default, $this->codes($other->product_revision_id));
        $this->assertSame(['SHAKEOUT', 'MACHINING', 'CUTTING'], app(AfterPour::class)->stepsFor($lot->product_revision_id)->pluck('code')->all());
        $this->get(route('lots.traveller', $lot))->assertOk()->assertSee('Machining')->assertDontSee('Shot blast');

        // Changing the default route leaves the customised product alone; back to default follows it again.
        $routes->saveDefault([$this->id('SHAKEOUT'), $this->id('FINAL_INSPECTION')]);
        $this->assertSame(['SHAKEOUT', 'MACHINING', 'CUTTING', 'FINAL_INSPECTION'], $this->codes($lot->product_revision_id));
        $routes->resetToDefault($lot->product);
        $this->assertSame(['SHAKEOUT', 'FINAL_INSPECTION'], $this->codes($lot->product_revision_id));
    }

    public function test_stages_are_recorded_in_the_order_of_the_products_route(): void
    {
        $lot = $this->castingLot();
        app(ProcessRoutes::class)->saveForProduct($lot->product, [$this->id('CUTTING'), $this->id('SHAKEOUT'), $this->id('FINAL_INSPECTION')]);
        $service = app(LotService::class);
        $in = ['quantity_in' => 5, 'quantity_accepted' => 5, 'quantity_rejected' => 0];

        $service->recordStage($lot, ProductionStageDefinition::find($this->id('CUTTING')), $in);
        $service->recordStage($lot, ProductionStageDefinition::find($this->id('SHAKEOUT')), $in);   // later on this route, though earlier by default

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already passed a later stage (Shakeout)');
        $service->recordStage($lot, ProductionStageDefinition::find($this->id('CUTTING')), $in);
    }

    public function test_the_routing_tab_customises_reorders_adds_and_goes_back_to_default(): void
    {
        $product = Product::create(['code' => 'VB-1', 'name' => 'Valve body']);
        $this->get(route('products.show', $product))->assertOk()->assertSee('Default route')->assertSee('Customise for this product');

        $tab = Livewire::test('product-route', ['product' => $product])->call('customise')->assertSee('Custom for this product');
        $tab->call('remove', $this->id('SHOT_BLAST'))->call('add', $this->id('MACHINING'))->call('move', 3, -1);
        $this->assertSame(['SHAKEOUT', 'CUTTING', 'MACHINING', 'FINAL_INSPECTION'], app(ProcessRoutes::class)->productRoute($product)->operations()->pluck('code')->all());

        $tab->call('add', $this->id('MACHINING'))->assertSee('A step can only be on the route once.');
        $tab->call('resetToDefault')->assertSee('Default route');
        $this->assertNull(app(ProcessRoutes::class)->productRoute($product));
    }

    public function test_an_archived_product_or_a_user_without_rights_cannot_change_the_route(): void
    {
        $product = Product::create(['code' => 'VB-2', 'name' => 'Valve body']);
        $this->actingAs(User::factory()->create(['is_active' => true, 'must_change_password' => false]));
        Livewire::test('product-route', ['product' => $product])->assertDontSee('Customise for this product')->call('customise')->assertForbidden();

        $product->delete();
        $this->actingAs(User::whereHas('roles', fn ($q) => $q->where('code', 'ADMIN'))->first());
        Livewire::test('product-route', ['product' => $product])->call('customise')->assertForbidden();
    }
}
