<?php

namespace Tests\Feature;

use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\Pattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolingListTest extends TestCase
{
    use RefreshDatabase;

    private function tooling(): void
    {
        Pattern::create(['code' => 'P-0123', 'name' => 'Flange plate', 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
        CoreBox::create(['core_definition_id' => CoreDefinition::create(['name' => 'Valve core', 'core_type' => 'STANDARD'])->id, 'name' => 'Valve core box']);
    }

    public function test_the_pattern_list_shows_patterns_and_core_boxes_together_and_filters_by_type(): void
    {
        $this->tooling();

        $this->get(route('patterns.index'))->assertOk()->assertSee('Flange plate')->assertSee('Valve core box');
        $this->get(route('patterns.index', ['type' => 'pattern']))->assertOk()->assertSee('Flange plate')->assertDontSee('Valve core box');
        $this->get(route('patterns.index', ['type' => 'core_box']))->assertOk()->assertSee('Valve core box')->assertDontSee('Flange plate');
    }

    public function test_search_covers_both_and_pattern_only_filters_leave_core_boxes_out(): void
    {
        $this->tooling();

        $this->get(route('patterns.index', ['search' => 'valve']))->assertOk()->assertSee('Valve core box')->assertDontSee('Flange plate');
        $this->get(route('patterns.index', ['owner' => 'FOUNDRY']))->assertOk()->assertSee('Flange plate')->assertDontSee('Valve core box');
    }
}
