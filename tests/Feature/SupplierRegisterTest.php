<?php

namespace Tests\Feature;

use App\Domains\Purchasing\Models\Supplier;
use App\Models\Material;
use App\Models\MaterialLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierRegisterTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(array $extra = []): Supplier
    {
        return Supplier::create($extra + ['name' => 'Acme Pig Iron', 'review_interval_days' => 180]);
    }

    private function evaluate(Supplier $supplier, array $fields = [])
    {
        return $this->post(route('suppliers.evaluate', $supplier), $fields + ['evaluated_on' => today()->toDateString(), 'quality_score' => 90, 'delivery_score' => 80, 'service_score' => 70, 'decision' => 'APPROVED']);
    }

    public function test_a_supplier_is_added_pending_with_a_generated_code(): void
    {
        $this->post(route('suppliers.store'), ['name' => 'Acme', 'review_interval_days' => 365])->assertSessionHasNoErrors();

        $supplier = Supplier::firstOrFail();
        $this->assertSame('PENDING', $supplier->approval_status);
        $this->assertStringStartsWith('SUP', $supplier->code);
    }

    public function test_an_evaluation_scores_the_supplier_and_sets_status_and_next_review(): void
    {
        $supplier = $this->supplier();

        $this->evaluate($supplier)->assertSessionHasNoErrors();

        $supplier->refresh();
        $this->assertSame(['APPROVED', '80.0', today()->addDays(180)->toDateString()], [$supplier->approval_status, (string) $supplier->latest_score, $supplier->next_review_on->toDateString()]);
        $this->assertCount(1, $supplier->evaluations);
    }

    public function test_a_suspended_supplier_has_no_review_date_and_scores_are_limited_to_100(): void
    {
        $supplier = $this->supplier();

        $this->evaluate($supplier, ['quality_score' => 101])->assertSessionHasErrors('quality_score');
        $this->evaluate($supplier, ['decision' => 'SUSPENDED']);

        $this->assertNull($supplier->refresh()->next_review_on);
        $this->assertTrue($supplier->isBlocked());
    }

    public function test_a_lot_cannot_be_received_from_a_blocked_supplier_but_can_from_an_approved_one(): void
    {
        $material = Material::create(['code' => 'PIG', 'name' => 'Pig iron', 'material_type' => 'PIG_IRON', 'base_uom' => 'kg', 'status' => 'ACTIVE']);
        $lot = fn (Supplier $s) => $this->post(route('materials.lots.store', $material), [
            'lot_number' => 'L-'.uniqid(), 'supplier_id' => $s->id, 'status' => 'QUARANTINE', 'received_qty' => 10, 'accepted_qty' => 0, 'currency_code' => 'PKR',
        ]);

        $lot($this->supplier(['approval_status' => 'SUSPENDED']))->assertSessionHasErrors('supplier_id');
        $this->assertSame(0, MaterialLot::count());

        $approved = $this->supplier(['approval_status' => 'APPROVED']);
        $lot($approved)->assertSessionHasNoErrors();
        $this->assertSame([$approved->id, 'Acme Pig Iron'], [MaterialLot::first()->supplier_id, MaterialLot::first()->supplier_name_snapshot]);
    }
}
