<?php

namespace Tests\Feature;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Enquiry\Services\QuotationService;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Support\OrderRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A new sales order started from a sent quotation (design 23a): its lines and prices carried over, and creating it accepts the quote. */
class SalesOrderFromQuoteTest extends TestCase
{
    use RefreshDatabase;

    /** A sent quotation with two parts: a repeat part already in the catalogue and a new one with no product yet. */
    private function sent(int $validDays = 17, string $code = 'BH-2'): Quotation
    {
        $c = Customer::create(['code' => 'C'.uniqid(), 'name' => 'Meridian Auto', 'payment_terms' => 'Net 30']);
        $e = Enquiry::create(['customer_id' => $c->id, 'title' => 'Diff carrier', 'source_type' => 'DRAWING', 'status' => 'QUOTED', 'received_on' => today()->subDays(20)]);
        $product = Product::create(['code' => $code, 'name' => 'Bearing housing']);
        ProductRevision::create(['product_id' => $product->id, 'revision' => 'B', 'status' => 'APPROVED']);
        $e->items()->create(['line_no' => 1, 'description' => 'Bearing housing', 'product_id' => $product->id, 'material_text' => 'EN-GJL-250', 'weight_kg' => 6.8, 'annual_qty' => 4000, 'first_batch_qty' => 400, 'family' => 'GREY', 'feasibility_status' => 'OK']);
        $e->items()->create(['line_no' => 2, 'description' => 'Diff carrier DC-4', 'material_text' => 'EN-GJS-500-7', 'weight_kg' => 12.4, 'annual_qty' => 6000, 'first_batch_qty' => 600, 'family' => 'DUCTILE', 'feasibility_status' => 'OK']);
        $q = app(QuotationService::class)->create($e->fresh('items'));
        $q->update(['status' => 'ISSUED', 'issued_at' => now()->subDays(10), 'valid_until' => today()->addDays($validDays), 'delivery_weeks' => 8]);

        return $q->fresh('lines.item');
    }

    public function test_the_form_offers_quotes_awaiting_an_answer_and_starts_with_every_line_at_the_quoted_price(): void
    {
        $q = $this->sent();
        $old = $this->sent(-1, 'BH-3');
        OrderRules::set('prices', 'SHOW');
        [$repeat, $new] = [$q->lines->firstWhere('line_no', 1), $q->lines->firstWhere('line_no', 2)];

        $this->get(route('sales-orders.create'))->assertOk()->assertSee('From quote (1 awaiting an answer)')
            ->assertSee(route('quotations.order', $q), false)->assertDontSee(route('quotations.order', $old), false);

        $this->get(route('quotations.order', $q))->assertOk()
            ->assertSee('Meridian Auto')->assertSee('Creating the order marks it accepted.')
            ->assertSee('name="lines[0][quote_line_id]" value="'.$repeat->id.'"', false)->assertSee('name="lines[1][quote_line_id]" value="'.$new->id.'"', false)
            ->assertSee('BH-2 · rev B')->assertSee('New part · the product is made with the order')
            ->assertSee('name="lines[0][unit_price]" step="any" min="0" value="'.rtrim(rtrim((string) $repeat->unit_price, '0'), '.').'"', false)
            ->assertSee('data-ref-name="Quote '.$q->code.'"', false)
            ->assertDontSee('class="btn" data-add-line', false)->assertDontSee('id="so-form" data-copy', false);

        $this->get(route('quotations.order', $old))->assertRedirect(route('quotations.show', $old));
    }

    public function test_creating_the_order_accepts_the_quote_with_the_lines_as_typed(): void
    {
        $q = $this->sent();
        [$repeat, $new] = [$q->lines->firstWhere('line_no', 1), $q->lines->firstWhere('line_no', 2)];
        $due = today()->addWeeks(6)->toDateString();

        $this->post(route('quotations.order.store', $q), ['customer_reference' => '4500-88190', 'requested_delivery_date' => $due, 'lines' => [
            ['quote_line_id' => $new->id, 'ordered_qty' => 450, 'unit_price' => $new->unit_price, 'discount_percent' => 2],
        ]])->assertSessionHasNoErrors()->assertRedirect();

        $q->refresh();
        $this->assertSame('ACCEPTED', $q->status);
        $this->assertSame('4500-88190', $q->customer_po);
        $this->assertSame('WON', $q->enquiry->fresh()->status);
        $order = SalesOrder::with('lines.product')->findOrFail($q->sales_order_id);
        $this->assertSame('4500-88190', $order->customer_reference);
        $this->assertSame($due, $order->requested_delivery_date->toDateString());
        $this->assertCount(1, $order->lines);
        $line = $order->lines->first();
        $this->assertSame('Diff carrier DC-4', $line->product->name);
        $this->assertEquals(450, $line->ordered_qty);
        $this->assertEquals(2, $line->discount_percent);
        $this->assertEquals((float) $new->unit_price, (float) $line->quoted_price);
        $this->assertNotNull($new->item->fresh()->product_id);
        $this->get(route('sales-orders.edit', $order))->assertOk()->assertSee('name="lines[0][quoted_price]" value="'.(float) $new->unit_price.'"', false);
        $this->assertFalse($order->lines->contains('product_id', $repeat->item->product_id));
    }

    public function test_the_same_quote_cannot_make_a_second_order_and_an_expired_one_makes_none(): void
    {
        $q = $this->sent();
        $line = ['quote_line_id' => $q->lines->first()->id, 'ordered_qty' => 10];

        $this->post(route('quotations.order.store', $q), ['lines' => [$line]])->assertRedirect();
        $this->post(route('quotations.order.store', $q), ['lines' => [$line]])->assertSessionHas('error');
        $this->assertSame(1, SalesOrder::count());

        $expired = $this->sent(-1, 'BH-3');
        $this->post(route('quotations.order.store', $expired), ['lines' => [['quote_line_id' => $expired->lines->first()->id, 'ordered_qty' => 10]]])->assertSessionHas('error');
        $this->assertSame(1, SalesOrder::count());
        $this->assertSame('ISSUED', $expired->fresh()->status);
    }

    public function test_an_ordinary_order_cannot_claim_a_quoted_price(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $rev = ProductRevision::create(['product_id' => Product::create(['code' => 'FLG-1', 'name' => 'Flange'])->id, 'revision' => 'A', 'status' => 'APPROVED']);

        $this->post(route('sales-orders.store'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'lines' => [
            ['product_revision_id' => $rev->id, 'ordered_qty' => 10, 'unit_price' => 5, 'quoted_price' => 5],
        ]])->assertSessionHasNoErrors();

        $this->assertNull(\App\Domains\Sales\Models\SalesOrderLine::sole()->quoted_price);
    }

    public function test_a_line_must_belong_to_the_quote(): void
    {
        $q = $this->sent();
        $other = $this->sent(code: 'BH-3');

        $this->post(route('quotations.order.store', $q), ['lines' => [['quote_line_id' => $other->lines->first()->id, 'ordered_qty' => 10]]])
            ->assertSessionHasErrors('lines.0.quote_line_id');
        $this->assertSame(0, SalesOrder::count());
    }

    public function test_the_price_check_compares_a_quoted_line_with_the_quote_not_the_price_list(): void
    {
        $q = $this->sent();
        OrderRules::set('prices', 'SHOW');
        $repeat = $q->lines->firstWhere('line_no', 1);
        $rev = ProductRevision::where('product_id', $repeat->item->product_id)->sole();
        $input = fn (float $price) => ['customer_id' => $q->enquiry->customer_id, 'order_date' => today()->toDateString(), 'lines' => [
            ['product_revision_id' => $rev->id, 'quote_line_id' => $repeat->id, 'quoted_price' => 100, 'ordered_qty' => 10, 'unit_price' => $price, 'uom' => 'piece'],
        ]];

        $price = collect($this->postJson(route('sales-orders.checks'), $input(100))->assertOk()->json('rows'))->firstWhere('name', 'Price');
        $this->assertSame('ok', $price['status']);
        $this->assertStringContainsString('at the quoted price', $price['text']);

        $price = collect($this->postJson(route('sales-orders.checks'), $input(90))->assertOk()->json('rows'))->firstWhere('name', 'Price');
        $this->assertSame('warn', $price['status']);
        $this->assertStringContainsString('under the quoted 100.00', $price['text']);
    }
}
