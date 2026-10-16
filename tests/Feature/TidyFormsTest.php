<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TidyFormsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Support\UiSettings::flush();
    }

    public function test_form_styling_is_on_by_default_and_can_be_switched_off(): void
    {
        $this->get(route('patterns.create'))->assertOk()->assertSee('data-tidy-forms="1"', false)->assertSee('data-progressive', false);
        $this->put(route('settings.display.update'), ['density' => 'COMPACT'])->assertSessionHasNoErrors();   // box unticked
        $this->get(route('patterns.create'))->assertOk()->assertSee('data-tidy-forms="0"', false);
        $this->put(route('settings.display.update'), ['density' => 'COMPACT', 'tidy_forms' => 1]);
        $this->get(route('patterns.create'))->assertSee('data-tidy-forms="1"', false);
    }

    public function test_heavy_forms_render(): void
    {
        foreach (['products.create', 'customers.create', 'enquiries.create', 'sales-orders.create', 'settings.display'] as $r) {
            $this->get(route($r))->assertOk();
        }
    }
}
