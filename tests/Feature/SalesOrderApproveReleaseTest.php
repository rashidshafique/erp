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

class SalesOrderApproveReleaseTest extends TestCase
{
    use RefreshDatabase;

    /** The review's line checks come back with these findings, so each verdict can be tried without building a whole product. */
    private function findings(array $findings): void
    {
        $this->partialMock(ContractReviewer::class, fn ($mock) => $mock->shouldReceive('checkLine')->andReturn($findings));
    }

    private function order(): SalesOrder
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme']);
        $product = Product::create(['code' => 'P1', 'name' => 'Part']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = SalesOrder::create(['order_number' => SalesOrder::nextNumber(), 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'DRAFT']);
        $order->lines()->create(['product_id' => $product->id, 'product_revision_id' => $revision->id, 'line_number' => 1, 'ordered_qty' => 10, 'uom' => 'piece']);

        return $order;
    }

    public function test_one_click_runs_the_review_signs_and_releases_every_line(): void
    {
        OrderRules::set('contract_review', 'AUTO');
        $this->findings([['FURNACE', 'PASS', 'A furnace can melt it.']]);
        $order = $this->order();
        $this->actingAs(User::factory()->create(['name' => 'Ayesha Khan']));

        $this->post(route('sales-orders.approve-release', $order))->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'Approved and released'));

        $this->assertSame(['IN_PRODUCTION', 'APPROVED', 'RELEASED'], [$order->fresh()->status, $order->currentReview()->status, ProductionOrder::sole()->status]);
    }

    public function test_a_blocked_review_refuses_and_changes_nothing(): void
    {
        OrderRules::set('contract_review', 'AUTO');
        $this->findings([['REVISION_STATUS', 'BLOCK', 'The revision is not approved.']]);
        $order = $this->order();

        $this->post(route('sales-orders.approve-release', $order), ['name' => 'Ayesha Khan'])->assertSessionHasErrors('status');

        $this->assertSame(['UNDER_REVIEW', 0], [$order->fresh()->status, ProductionOrder::count()]);
    }

    public function test_a_warning_without_a_note_refuses(): void
    {
        OrderRules::set('contract_review', 'AUTO');
        $this->findings([['GRADE', 'WARN', 'Only the nearest grade.']]);
        $order = $this->order();

        $this->post(route('sales-orders.approve-release', $order), ['name' => 'Ayesha Khan'])->assertSessionHasErrors('status');
        $this->assertSame(0, ProductionOrder::count());

        $this->actingAs(User::factory()->create(['name' => 'Ayesha Khan']));
        $this->post(route('sales-orders.review.acknowledge', $order), ['item_id' => $order->currentReview()->items->first()->id, 'reason' => 'Agreed with the buyer.']);
        $this->post(route('sales-orders.approve-release', $order))->assertSessionHasNoErrors();

        $this->assertSame('IN_PRODUCTION', $order->fresh()->status);
    }

    public function test_two_sign_offs_keep_the_first_and_release_only_when_a_second_person_signs(): void
    {
        OrderRules::set('contract_review', 'FULL');
        $this->findings([['FURNACE', 'PASS', 'A furnace can melt it.']]);
        $order = $this->order();

        $this->actingAs(User::factory()->create(['name' => 'Ayesha Khan']));
        $this->post(route('sales-orders.approve-release', $order))->assertSessionHas('success', fn ($m) => str_contains($m, 'Sales sign-off recorded'));
        $this->assertSame([0, 'IN_REVIEW'], [ProductionOrder::count(), $order->currentReview()->status]);

        $this->actingAs(User::factory()->create(['name' => 'Bilal Raza']));
        $this->post(route('sales-orders.approve-release', $order))->assertSessionHasNoErrors();

        $this->assertSame(['IN_PRODUCTION', 1], [$order->fresh()->status, ProductionOrder::count()]);
    }

    public function test_with_the_review_off_it_just_releases(): void
    {
        OrderRules::set('contract_review', 'SKIP');
        $order = $this->order();

        $this->post(route('sales-orders.approve-release', $order), ['name' => 'Ayesha Khan'])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Released'));

        $this->assertSame(['IN_PRODUCTION', 1], [$order->fresh()->status, ProductionOrder::count()]);
    }
}
