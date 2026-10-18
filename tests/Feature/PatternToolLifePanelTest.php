<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\ToolingLifeLimit;
use App\Models\ToolingMaintenanceRecord;
use App\Models\ToolingUsageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternToolLifePanelTest extends TestCase
{
    use RefreshDatabase;

    private function pattern(): Pattern
    {
        $pattern = Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE']);
        PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'is_current' => true]);

        return $pattern;
    }

    public function test_a_pattern_without_a_life_limit_says_none_is_set(): void
    {
        $this->get(route('patterns.show', $this->pattern()))->assertOk()->assertSee('Life &amp; maintenance', false)->assertSee('No service-life limit is set');
    }

    public function test_usage_against_the_rated_life_and_the_next_inspection_are_shown(): void
    {
        $pattern = $this->pattern();
        ToolingLifeLimit::create(['tooling_type' => 'PATTERN', 'pattern_id' => $pattern->id, 'usage_unit' => 'CYCLES', 'service_life_quantity' => 1000, 'warning_threshold_percent' => 80, 'is_active' => true]);
        ToolingUsageRecord::create(['tooling_type' => 'PATTERN', 'pattern_id' => $pattern->id, 'usage_unit' => 'CYCLES', 'usage_quantity' => 850, 'used_at' => now()]);
        ToolingMaintenanceRecord::create(['tooling_type' => 'PATTERN', 'pattern_id' => $pattern->id, 'record_number' => 'TM-1', 'activity_type' => 'INSPECTION', 'status' => 'PLANNED', 'priority' => 'NORMAL', 'reported_at' => now(), 'next_inspection_due_on' => now()->subDays(3)]);

        $this->get(route('patterns.show', $pattern))->assertOk()
            ->assertSee('850 / 1,000 cycles', false)->assertSee('Approaching limit')->assertSee('overdue');
    }
}
