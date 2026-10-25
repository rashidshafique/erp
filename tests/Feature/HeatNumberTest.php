<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Services\Production\HeatNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HeatNumberTest extends TestCase
{
    use RefreshDatabase;

    private function heat(string $number): Heat
    {
        $furnace = Furnace::first() ?? Furnace::create(['code' => 'IF-1', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create(['heat_number' => $number, 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED']);
    }

    private function next(string $day): string
    {
        return DB::transaction(fn () => app(HeatNumber::class)->next(now()->parse($day)));
    }

    public function test_the_first_heat_of_a_day_is_h_yymm_dd_and_the_others_count_up_from_1(): void
    {
        $this->assertSame('H-2610-25', $this->next('2026-10-25'));
        $this->heat('H-2610-25');
        $this->assertSame('H-2610-25-1', $this->next('2026-10-25'));
        $this->heat('H-2610-25-1');
        $this->assertSame('H-2610-25-2', $this->next('2026-10-25'));

        $this->assertSame('H-2610-26', $this->next('2026-10-26'), 'Another day starts again.');
        $this->assertSame('H-2701-05', $this->next('2027-01-05'), 'Two-digit year, then month, then day.');
    }

    public function test_an_older_numbered_heat_counts_as_a_heat_of_its_day(): void
    {
        $this->heat('H-20261025-001');

        $this->assertSame('H-2610-25-1', $this->next('2026-10-25'));
        $this->assertSame('H-2610-24', $this->next('2026-10-24'));
    }

    public function test_the_short_number_reads_both_forms(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 25));

        $this->assertSame(['#001', '#003', '24/10 #002', '19/10 #012', 'TRIAL-1'], array_map(fn ($n) => (new Heat(['heat_number' => $n]))->shortNumber(),
            ['H-2610-25', 'H-2610-25-2', 'H-2610-24-1', 'H-20261019-012', 'TRIAL-1']));
    }

    public function test_heats_planned_for_a_day_get_the_new_numbers(): void
    {
        $furnace = Furnace::create(['code' => 'IF-9', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $grade = \App\Models\IronGrade::where('code', 'EN-GJS-500-7')->firstOrFail();
        $planner = app(\App\Services\Planning\HeatPlanner::class);
        $day = now()->setDate(2026, 10, 25);

        $numbers = DB::transaction(fn () => [
            $planner->startHeat($furnace, $grade, 500, $day)->heat_number,
            $planner->startHeat($furnace, $grade, 500, $day)->heat_number,
            $planner->startHeat($furnace, $grade, 500, $day)->heat_number,
        ]);

        $this->assertSame(['H-2610-25', 'H-2610-25-1', 'H-2610-25-2'], $numbers);
    }
}
