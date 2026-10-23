<?php

namespace Tests\Feature;

use App\Models\ProductionStageDefinition;
use App\Services\Production\LotService;
use App\Services\Production\ProcessRoutes;
use App\Support\QueueRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class ProcessRouteTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    private function stage(string $code): ProductionStageDefinition
    {
        return ProductionStageDefinition::where('code', $code)->firstOrFail();
    }

    /** @return list<string> */
    private function routeCodes(): array
    {
        return app(ProcessRoutes::class)->defaultSteps()->map(fn ($step) => $step->stage->code)->all();
    }

    public function test_the_default_route_starts_as_the_present_stages_in_their_order(): void
    {
        $this->assertSame(['SHAKEOUT', 'CUTTING', 'SHOT_BLAST', 'FETTLING', 'HEAT_TREATMENT', 'MACHINING', 'COATING', 'FINAL_INSPECTION'], $this->routeCodes());
        $this->assertTrue($this->stage('SHAKEOUT')->knocks_out_mould);
        $this->assertSame('INSPECTION', $this->stage('FINAL_INSPECTION')->kind);
        $this->get(route('settings.process'))->assertOk()->assertSee('Our process')->assertSee('Gate and riser removal');
    }

    public function test_moving_and_taking_off_steps_changes_the_order_the_queue_follows(): void
    {
        $page = Livewire::test('process-route')->call('move', 2, -1);   // shot blasting before gate and riser removal
        $this->assertSame(['SHAKEOUT', 'SHOT_BLAST', 'CUTTING'], array_slice($this->routeCodes(), 0, 3));
        $this->assertSame(['waiting', 'after-SHAKEOUT', 'after-SHOT_BLAST', 'after-CUTTING'], array_slice(array_keys(QueueRules::steps()), 0, 4));

        $page->call('takeOff', $this->stage('MACHINING')->id)->assertSee('Machining taken off the process');
        $this->assertNotContains('MACHINING', $this->routeCodes());
        $this->assertFalse($this->stage('MACHINING')->is_active);
        $this->assertArrayNotHasKey('after-MACHINING', QueueRules::steps());

        $page->call('putBack', $this->stage('MACHINING')->id);
        $this->assertSame('MACHINING', last($this->routeCodes()));
        $this->assertTrue($this->stage('MACHINING')->is_active);
    }

    public function test_a_jobbing_foundry_runs_fettle_then_weigh_with_a_step_of_its_own(): void
    {
        $routes = app(ProcessRoutes::class);
        $routes->saveDefault([$this->stage('FETTLING')->id]);

        Livewire::test('process-route')->call('create')
            ->set('form.name', 'Weighing')->set('form.kind', 'WEIGH')->set('form.records_weight', true)->call('save')->assertHasNoErrors()
            ->call('create')->set('form.name', 'weighing')->call('save')->assertHasErrors('form.name');   // names are unique whatever the case

        $this->assertSame(['FETTLING', 'WEIGHING'], $this->routeCodes());
        $this->assertTrue($this->stage('WEIGHING')->records_weight);
        $this->assertSame(['waiting', 'after-FETTLING', 'after-WEIGHING', 'ready'], array_keys(QueueRules::steps()));
        $this->assertSame('Waiting for fettling and grinding', QueueRules::steps()['waiting']);
        $this->get(route('lots.queue'))->assertOk();
    }

    public function test_the_mould_knock_out_behaviour_follows_the_step_not_its_code(): void
    {
        $lot = $this->castingLot();
        DB::table('pours')->where('id', $lot->pourMouldAssembly->pour_id)->update(['completed_at' => now()]);
        DB::table('pattern_revisions')->update(['min_cooling_minutes' => 60]);
        $knockOut = app(ProcessRoutes::class)->addStep(['name' => 'Knock-out', 'kind' => 'PROCESS', 'knocks_out_mould' => true]);
        $this->stage('SHAKEOUT')->update(['knocks_out_mould' => false]);
        $in = ['quantity_in' => 5, 'quantity_accepted' => 5, 'quantity_rejected' => 0];

        app(LotService::class)->recordStage($lot, $this->stage('SHAKEOUT'), $in);   // no longer checks cooling

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('of cooling');
        app(LotService::class)->recordStage($lot, $knockOut, $in);
    }

    public function test_quality_notes_show_where_the_step_is_recorded(): void
    {
        $fettling = $this->stage('FETTLING');
        Livewire::test('process-route')->call('edit', $fettling->id)
            ->set('form.quality_notes', 'Gate stubs ground flush')->set('form.description', '  ')->call('save')->assertHasNoErrors()
            ->assertSee('Gate stubs ground flush');

        $this->assertNull($fettling->fresh()->description);   // blank text is not kept
        $this->get(route('lots.show', $this->castingLot()))->assertOk()->assertSee('data-check="Gate stubs ground flush"', false);
    }

    public function test_the_traveller_lists_the_route_and_fills_in_what_is_recorded(): void
    {
        $lot = $this->castingLot();
        $service = app(LotService::class);
        $service->recordStage($lot, $this->stage('CUTTING'), ['quantity_in' => 5, 'quantity_accepted' => 4, 'quantity_rejected' => 1, 'rejection_reason' => 'Crack', 'operator_name' => 'Asif']);
        $service->recordStage($lot, $this->stage('MACHINING'), ['quantity_in' => 4, 'quantity_accepted' => 4, 'quantity_rejected' => 0]);
        app(ProcessRoutes::class)->saveDefault([$this->stage('CUTTING')->id, $this->stage('FETTLING')->id]);   // machining taken off after the lot passed it
        $this->stage('FETTLING')->update(['quality_notes' => 'No cracks at the gate']);

        $this->get(route('lots.traveller', $lot))->assertOk()
            ->assertSeeInOrder(['Gate and riser removal', '5', '4', '1', 'Crack', 'Asif', 'Fettling', 'No cracks at the gate', 'Machining', 'recorded, not on the process'])
            ->assertDontSee('Shot blasting');
    }

    public function test_a_step_cannot_be_on_the_route_twice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ProcessRoutes::class)->saveDefault([$this->stage('FETTLING')->id, $this->stage('FETTLING')->id]);
    }
}
