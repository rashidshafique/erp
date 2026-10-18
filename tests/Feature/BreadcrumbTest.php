<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreadcrumbTest extends TestCase
{
    use RefreshDatabase;

    private function pattern(): Pattern
    {
        $pattern = Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE']);
        PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'DRAFT', 'is_current' => true]);

        return $pattern;
    }

    public function test_the_trail_no_longer_starts_with_operations(): void
    {
        $this->get(route('patterns.index'))->assertOk()->assertDontSee('Operations /', false);
    }

    public function test_a_detail_page_links_its_list_and_has_a_back_button_to_it(): void
    {
        $pattern = $this->pattern();

        $this->get(route('patterns.show', $pattern))->assertOk()
            ->assertSee('<a href="'.route('patterns.index').'">Patterns</a> / '.$pattern->code, false)
            ->assertSee('id="crumb-back" href="'.route('patterns.index').'"', false);
    }

    public function test_a_sub_screen_links_every_level_and_goes_back_to_the_parent(): void
    {
        $pattern = $this->pattern();

        $this->get(route('patterns.edit', $pattern))->assertOk()
            ->assertSee('<a href="'.route('patterns.show', $pattern).'">'.$pattern->code.'</a> / Edit', false)
            ->assertSee('id="crumb-back" href="'.route('patterns.show', $pattern).'"', false);
    }

    public function test_a_list_page_has_no_back_button(): void
    {
        $this->get(route('patterns.index'))->assertDontSee('id="crumb-back"', false);
    }
}
