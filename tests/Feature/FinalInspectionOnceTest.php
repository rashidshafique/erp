<?php

namespace Tests\Feature;

use App\Models\QualityInspection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class FinalInspectionOnceTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    public function test_a_final_inspection_completed_from_the_form_moves_the_lot_on_and_cannot_be_repeated(): void
    {
        $lot = $this->castingLot();
        $this->post(route('lots.inspections.store', $lot), ['inspection_type' => 'FINAL', 'sample_size' => '5'])->assertSessionHasNoErrors();
        $inspection = QualityInspection::firstOrFail();

        $this->post(route('lots.inspections.store', $lot), ['inspection_type' => 'FINAL', 'sample_size' => '5'])->assertSessionHasErrors('inspection_type');

        // A browser posts every number as text.
        $this->post(route('inspections.complete', $inspection), ['quantity_accepted' => '5', 'quantity_rejected' => '0', 'quantity_rework' => '0', 'quantity_pending' => '0', 'overall_disposition' => 'ACCEPT'])->assertSessionHasNoErrors();

        $this->assertSame('READY', $lot->fresh()->status);
        $this->post(route('lots.inspections.store', $lot), ['inspection_type' => 'FINAL', 'sample_size' => '5'])->assertSessionHasErrors('inspection_type');
        $this->assertSame(1, QualityInspection::count());
    }

    public function test_a_second_final_inspection_is_allowed_for_pieces_left_pending(): void
    {
        $lot = $this->castingLot();
        $this->post(route('lots.inspections.store', $lot), ['inspection_type' => 'FINAL', 'sample_size' => '5']);
        $this->post(route('inspections.complete', QualityInspection::firstOrFail()), ['quantity_accepted' => '3', 'quantity_rejected' => '0', 'quantity_rework' => '0', 'quantity_pending' => '2', 'overall_disposition' => 'PARTIAL'])->assertSessionHasNoErrors();

        $this->assertSame('IN_PRODUCTION', $lot->fresh()->status);
        $this->post(route('lots.inspections.store', $lot), ['inspection_type' => 'FINAL', 'sample_size' => '2'])->assertSessionHasNoErrors();
        $this->assertSame(2, QualityInspection::count());
    }
}
