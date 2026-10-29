<?php

namespace Tests\Feature;

use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Dispatch\Models\DispatchItem;
use App\Domains\Dispatch\Models\PackingRecord;
use App\Domains\Dispatch\Models\PackingRecordItem;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Models\CastingLot;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\ProductSpecification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private Product $valve;

    private Product $cap;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        $this->valve = Product::create(['code' => 'VALVE-1', 'name' => 'Valve body']);
        $this->cap = Product::create(['code' => 'CAP-1', 'name' => 'Cap']);
    }

    public function test_the_side_panel_shows_the_latest_sales_price_and_the_pieces_shipped(): void
    {
        $revision = ProductRevision::create(['product_id' => $this->valve->id, 'revision' => 'A', 'status' => 'APPROVED']);
        foreach (['pour_mould_assembly_id', 'production_order_id'] as $column) {
            DB::statement("ALTER TABLE casting_lots ALTER COLUMN {$column} DROP NOT NULL");   // undone with the test's transaction
        }
        $lot = CastingLot::create(['lot_number' => 'LOT-1', 'product_id' => $this->valve->id, 'product_revision_id' => $revision->id, 'status' => 'OPEN', 'quantity_poured' => 10]);
        $packed = PackingRecordItem::create(['packing_record_id' => PackingRecord::create(['packing_number' => 'PK-1', 'status' => 'DRAFT'])->id, 'casting_lot_id' => $lot->id, 'quantity_packed' => 10, 'uom' => 'PCS']);
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $dispatch = Dispatch::create(['dispatch_number' => 'DS-1', 'customer_id' => $customer->id, 'dispatched_at' => now()]);
        DispatchItem::create(['dispatch_id' => $dispatch->id, 'packing_record_item_id' => $packed->id, 'quantity_dispatched' => 4]);
        foreach ([['SO-1', today()->subMonths(2), 100], ['SO-2', today(), 125]] as [$number, $date, $price]) {
            $order = SalesOrder::create(['order_number' => $number, 'customer_id' => $customer->id, 'order_date' => $date, 'status' => 'CONFIRMED']);
            SalesOrderLine::create(['sales_order_id' => $order->id, 'product_id' => $this->valve->id, 'product_revision_id' => $revision->id, 'line_number' => 1, 'ordered_qty' => 2, 'unit_price' => $price, 'uom' => 'piece']);
        }

        $list = Livewire::test('product-list');
        $list->call('focus', $this->valve->id)->assertSee('125.00')->assertSee('4 pcs');   // the side panel: latest price and pieces shipped in a year
    }

    public function test_the_list_shows_each_product_customer_and_can_be_narrowed_by_customer_and_status_tab(): void
    {
        $revision = ProductRevision::create(['product_id' => $this->valve->id, 'revision' => 'A', 'status' => 'DRAFT']);
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $order = SalesOrder::create(['order_number' => 'SO-1', 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'CONFIRMED']);
        SalesOrderLine::create(['sales_order_id' => $order->id, 'product_id' => $this->valve->id, 'product_revision_id' => $revision->id, 'line_number' => 1, 'ordered_qty' => 2, 'unit_price' => 50, 'uom' => 'piece']);

        $list = Livewire::test('product-list');
        $list->assertSee('Acme Pumps');
        $list->set('customer', $customer->id)->assertSee('VALVE-1')->assertDontSee('CAP-1');
        $list->call('resetFilters')->call('showStatus', 'dev')->assertSee('VALVE-1')->assertDontSee('CAP-1');
        $list->call('resetFilters')->set('search', 'DWG-77')->assertDontSee('VALVE-1');
        ProductSpecification::create(['product_revision_id' => $revision->id, 'specification_code' => 'SP-1', 'title' => 'Valve spec', 'drawing_number' => 'DWG-77', 'material_grade' => 'EN-GJS-500-7']);
        $list->set('search', 'DWG-77')->assertSee('VALVE-1')->assertDontSee('CAP-1');
        $list->call('resetFilters')->set('metal', (string) \App\Models\IronGrade::resolve('EN-GJS-500-7')['grade']->id)->assertSee('VALVE-1')->assertDontSee('CAP-1');
        $list->call('focus', $this->valve->id)->assertSee('SO-1');
    }

    public function test_the_product_page_overview_and_bom_tabs_show_the_commercial_facts_and_material_cost(): void
    {
        $revision = ProductRevision::create(['product_id' => $this->valve->id, 'revision' => 'A', 'status' => 'APPROVED', 'net_weight_kg' => 2]);
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $order = SalesOrder::create(['order_number' => 'SO-9', 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'CONFIRMED']);
        SalesOrderLine::create(['sales_order_id' => $order->id, 'product_id' => $this->valve->id, 'product_revision_id' => $revision->id, 'line_number' => 1, 'ordered_qty' => 30, 'unit_price' => 14.8, 'uom' => 'piece']);
        $filter = \App\Models\Material::create(['code' => 'FL-1', 'name' => 'Foam filter', 'material_type' => 'OTHER', 'base_uom' => 'piece', 'status' => 'ACTIVE', 'standard_cost_per_uom' => 1.25]);
        \App\Domains\Engineering\Models\BomLine::create(['product_revision_id' => $revision->id, 'material_id' => $filter->id, 'quantity' => 2, 'basis' => 'PER_CASTING']);

        $this->get(route('products.show', $this->valve))->assertOk()->assertSee('Acme Pumps')->assertSee('SO-9 · 30 pcs', false)->assertSee('14.80')
            ->assertSee('Material cost per casting')->assertSee('Foam filter')->assertSee('2.50');
    }

    public function test_the_status_switch_and_bulk_actions_set_products_active_or_inactive(): void
    {
        $list = Livewire::test('product-list')->call('toggleActive', $this->valve->id);
        $this->assertFalse($this->valve->fresh()->active);

        $list->set('selected', [$this->valve->id, $this->cap->id])->call('setActive', false)->assertSet('selected', []);
        $this->assertFalse($this->cap->fresh()->active);
        $list->set('selected', [$this->valve->id, $this->cap->id])->call('setActive', true);
        $this->assertTrue($this->valve->fresh()->active && $this->cap->fresh()->active);
    }

    public function test_a_user_without_product_rights_cannot_change_status(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true, 'must_change_password' => false]));

        Livewire::test('product-list')->call('toggleActive', $this->valve->id)->assertForbidden();
        $this->assertTrue($this->valve->fresh()->active);
    }

    public function test_a_product_bought_for_resale_is_saved_as_stock_in_trade_with_its_pct_code(): void
    {
        $this->assertSame('FINISHED_GOODS', $this->valve->fresh()->inventory_class);

        $this->post(route('products.store'), ['name' => 'Ladle liner', 'product_type' => 'OTHER', 'base_uom' => 'piece', 'active' => 1, 'revision' => 'A', 'revision_status' => 'APPROVED',
            'inventory_class' => 'STOCK_IN_TRADE', 'tariff_code' => '69022000', 'origin_country' => 'CN'])->assertSessionHasNoErrors();
        $liner = Product::where('name', 'Ladle liner')->sole();
        $this->assertSame(['STOCK_IN_TRADE', '6902.2000', 'CN'], array_values($liner->only('inventory_class', 'tariff_code', 'origin_country')));
    }

    public function test_products_sit_in_the_sales_menu(): void
    {
        $html = $this->get(route('products.index'))->assertOk()->assertSee('Products')->getContent();

        $sales = substr($html, strpos($html, '<span>Sales &amp; Planning</span>'));
        $this->assertStringContainsString(route('products.index'), substr($sales, 0, strpos($sales, 'nav-group-toggle')));
    }
}
