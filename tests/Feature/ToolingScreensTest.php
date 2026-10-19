<?php

namespace Tests\Feature;

use App\Domains\Maintenance\Models\Gauge;
use App\Domains\Tooling\Models\ToolingLifeLimit;
use App\Domains\Tooling\Models\ToolingMaintenanceRecord;
use App\Models\Pattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolingScreensTest extends TestCase
{
    use RefreshDatabase;

    private function pattern(string $code = 'MP-1'): Pattern
    {
        return Pattern::create(['code' => $code, 'name' => 'Plate '.$code, 'pattern_type' => 'MATCHPLATE']);
    }

    /** @return array<string, mixed> */
    private function record(Pattern $pattern, array $over = []): array
    {
        return $over + ['tooling_type' => 'PATTERN', 'pattern_id' => $pattern->id, 'activity_type' => 'REPAIR', 'status' => 'OPEN', 'priority' => 'NORMAL', 'issue_description' => 'Worn locating pin'];
    }

    public function test_a_maintenance_record_is_created_against_exactly_one_tooling_item(): void
    {
        $pattern = $this->pattern();

        $this->get(route('tooling-maintenance.create'))->assertOk();
        $this->post(route('tooling-maintenance.store'), $this->record($pattern, ['pattern_id' => null]))->assertSessionHasErrors();
        $this->post(route('tooling-maintenance.store'), $this->record($pattern, ['pattern_revision_id' => 'not-a-uuid']))->assertSessionHasErrors();
        $this->assertSame(0, ToolingMaintenanceRecord::count());

        $this->post(route('tooling-maintenance.store'), $this->record($pattern))->assertSessionHasNoErrors();
        $record = ToolingMaintenanceRecord::sole();
        $this->assertStringStartsWith('TM-'.now()->format('Ymd'), $record->record_number);
        $this->get(route('tooling-maintenance.index'))->assertOk()->assertSee($record->record_number);
    }

    public function test_completing_a_maintenance_record_stamps_the_completion_time(): void
    {
        $pattern = $this->pattern();
        $this->post(route('tooling-maintenance.store'), $this->record($pattern));
        $record = ToolingMaintenanceRecord::sole();

        $this->get(route('tooling-maintenance.edit', $record))->assertOk();
        $this->put(route('tooling-maintenance.update', $record), $this->record($pattern, ['status' => 'COMPLETED']))->assertSessionHasNoErrors();

        $this->assertSame('COMPLETED', $record->fresh()->status);
        $this->assertNotNull($record->fresh()->completed_at);
        $this->put(route('tooling-maintenance.update', $record), $this->record($pattern, ['activity_type' => 'WELDING']))->assertSessionHasErrors('activity_type');
    }

    public function test_a_tooling_item_gets_one_life_limit_and_keeps_its_identity_when_edited(): void
    {
        $pattern = $this->pattern();
        $form = ['tooling_type' => 'PATTERN', 'tooling_id' => $pattern->id, 'usage_unit' => 'moulds', 'service_life_quantity' => 5000, 'warning_threshold_percent' => 80, 'is_active' => 1];

        $this->get(route('tooling-life.create'))->assertOk();
        $this->post(route('tooling-life.store'), $form)->assertRedirect(route('tooling-life.dashboard'));
        $limit = ToolingLifeLimit::sole();
        $this->assertSame($pattern->id, $limit->pattern_id);

        $this->post(route('tooling-life.store'), $form)->assertSessionHasErrors('tooling_id');      // already has one
        $this->post(route('tooling-life.store'), ['tooling_id' => $this->pattern('MP-2')->id, 'warning_threshold_percent' => 150] + $form)->assertSessionHasErrors('warning_threshold_percent');

        $this->get(route('tooling-life.edit', $limit))->assertOk();
        $this->put(route('tooling-life.update', $limit), ['tooling_id' => $this->pattern('MP-3')->id, 'service_life_quantity' => 8000] + $form)->assertSessionHasNoErrors();
        $this->assertSame($pattern->id, $limit->fresh()->pattern_id);                                  // identity is fixed once created
        $this->assertEquals(8000, $limit->fresh()->service_life_quantity);
        $this->get(route('tooling-life.dashboard'))->assertOk();
    }

    public function test_a_gauge_is_added_edited_and_needs_an_interval_when_calibration_is_required(): void
    {
        $form = ['name' => 'Vernier 150', 'instrument_type' => 'CALIPER', 'status' => 'IN_SERVICE', 'requires_calibration' => 1, 'calibration_interval_days' => 365, 'code' => 'cal-1'];

        $this->get(route('gauges.create'))->assertOk();
        $this->post(route('gauges.store'), ['calibration_interval_days' => null] + $form)->assertSessionHasErrors('calibration_interval_days');
        $this->post(route('gauges.store'), $form)->assertSessionHasNoErrors();
        $gauge = Gauge::sole();
        $this->assertSame('CAL-1', $gauge->code);

        $this->get(route('gauges.edit', $gauge))->assertOk();
        $this->put(route('gauges.update', $gauge), ['location' => 'Lab'] + $form)->assertSessionHasNoErrors();
        $this->assertSame('Lab', $gauge->fresh()->location);

        $this->post(route('gauges.store'), ['name' => 'Other'] + $form)->assertSessionHasErrors('code');
        $this->put(route('gauges.update', $gauge), ['instrument_type' => 'RULER'] + $form)->assertSessionHasErrors('instrument_type');
    }
}
