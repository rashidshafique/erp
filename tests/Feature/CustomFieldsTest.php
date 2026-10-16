<?php

namespace Tests\Feature;

use App\Models\CustomFieldDefinition;
use App\Models\Furnace;
use App\Models\Heat;
use App\Services\CustomFields\CustomFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function heat(): Heat
    {
        $f = Furnace::create(['code' => 'IF-1', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create(['heat_number' => 'H-CF1', 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED']);
    }

    private function define(array $over): void
    {
        $this->post(route('custom-fields.store'), $over + ['entity_type' => 'heat', 'data_type' => 'TEXT'])->assertSessionHasNoErrors();
    }

    public function test_define_fill_validate_and_query(): void
    {
        $heat = $this->heat();
        $this->get(route('custom-fields.index'))->assertOk();
        $this->define(['label' => 'Ladle number', 'data_type' => 'TEXT']);
        $this->define(['label' => 'Charge weight check', 'data_type' => 'NUMBER', 'unit' => 'kg', 'min_value' => 0, 'max_value' => 5000]);
        $this->define(['label' => 'Bath colour', 'data_type' => 'SELECT', 'options_text' => "Bright\nDull"]);
        $this->define(['label' => 'Sample kept', 'data_type' => 'BOOLEAN', 'is_required' => 1]);
        $this->assertSame('ladle_number', CustomFieldDefinition::orderBy('created_at')->first()->field_key);

        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Additional information')->assertSee('Ladle number');
        $url = route('custom-fields.values', ['heat', $heat->id]);
        $this->post($url, ['cf' => ['ladle_number' => 'L-7']])->assertSessionHasErrors('cf.sample_kept');                                 // required
        $this->post($url, ['cf' => ['sample_kept' => '1', 'charge_weight_check' => '9000']])->assertSessionHasErrors('cf.charge_weight_check');
        $this->post($url, ['cf' => ['sample_kept' => '1', 'bath_colour' => 'Purple']])->assertSessionHasErrors('cf.bath_colour');
        $this->post($url, ['cf' => ['sample_kept' => '1', 'ladle_number' => 'L-7', 'charge_weight_check' => '1250', 'bath_colour' => 'Bright']])->assertSessionHasNoErrors();

        $v = app(CustomFields::class)->values('heat', $heat->id);
        $this->assertSame('L-7', $v['ladle_number']);
        $this->assertEquals(1250, $v['charge_weight_check']);
        $this->assertTrue($v['sample_kept']);
        $this->assertSame(1, DB::table('heats')->whereRaw("custom_fields->>'ladle_number' = ?", ['L-7'])->count());   // queryable
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('L-7');

        // Deactivating a field hides it but keeps what was stored
        $def = CustomFieldDefinition::where('field_key', 'ladle_number')->first();
        $this->put(route('custom-fields.update', $def), ['label' => 'Ladle number', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->post($url, ['cf' => ['sample_kept' => '1']])->assertSessionHasNoErrors();
        $this->assertSame('L-7', app(CustomFields::class)->values('heat', $heat->id)['ladle_number']);
    }

    public function test_duplicates_and_unknown_objects_are_refused(): void
    {
        $this->define(['label' => 'Drawing ref']);
        $this->post(route('custom-fields.store'), ['entity_type' => 'heat', 'label' => 'Drawing ref', 'data_type' => 'TEXT'])->assertSessionHasErrors('label');
        $this->post(route('custom-fields.store'), ['entity_type' => 'heat', 'label' => 'Colour', 'data_type' => 'SELECT'])->assertSessionHasErrors('options_text');
        $this->post(route('custom-fields.values', ['nonsense', '00000000-0000-0000-0000-000000000000']), [])->assertNotFound();
    }
}
