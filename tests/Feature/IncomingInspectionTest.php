<?php

namespace Tests\Feature;

use App\Domains\Purchasing\Models\IncomingInspection;
use App\Domains\Purchasing\Support\IncomingRules;
use App\Models\Attachment;
use App\Models\Material;
use App\Models\MaterialLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomingInspectionTest extends TestCase
{
    use RefreshDatabase;

    private function material(): Material
    {
        $material = Material::create(['code' => 'PIG', 'name' => 'Pig iron', 'material_type' => 'PIG_IRON', 'base_uom' => 'kg', 'status' => 'ACTIVE']);
        $this->put(route('incoming.plan.update', $material), ['items' => [
            ['characteristic' => 'Carbon', 'min_value' => 3.5, 'max_value' => 4.2, 'unit' => '%'],
            ['characteristic' => '', 'min_value' => 1],
        ]])->assertSessionHasNoErrors();

        return $material;
    }

    private function lot(Material $material, array $extra = []): MaterialLot
    {
        return $material->lots()->create($extra + ['lot_number' => 'L-'.uniqid(), 'status' => 'QUARANTINE', 'received_qty' => 100, 'accepted_qty' => 0, 'currency_code' => 'PKR']);
    }

    private function inspect(MaterialLot $lot, float $carbon, array $extra = [])
    {
        $item = $lot->material->inspectionItems()->firstOrFail();

        return $this->post(route('incoming.inspections.store', $lot), $extra + ['inspected_on' => today()->toDateString(), 'values' => [$item->id => $carbon]]);
    }

    public function test_the_plan_drops_blank_rows_and_rejects_limits_that_are_reversed(): void
    {
        $material = $this->material();
        $this->assertSame(['Carbon'], $material->inspectionItems->pluck('characteristic')->all());

        $this->put(route('incoming.plan.update', $material), ['items' => [['characteristic' => 'Carbon', 'min_value' => 5, 'max_value' => 4]]])->assertSessionHasErrors('items.0.max_value');
        $this->assertCount(1, $material->inspectionItems()->get());
    }

    public function test_an_inspection_passes_or_fails_against_the_limits_and_keeps_them_as_recorded(): void
    {
        $lot = $this->lot($this->material());

        $this->inspect($lot, 3.9)->assertSessionHasNoErrors();
        $this->inspect($lot, 4.8)->assertSessionHasNoErrors();

        $this->assertSame(['PASS', 'FAIL'], IncomingInspection::orderBy('created_at')->pluck('result')->all());
        $lot->material->inspectionItems()->update(['max_value' => 9]);
        $this->assertSame('4.2000', IncomingInspection::first()->results->first()->max_value);
    }

    public function test_a_concession_needs_a_reason(): void
    {
        $lot = $this->lot($this->material());

        $this->inspect($lot, 4.8, ['concession' => 1])->assertSessionHasErrors('notes');
        $this->inspect($lot, 4.8, ['concession' => 1, 'notes' => 'Customer agreed'])->assertSessionHasNoErrors();
        $this->assertSame('CONCESSION', IncomingInspection::firstOrFail()->result);
    }

    public function test_a_lot_is_accepted_without_checks_while_the_rules_are_off(): void
    {
        $lot = $this->lot($this->material());

        $this->post(route('incoming.lots.accept', $lot), ['accepted_qty' => 90])->assertSessionHasNoErrors();

        $this->assertSame(['ACCEPTED', '90.000'], [$lot->refresh()->status, (string) $lot->accepted_qty]);
    }

    public function test_acceptance_waits_for_a_certificate_of_the_required_type_and_a_passed_inspection(): void
    {
        IncomingRules::set('certificate', 'EN10204_31');
        IncomingRules::set('inspection', 'REQUIRED');
        $lot = $this->lot($this->material());
        $accept = fn () => $this->post(route('incoming.lots.accept', $lot), ['accepted_qty' => 90]);

        $accept()->assertSessionHasErrors('accepted_qty');

        Attachment::forceCreate(['attachable_type' => 'material_lot', 'attachable_id' => $lot->id, 'category' => 'CERTIFICATE', 'original_name' => 'cert.pdf', 'disk' => 'local', 'path' => 'x', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64)]);
        $this->put(route('incoming.lots.certificate-type', $lot), ['certificate_type' => 'EN10204_22']);
        $this->inspect($lot, 3.9);
        $accept()->assertSessionHasErrors('accepted_qty');

        $this->put(route('incoming.lots.certificate-type', $lot), ['certificate_type' => 'EN10204_31']);
        $accept()->assertSessionHasNoErrors();
        $this->assertSame('ACCEPTED', $lot->refresh()->status);
    }

    public function test_a_failed_latest_inspection_blocks_acceptance_until_inspected_again(): void
    {
        IncomingRules::set('inspection', 'REQUIRED');
        $lot = $this->lot($this->material());

        $this->inspect($lot, 4.8);
        $this->post(route('incoming.lots.accept', $lot), ['accepted_qty' => 90])->assertSessionHasErrors('accepted_qty');
        $this->assertSame('QUARANTINE', $lot->refresh()->status);

        $this->travel(1)->minutes();
        $this->inspect($lot, 3.9);
        $this->post(route('incoming.lots.accept', $lot), ['accepted_qty' => 90])->assertSessionHasNoErrors();
        $this->assertSame('ACCEPTED', $lot->refresh()->status);
    }

    public function test_a_lot_cannot_be_recorded_as_accepted_when_the_rules_are_not_met(): void
    {
        IncomingRules::set('inspection', 'REQUIRED');
        $material = $this->material();

        $this->post(route('materials.lots.store', $material), ['lot_number' => 'L-1', 'status' => 'ACCEPTED', 'received_qty' => 10, 'accepted_qty' => 10, 'currency_code' => 'PKR'])->assertSessionHasErrors('status');
        $this->assertSame(0, MaterialLot::count());
    }

    public function test_the_rules_are_saved_from_settings(): void
    {
        $this->put(route('incoming.settings.update'), ['certificate' => 'ATTACHED', 'inspection' => 'REQUIRED'])->assertSessionHasNoErrors();

        $this->assertSame(['ATTACHED', 'REQUIRED'], [IncomingRules::get('certificate'), IncomingRules::get('inspection')]);
        $this->put(route('incoming.settings.update'), ['certificate' => 'BOGUS', 'inspection' => 'OFF'])->assertSessionHasErrors('certificate');
    }

    public function test_the_lot_page_and_settings_page_render(): void
    {
        $lot = $this->lot($this->material());

        $this->get(route('incoming.lots.show', $lot))->assertOk()->assertSee('Record an inspection');
        $this->get(route('incoming.plan.edit', $lot->material))->assertOk();
        $this->get(route('incoming.settings.edit'))->assertOk();
    }
}
