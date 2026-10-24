<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\ProductionStageDefinition;
use App\Models\QualityInspection;
use App\Models\Role;
use App\Models\User;
use App\Services\Production\LotService;
use App\Services\Quality\PendingInspections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class PendingInspectionsTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    private function user(string $roleCode): User
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function throughLastStage(CastingLot $lot): void
    {
        $service = app(LotService::class);
        foreach (ProductionStageDefinition::where('is_active', true)->orderBy('sequence')->get() as $stage) {
            $service->recordStage($lot, $stage, ['quantity_in' => 5, 'quantity_accepted' => 5, 'quantity_rejected' => 0]);
        }
    }

    public function test_a_lot_through_its_last_stage_counts_as_waiting_and_tells_the_inspectors_once(): void
    {
        $inspector = $this->user('QUALITY_INSPECTOR');
        $other = $this->user('VIEWER');
        $lot = $this->castingLot();
        $pending = app(PendingInspections::class);

        $first = ProductionStageDefinition::where('is_active', true)->orderBy('sequence')->first();
        app(LotService::class)->recordStage($lot, $first, ['quantity_in' => 5, 'quantity_accepted' => 5, 'quantity_rejected' => 0]);
        $this->assertSame(0, $pending->count());
        $this->assertSame(0, $inspector->notifications()->count());

        $this->throughLastStage($lot->fresh());

        $this->assertSame(1, $pending->count());
        $this->assertSame(1, $inspector->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $notice = $inspector->notifications()->first()->data;
        $this->assertSame('inspection', $notice['kind']);
        $this->assertSame(route('lots.inspections.create', $lot), $notice['url']);

        $this->assertFalse($pending->announce($lot), 'one notice per lot');
        $this->assertSame(1, $inspector->notifications()->count());
    }

    public function test_starting_the_final_inspection_takes_the_lot_off_the_waiting_list_and_the_open_inspection_counts_instead(): void
    {
        $lot = $this->castingLot();
        $this->throughLastStage($lot);
        $pending = app(PendingInspections::class);
        $this->assertSame(1, $pending->awaitingLots()->count());

        QualityInspection::create(['inspection_number' => 'QI-1', 'casting_lot_id' => $lot->id, 'inspection_type' => 'FINAL', 'status' => 'IN_PROGRESS', 'sample_size' => 5, 'quantity_inspected' => 5, 'quantity_pending' => 5]);

        $this->assertSame(0, $pending->awaitingLots()->count());
        $this->assertSame(1, $pending->inProgress());
        $this->assertSame(1, $pending->count());
    }

    public function test_the_inspections_menu_shows_the_count_and_the_page_lists_the_waiting_lots(): void
    {
        $lot = $this->castingLot();
        $this->throughLastStage($lot);

        $page = $this->get(route('inspections.index'))->assertOk();

        $page->assertSee('class="nav-count"', false)->assertSee('Waiting for inspection')->assertSee(route('lots.inspections.create', $lot), false);
    }

    public function test_no_count_is_shown_when_nothing_is_waiting(): void
    {
        $this->get(route('inspections.index'))->assertOk()->assertDontSee('class="nav-count"', false);
    }
}
