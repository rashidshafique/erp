<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\NonconformanceReport;
use App\Models\QualityDefect;
use App\Models\QualityDefectMark;
use App\Models\QualityInspection;
use App\Support\ProcessDepth;
use App\Support\QualityRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class QuickQcTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    private function secondLotOfSameProduct(CastingLot $first): CastingLot
    {
        $other = $this->castingLot();
        $other->update(['product_id' => $first->product_id, 'product_revision_id' => $first->product_revision_id, 'created_at' => now()->addMinute()]);

        return $other->fresh();
    }

    public function test_the_tally_is_shared_over_lots_oldest_first_and_closes_each_inspection(): void
    {
        $first = $this->castingLot();
        $second = $this->secondLotOfSameProduct($first);
        $defect = QualityDefect::where('code', 'SAND-INCL')->firstOrFail();

        $this->get(route('quick-qc.index'))->assertOk()->assertSee('10');
        $this->post(route('quick-qc.store'), ['rows' => [$first->product_revision_id => ['accepted' => 6, 'rework' => 0, 'rejected' => 2, 'defects' => [$defect->id => 2]]]])->assertSessionHasNoErrors();

        $this->assertSame(0, $first->fresh()->quantity_rejected);
        $this->assertSame('READY', $first->fresh()->status);
        $this->assertSame(2, $second->fresh()->quantity_rejected);
        $this->assertSame(3, $second->fresh()->quantity_good);
        $this->assertSame(2, QualityInspection::where('status', 'COMPLETED')->count());
        $this->assertSame(8, (int) QualityInspection::sum('quantity_inspected'));
        $this->assertSame(1, QualityInspection::whereHas('results')->count());
        $this->assertSame(2, (int) NonconformanceReport::sum('quantity_affected'));
    }

    public function test_more_than_is_left_to_check_is_refused_and_nothing_is_saved(): void
    {
        $lot = $this->castingLot();

        $this->post(route('quick-qc.store'), ['rows' => [$lot->product_revision_id => ['accepted' => 4, 'rejected' => 2]]])->assertSessionHasErrors('quick');

        $this->assertSame(0, QualityInspection::count());
        $this->assertSame(0, $lot->fresh()->quantity_rejected);
    }

    public function test_a_clean_tally_makes_the_lot_ready_and_a_checked_piece_is_not_offered_again(): void
    {
        $lot = $this->castingLot();

        $this->post(route('quick-qc.store'), ['rows' => [$lot->product_revision_id => ['accepted' => 5]]])->assertSessionHasNoErrors();

        $this->assertSame('READY', $lot->fresh()->status);
        $this->assertSame(0, NonconformanceReport::count());
        $this->get(route('quick-qc.index'))->assertSee('No castings are waiting');
    }

    public function test_the_nonconformance_threshold_decides_whether_a_ncr_is_raised(): void
    {
        $lot = $this->castingLot();
        $lot->update(['quantity_poured' => 100, 'quantity_good' => 100]);
        ProcessDepth::apply('BASIC');
        $this->assertSame('OVER_5', QualityRules::get('ncr_threshold'));

        $this->post(route('quick-qc.store'), ['rows' => [$lot->product_revision_id => ['accepted' => 48, 'rejected' => 2]]])->assertSessionHasNoErrors();
        $this->assertSame(0, NonconformanceReport::count());   // 4% is under the limit
        $this->assertSame(2, $lot->fresh()->quantity_rejected);

        $this->post(route('quick-qc.store'), ['rows' => [$lot->product_revision_id => ['accepted' => 40, 'rejected' => 10]]])->assertSessionHasNoErrors();
        $this->assertSame(1, NonconformanceReport::count());   // 20% is over it
    }

    public function test_defects_are_marked_by_cavity_and_where_shared_over_the_lots_with_the_rejects_and_rework(): void
    {
        $first = $this->castingLot();
        $second = $this->secondLotOfSameProduct($first);
        $plate = $first->mouldAssemblies()->first()->mouldingRun->patternRevision;
        $plate->cavityMap()->create(['cavity_number' => 2, 'product_revision_id' => $first->product_revision_id]);
        [$coldShut, $sand] = [QualityDefect::where('code', 'COLD-SHUT')->firstOrFail(), QualityDefect::where('code', 'SAND-INCL')->firstOrFail()];
        $this->get(route('quick-qc.index'))->assertOk()->assertSee($plate->pattern->code.' cavity 2');

        $row = fn (array $marks) => ['rows' => [$first->product_revision_id => ['accepted' => 4, 'rework' => 2, 'rejected' => 4, 'marks' => $marks]]];
        $this->post(route('quick-qc.store'), $row([['defect_id' => $coldShut->id, 'quantity' => 5, 'disposition' => 'REJECT']]))->assertSessionHasErrors('quick');
        $this->post(route('quick-qc.store'), $row([
            ['defect_id' => $coldShut->id, 'quantity' => 3, 'disposition' => 'REJECT', 'cavity' => $plate->id.':2', 'location' => 'flange edge'],
            ['defect_id' => $sand->id, 'quantity' => 1, 'disposition' => 'REJECT'],
            ['defect_id' => $coldShut->id, 'quantity' => 2, 'disposition' => 'REWORK', 'cavity' => $plate->id.':2'],
            ['defect_id' => '', 'quantity' => ''],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, (int) QualityDefectMark::where('casting_lot_id', $first->id)->sum('quantity'), 'the first lot took 4 good and 1 rework');
        $this->assertSame(5, (int) QualityDefectMark::where('casting_lot_id', $second->id)->sum('quantity'));
        $mark = QualityDefectMark::where('location', 'flange edge')->sole();
        $this->assertSame([3, 2, 'REJECT'], [$mark->quantity, $mark->cavity_number, $mark->disposition]);
        $this->get(route('quick-qc.index'))->assertSee('Defects by cavity')->assertSee($plate->pattern->code.' cavity 2');
    }

    public function test_piece_ids_are_required_on_each_defect_line_when_the_rule_says_so(): void
    {
        $lot = $this->castingLot();
        $defect = QualityDefect::where('code', 'COLD-SHUT')->firstOrFail();
        QualityRules::set('defect_piece_ids', 'REQUIRED');
        $post = fn (string $ids) => $this->post(route('quick-qc.store'), ['rows' => [$lot->product_revision_id => ['accepted' => 3, 'rejected' => 2,
            'marks' => [['defect_id' => $defect->id, 'quantity' => 2, 'disposition' => 'REJECT', 'piece_ids' => $ids]]]]]);

        $post('A-11')->assertSessionHasErrors('quick');
        $post('A-11, A-14')->assertSessionHasNoErrors();
        $this->assertSame('A-11, A-14', QualityDefectMark::sole()->piece_ids);
    }
}
