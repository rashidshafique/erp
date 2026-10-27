<?php

namespace Tests\Feature;

use App\Domains\Sales\Models\PriceList;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Domains\Sales\Services\ContractReviewer;
use App\Domains\Sales\Services\PriceLists;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PriceListTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Product $casing;

    private ProductRevision $revision;

    private Product $cover;

    private PriceList $standard;

    /** A default list: casing 100 a piece, 90 from 50 pieces; cover 40 a piece. The casing weighs 5 kg. */
    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        $this->customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $this->casing = Product::create(['code' => 'CAS-1', 'name' => 'Pump casing']);
        $this->revision = ProductRevision::create(['product_id' => $this->casing->id, 'revision' => 'A', 'status' => 'APPROVED', 'net_weight_kg' => 5]);
        $this->cover = Product::create(['code' => 'COV-1', 'name' => 'Cover']);

        $this->post(route('price-lists.store'), ['code' => 'standard', 'name' => 'Standard', 'is_default' => 1])->assertSessionHasNoErrors();
        $this->standard = PriceList::sole();
        $this->put(route('price-lists.items', $this->standard), ['items' => [
            ['product_id' => $this->casing->id, 'basis' => 'PIECE', 'min_quantity' => 0, 'price' => 100],
            ['product_id' => $this->casing->id, 'basis' => 'PIECE', 'min_quantity' => 50, 'price' => 90],
            ['product_id' => $this->cover->id, 'basis' => 'PIECE', 'min_quantity' => 0, 'price' => 40],
        ]])->assertSessionHasNoErrors();
    }

    private function price(Product $product, float $qty, string $uom = 'piece', float $kgEach = 5, string $date = '2026-10-27'): ?float
    {
        return app(PriceLists::class)->priceFor($this->customer->id, $product->id, $qty, $uom, $kgEach, Carbon::parse($date))['price'] ?? null;
    }

    public function test_the_default_list_prices_by_the_highest_break_reached(): void
    {
        $this->assertSame([100.0, 90.0, 40.0], [$this->price($this->casing, 10), $this->price($this->casing, 60), $this->price($this->cover, 1)]);
        $this->assertSame('STANDARD', $this->standard->code);
    }

    public function test_a_customers_own_list_comes_first_converts_per_kg_and_the_default_fills_its_gaps(): void
    {
        $this->post(route('price-lists.store'), ['code' => 'ACME', 'name' => 'Acme contract'])->assertSessionHasNoErrors();
        $acme = PriceList::where('code', 'ACME')->sole();
        $this->put(route('price-lists.items', $acme), ['items' => [['product_id' => $this->casing->id, 'basis' => 'KG', 'min_quantity' => 0, 'price' => 18]]])->assertSessionHasNoErrors();
        $this->put(route('price-lists.assign', $this->customer), ['price_list_id' => $acme->id])->assertSessionHasNoErrors();

        $this->assertSame([90.0, 18.0, 40.0], [$this->price($this->casing, 10), $this->price($this->casing, 50, 'kg'), $this->price($this->cover, 1)]);
        $this->assertSame(100.0, $this->price($this->casing, 10, 'piece', 0), 'with no casing weight the per-kg price cannot price a piece, so the default list does');

        $acme->update(['valid_to' => '2026-09-30']);
        $this->assertSame(100.0, $this->price($this->casing, 10));
    }

    public function test_the_order_form_lookup_and_the_contract_review_use_the_list(): void
    {
        $this->getJson(route('price-lists.lookup', ['customer' => $this->customer->id, 'product' => $this->revision->id, 'qty' => 60, 'uom' => 'piece']))
            ->assertOk()->assertJson(['price' => 90, 'list' => 'STANDARD']);
        $this->getJson(route('price-lists.lookup', ['product' => 'product:'.$this->cover->id, 'qty' => 1]))->assertJson(['price' => 40]);

        $order = SalesOrder::create(['order_number' => 'SO-1', 'customer_id' => $this->customer->id, 'order_date' => '2026-10-27', 'status' => 'DRAFT']);
        $line = SalesOrderLine::create(['sales_order_id' => $order->id, 'product_id' => $this->casing->id, 'product_revision_id' => $this->revision->id, 'line_number' => 1, 'ordered_qty' => 10, 'uom' => 'piece', 'unit_price' => 95]);
        $price = collect(app(ContractReviewer::class)->checkLine($order, $line->fresh(['product', 'productRevision'])))->firstWhere(0, 'PRICE');
        $this->assertSame('WARN', $price[1]);
        $this->assertStringContainsString('STANDARD', $price[2]);

        $line->update(['unit_price' => 100]);
        $this->assertNull(collect(app(ContractReviewer::class)->checkLine($order, $line->fresh(['product', 'productRevision'])))->firstWhere(0, 'PRICE'));
    }

    public function test_a_copy_moves_every_price_and_starts_switched_off(): void
    {
        $this->post(route('price-lists.copy', $this->standard), ['code' => 'std-2027', 'name' => 'Standard 2027', 'percent' => 10, 'valid_from' => '2027-01-01'])->assertSessionHasNoErrors();

        $copy = PriceList::where('code', 'STD-2027')->sole();
        $this->assertSame([false, false], [$copy->is_active, $copy->is_default]);
        $this->assertSame([110.0, 99.0, 44.0], $copy->items()->get()->sortByDesc('price')->pluck('price')->values()->all());
    }

    public function test_the_same_break_twice_is_refused_and_the_pages_open(): void
    {
        $this->put(route('price-lists.items', $this->standard), ['items' => [
            ['product_id' => $this->cover->id, 'basis' => 'PIECE', 'min_quantity' => 0, 'price' => 40],
            ['product_id' => $this->cover->id, 'basis' => 'PIECE', 'min_quantity' => 0, 'price' => 41],
        ]])->assertSessionHasErrors('items');
        $this->assertSame(3, $this->standard->items()->count());

        $this->get(route('price-lists.index'))->assertOk()->assertSee('STANDARD');
        $this->get(route('price-lists.show', $this->standard))->assertOk()->assertSee('Pump casing');
        $this->get(route('parties.show', $this->customer->party_id))->assertOk()->assertSee('Default: STANDARD');
    }
}
