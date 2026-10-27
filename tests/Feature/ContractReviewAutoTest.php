<?php

namespace Tests\Feature;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Services\ContractReviewer;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Models\User;
use App\Support\OrderRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractReviewAutoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OrderRules::set('contract_review', 'AUTO');
        OrderRules::set('order_flow', 'SIMPLE');
    }

    /** The review's line checks come back with these findings, so each verdict can be tried without building a whole product. */
    private function findings(array $findings): void
    {
        $this->partialMock(ContractReviewer::class, fn ($mock) => $mock->shouldReceive('checkLine')->andReturn($findings));
    }

    private function saveOrder(): \Illuminate\Testing\TestResponse
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme']);
        $product = Product::create(['code' => 'P1', 'name' => 'Part']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);

        return $this->post(route('sales-orders.store'), ['customer_id' => $customer->id, 'order_date' => today()->toDateString(), 'lines' => [
            ['product_revision_id' => $revision->id, 'ordered_qty' => 10],
        ]]);
    }

    public function test_a_clean_review_approves_itself_and_the_order_goes_straight_to_production(): void
    {
        $this->findings([['FURNACE', 'PASS', 'A furnace can melt it.']]);

        $this->saveOrder()->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'sent to production'));

        $order = SalesOrder::sole();
        $this->assertSame(['IN_PRODUCTION', 'APPROVED'], [$order->status, $order->currentReview()->status]);
        $this->assertSame('RELEASED', ProductionOrder::sole()->status);
    }

    public function test_warnings_hold_the_order_until_one_person_acknowledges_and_approves(): void
    {
        $this->findings([['GRADE', 'WARN', 'The customer grade is only the nearest match.']]);

        $this->saveOrder()->assertSessionHas('success', fn ($m) => str_contains($m, 'warnings'));
        $order = SalesOrder::sole();
        $this->assertSame(['UNDER_REVIEW', 0], [$order->status, ProductionOrder::count()]);

        $review = $order->currentReview();
        $this->actingAs(User::factory()->create(['name' => 'Ayesha Khan']));
        $this->post(route('sales-orders.review.acknowledge', $order), ['item_id' => $review->items->first()->id, 'reason' => 'Agreed with the buyer.'])->assertSessionHasNoErrors();
        $this->post(route('sales-orders.review.sign', $order), ['role' => 'sales'])->assertSessionHasNoErrors();

        $this->assertSame('APPROVED', $review->fresh()->status);
        $this->assertTrue($order->fresh()->hasApprovedReview());
    }

    public function test_a_blocked_review_stops_the_order(): void
    {
        $this->findings([['REVISION_STATUS', 'BLOCK', 'The revision is not approved.']]);

        $this->saveOrder()->assertSessionHas('success', fn ($m) => str_contains($m, 'blocking'));

        $this->assertSame(['UNDER_REVIEW', 0], [SalesOrder::sole()->status, ProductionOrder::count()]);
    }

    public function test_in_the_step_by_step_flow_a_clean_review_only_confirms_the_order(): void
    {
        OrderRules::set('order_flow', 'FULL');
        $this->findings([]);

        $this->saveOrder();

        $this->assertSame(['CONFIRMED', 0], [SalesOrder::sole()->status, ProductionOrder::count()]);
    }

    public function test_when_both_must_sign_the_person_who_signed_sales_is_not_offered_the_quality_signature(): void
    {
        OrderRules::set('contract_review', 'REQUIRED');
        OrderRules::set('order_flow', 'FULL');
        $this->findings([['FURNACE', 'PASS', 'A furnace can melt it.']]);
        $this->actingAs(User::factory()->create(['name' => 'Ayesha Khan']));
        $this->saveOrder();
        $order = SalesOrder::sole();
        $this->post(route('sales-orders.review', $order));

        $this->post(route('sales-orders.review.sign', $order), ['role' => 'sales'])->assertSessionHasNoErrors();

        $this->get(route('sales-orders.show', $order))->assertOk()
            ->assertDontSee('Sign as quality')
            ->assertSee('Needs someone other than Ayesha Khan, who signed as sales.');
    }
}
