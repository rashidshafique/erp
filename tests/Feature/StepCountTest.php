<?php

namespace Tests\Feature;

use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Dispatch\Models\PackingRecordItem;
use App\Models\CastingLot;
use App\Models\Customer;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\Pattern;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Support\ProcessDepth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Drives an order from entry to dispatch and counts what a person has to submit, so the small-foundry path cannot quietly grow.
 * A step is one form sent; pages only looked at do not count.
 */
class StepCountTest extends TestCase
{
    use RefreshDatabase;

    private int $steps = 0;

    /** One form submitted by the user, which must go through cleanly. */
    private function step(string $route, array $data, mixed $parameters = []): TestResponse
    {
        $this->steps++;

        return $this->post(route($route, $parameters), $data)->assertSessionHasNoErrors();
    }

    public function test_basic_takes_an_order_to_dispatch_in_seven_steps(): void
    {
        $this->put(route('settings.process-depth'), ['preset' => 'BASIC']);
        Furnace::create(['code' => 'IF1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 1000, 'nominal_melting_rate_kg_per_hour' => 500]);
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $product = Product::create(['code' => 'PUMP-1', 'name' => 'Pump body']);
        $pattern = Pattern::create(['code' => 'PAT-1', 'name' => 'Pump plate', 'status' => 'ACTIVE']);
        $revision = $pattern->addRevision(['cavities_per_mould' => 2]);
        foreach ([1, 2] as $n) {
            $revision->cavityMap()->create(['cavity_number' => $n, 'product_revision_id' => $product->linkableRevision()->id, 'cavity_role' => 'WHOLE', 'pieces_per_product' => 1]);
        }

        // 1. The order, straight to production.
        $this->step('quick.orders.store', ['customer_id' => $customer->id, 'due_date' => today()->addDays(10)->toDateString(), 'lines' => [
            ['product_id' => $product->id, 'qty' => 20, 'iron_grade_id' => IronGrade::where('code', 'EN-GJS-500-7')->value('id'), 'casting_weight_kg' => 6],
        ]]);
        $order = ProductionOrder::sole();
        // 2. Moulds made, assembled as they are saved.
        $this->step('quick.floor.made', ['rows' => [['pattern_id' => $pattern->id, 'moulds' => 10, 'production_order_id' => $order->id]]]);
        // 3. The heat started from Next heat.
        $this->step('quick.heat.start', ['kg' => 150, 'furnace_id' => Furnace::sole()->id, 'iron_grade_id' => IronGrade::where('code', 'EN-GJS-500-7')->value('id')]);
        // 4. Moulds poured: the pour, its completion and its lots in one.
        $this->step('quick.floor.poured', ['heat_id' => Heat::sole()->id, 'rows' => [['pattern_id' => $pattern->id, 'moulds' => 10, 'production_order_id' => $order->id]]]);
        $this->assertSame(20, (int) CastingLot::sum('quantity_poured'));
        $this->assertSame('COMPLETED', $order->fresh()->status);
        // 5. Quick QC passes the pieces.
        $this->step('quick-qc.store', ['rows' => [$order->product_revision_id => ['accepted' => 20]]]);
        // 6. Packing.
        $this->step('packing.store', ['qty' => CastingLot::pluck('quantity_good', 'id')->all()]);
        // 7. Dispatch.
        $this->step('dispatches.store', ['customer_id' => $customer->id, 'qty' => PackingRecordItem::pluck('quantity_packed', 'id')->all()]);

        $this->assertSame(1, Dispatch::count());
        $this->assertSame(7, $this->steps, 'Basic grew past seven steps from order to dispatch.');
    }
}
