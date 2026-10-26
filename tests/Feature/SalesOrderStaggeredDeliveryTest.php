<?php

namespace Tests\Feature;

use App\Domains\Sales\Models\SalesOrder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesOrderStaggeredDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private ProductRevision $revision;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create(['code' => 'ACME', 'name' => 'Acme']);
        $product = Product::create(['code' => 'P1', 'name' => 'Part']);
        $this->revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
    }

    /** @param array<int, array<string, mixed>> $deliveries */
    private function store(array $deliveries): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('sales-orders.store'), [
            'customer_id' => $this->customer->id, 'order_date' => today()->toDateString(),
            'lines' => [['product_revision_id' => $this->revision->id, 'ordered_qty' => 100, 'staggered' => 1, 'deliveries' => $deliveries]],
        ]);
    }

    public function test_a_new_order_saves_its_staggered_deliveries_but_not_more_than_ordered(): void
    {
        $this->store([['due_date' => today()->addWeek()->toDateString(), 'quantity' => 60], ['due_date' => today()->addWeeks(2)->toDateString(), 'quantity' => 50]])
            ->assertSessionHasErrors(['order' => "Line 1's deliveries add up to 110, more than the 100 ordered."]);
        $this->assertSame(0, SalesOrder::count());

        $this->store([['due_date' => today()->addWeek()->toDateString(), 'quantity' => 60, 'notes' => 'First'], ['due_date' => today()->addWeeks(2)->toDateString(), 'quantity' => 30]])
            ->assertSessionHasNoErrors();

        $line = SalesOrder::firstOrFail()->lines()->first();
        $this->assertEquals([[60, 'First'], [30, null]], $line->deliveries->map(fn ($d) => [(float) $d->quantity, $d->notes])->all());
        $this->assertEquals(100, $line->ordered_qty);
    }

    public function test_editing_the_order_changes_adds_and_removes_deliveries_and_unticking_clears_them(): void
    {
        $this->store([['due_date' => today()->addWeek()->toDateString(), 'quantity' => 60], ['due_date' => today()->addWeeks(2)->toDateString(), 'quantity' => 40]]);
        $order = SalesOrder::firstOrFail();
        $line = $order->lines()->first();
        [$first, $second] = $line->deliveries->all();
        $this->get(route('sales-orders.edit', $order))->assertOk()->assertSee('name="lines[0][deliveries][0][id]" value="'.$first->id.'"', false);

        $edit = fn (array $row) => $this->put(route('sales-orders.update', $order), ['customer_id' => $order->customer_id, 'order_date' => today()->toDateString(), 'reason' => 'Customer moved dates',
            'lines' => [['id' => $line->id, 'ordered_qty' => 100] + $row]]);
        $edit(['staggered' => 1, 'deliveries' => [
            ['id' => $first->id, 'due_date' => today()->addDays(10)->toDateString(), 'quantity' => 50],
            ['due_date' => today()->addWeeks(3)->toDateString(), 'quantity' => 50],
        ]])->assertSessionHasNoErrors();

        $this->assertEquals([[$first->id, 50], [null, 50]], $line->deliveries()->get()->map(fn ($d) => [$d->id === $first->id ? $d->id : null, (float) $d->quantity])->all());
        $this->assertFalse($line->deliveries()->whereKey($second->id)->exists());
        $history = DB::table('audit_logs')->where('entity_type', 'SalesOrder')->where('entity_id', $order->id)->pluck('changes')->implode("\n");
        $this->assertStringContainsString('Line 1: delivery of 60 for '.today()->addWeek()->format('j M Y').' → 50 for '.today()->addDays(10)->format('j M Y'), $history);
        $this->assertStringContainsString('Line 1: delivery of 40 for '.today()->addWeeks(2)->format('j M Y').' removed', $history);

        $edit(['staggered' => 0])->assertSessionHasNoErrors();
        $this->assertSame(0, $line->deliveries()->count());
    }
}
