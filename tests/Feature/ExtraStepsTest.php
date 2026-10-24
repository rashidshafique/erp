<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductionStageDefinition;
use App\Models\ProductRevision;
use App\Services\Production\ProcessRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class ExtraStepsTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    private function stage(string $code): ProductionStageDefinition
    {
        return ProductionStageDefinition::where('code', $code)->firstOrFail();
    }

    public function test_a_product_specific_step_applies_only_to_revisions_that_were_given_it(): void
    {
        $lot = $this->castingLot();
        $this->stage('MACHINING')->update(['product_specific' => true]);

        $this->assertNotContains('MACHINING', ProductionStageDefinition::applicableTo($lot->product_revision_id)->pluck('code')->all());

        $lot->productRevision->extraSteps()->attach($this->stage('MACHINING')->id);

        $this->assertContains('MACHINING', ProductionStageDefinition::applicableTo($lot->product_revision_id)->pluck('code')->all());
    }

    public function test_the_lot_page_and_traveller_leave_out_steps_the_product_does_not_need(): void
    {
        $lot = $this->castingLot();
        $this->stage('MACHINING')->update(['product_specific' => true]);

        $this->get(route('lots.traveller', $lot))->assertOk()->assertDontSee('Machining');

        $lot->productRevision->extraSteps()->attach($this->stage('MACHINING')->id);

        $this->get(route('lots.traveller', $lot))->assertOk()->assertSee('Machining');
    }

    public function test_ticking_extra_steps_on_the_product_page_and_a_new_revision_copies_them(): void
    {
        $this->stage('MACHINING')->update(['product_specific' => true]);
        $product = Product::create(['code' => 'P-'.uniqid(), 'name' => 'Valve body']);
        $first = ProductRevision::create(['product_id' => $product->id, 'revision' => '0', 'status' => 'APPROVED']);

        $this->get(route('products.show', $product))->assertOk()->assertSee('Extra steps');
        $this->put(route('products.revisions.steps', [$product, $first]), ['steps' => [$this->stage('MACHINING')->id]])->assertSessionHasNoErrors();
        $this->assertSame(['MACHINING'], $first->extraSteps()->pluck('code')->all());

        $second = ProductRevision::create(['product_id' => $product->id, 'revision' => '1', 'status' => 'APPROVED']);
        $this->assertSame(['MACHINING'], $second->extraSteps()->pluck('code')->all());
    }

    public function test_only_steps_marked_product_specific_can_be_ticked(): void
    {
        $product = Product::create(['code' => 'P-'.uniqid(), 'name' => 'Valve body']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => '0', 'status' => 'APPROVED']);

        $this->put(route('products.revisions.steps', [$product, $revision]), ['steps' => [$this->stage('FETTLING')->id]])->assertSessionHasErrors('steps.0');
    }

    public function test_the_step_form_can_mark_a_step_as_product_specific(): void
    {
        $stage = $this->stage('MACHINING');
        \Livewire\Livewire::test('process-route')->call('edit', $stage->id)->set('form.product_specific', true)->call('save');

        $this->assertTrue($stage->fresh()->product_specific);
        $this->assertContains('MACHINING', app(ProcessRoutes::class)->defaultSteps()->map(fn ($s) => $s->stage->code)->all());
    }
}
