<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Services\Quotation\QuotationService;
use App\Services\Quotation\Rates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatternRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function backfill(): void
    {
        (require database_path('migrations/2026_10_21_100000_define_current_pattern_revisions.php'))->up();
    }

    /** A revision row as older code and imports wrote it: no code, number or current flag. */
    private function legacyRevision(string $patternId, string $revision, string $status, string $createdAt): string
    {
        $id = (string) Str::uuid();
        DB::table('pattern_revisions')->insert(['id' => $id, 'pattern_id' => $patternId, 'revision' => $revision, 'status' => $status, 'cavities_per_mould' => 2, 'created_at' => $createdAt, 'updated_at' => $createdAt]);

        return $id;
    }

    private function productRevision(): ProductRevision
    {
        $product = Product::create(['code' => 'VALVE-1', 'name' => 'Valve body']);

        return ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
    }

    public function test_backfill_gives_a_bare_pattern_a_baseline_and_keeps_existing_revisions_current(): void
    {
        $bare = Pattern::create(['code' => 'P-0123', 'name' => 'Imported plate']);
        $revised = Pattern::create(['code' => 'P-0200', 'name' => 'Revised plate']);
        $old = $this->legacyRevision($revised->id, 'A', 'APPROVED', '2026-01-01 08:00:00');
        $approved = $this->legacyRevision($revised->id, 'B', 'APPROVED', '2026-03-01 08:00:00');

        $this->backfill();
        $this->backfill();

        $baseline = PatternRevision::where('pattern_id', $bare->id)->sole();
        $this->assertSame(['P-0123-R0', 0, true, true, null, 'APPROVED'], [$baseline->code, $baseline->sequence, $baseline->legacy_baseline, $baseline->is_current, $baseline->drawing_revision, $baseline->status]);
        $this->assertSame(2, PatternRevision::where('pattern_id', $revised->id)->count());
        $this->assertTrue(PatternRevision::find($approved)->is_current);
        $this->assertTrue(PatternRevision::find($old)->isSuperseded());
        $this->assertSame(['P-0200-R0', 'P-0200-R1'], PatternRevision::where('pattern_id', $revised->id)->orderBy('sequence')->pluck('code')->all());
    }

    public function test_a_new_revision_supersedes_the_current_one_and_keeps_it(): void
    {
        Storage::fake(config('foundry.attachments.disk'));
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate']);
        $baseline = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'legacy_baseline' => true, 'cavities_per_mould' => 4]);

        $response = $this->post(route('patterns.revisions.store', $pattern), [
            'change_description' => 'Added 0.5° draft on the boss', 'drawing_revision' => 'C', 'shrinkage_note' => '1.0 % shrink',
            'photo' => UploadedFile::fake()->createWithContent('plate.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')), '_quick' => 'qd-revision',
        ]);

        $response->assertRedirect(route('patterns.show', $pattern))->assertSessionHas('success', 'Revision P-0123-R1 is now current. P-0123-R0 is superseded and kept.');
        $new = PatternRevision::where('pattern_id', $pattern->id)->where('is_current', true)->sole();
        $this->assertSame(['P-0123-R1', 'C', '1.0 % shrink', 4, false], [$new->code, $new->drawing_revision, $new->shrinkage_note, $new->cavities_per_mould, $new->legacy_baseline]);
        $this->assertTrue($baseline->fresh()->isSuperseded());
        $this->assertSame(1, Attachment::where('attachable_type', 'pattern_revision')->where('attachable_id', $new->id)->where('category', 'PHOTO')->count());
    }

    public function test_a_revision_needs_a_reason(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate']);
        PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED']);

        $response = $this->post(route('patterns.revisions.store', $pattern), ['change_description' => '', '_quick' => 'qd-revision']);

        $response->assertSessionHasErrors(['change_description' => 'Say what changed in the tooling geometry or layout.']);
        $this->assertSame(1, $pattern->revisions()->count());
    }

    public function test_the_first_revision_of_a_pattern_is_current_however_it_is_created(): void
    {
        $pattern = Pattern::create(['code' => 'P-0300', 'name' => 'Plate']);

        $first = PatternRevision::create(['pattern_id' => $pattern->id, 'revision' => 'A', 'status' => 'DRAFT']);
        $second = PatternRevision::create(['pattern_id' => $pattern->id, 'revision' => 'B', 'status' => 'DRAFT']);

        $this->assertTrue($first->fresh()->is_current);
        $this->assertFalse($second->fresh()->is_current);
        $this->assertSame(['P-0300-R0', 'P-0300-R1'], [$first->code, $second->code]);
    }

    public function test_each_revision_shows_one_editable_row_per_cavity(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate']);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => 3]);
        $revision->cavityMap()->create(['cavity_number' => 2, 'product_revision_id' => $this->productRevision()->id, 'casting_weight_kg' => 5]);

        $page = $this->get(route('patterns.show', $pattern));

        $page->assertOk()->assertSee('name="cavities_per_mould" min="1" max="200" value="3"', false)
            ->assertSee('data-cavity-row="1"', false)->assertSee('data-cavity-row="2"', false)->assertSee('data-cavity-row="3"', false)
            ->assertDontSee('data-cavity-row="4"', false)->assertSee('name="rows[2][casting_weight_kg]" min="0.0001" step="0.0001" value="5"', false);
    }

    public function test_cavities_on_a_superseded_revision_are_allowed_with_a_warning(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate']);
        $old = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => 4]);
        $pattern->addRevision(['change_description' => 'New gating']);
        $product = $this->productRevision();

        $response = $this->post(route('patterns.cavities.add', $pattern), ['pattern_revision_id' => $old->id, 'cavity_number' => 2, 'product_revision_id' => $product->id, 'cavity_role' => 'WHOLE']);

        $response->assertSessionHasNoErrors()->assertSessionHas('warning', fn (string $w) => str_contains($w, 'P-0123-R0 was superseded') && str_contains($w, 'by P-0123-R1'));
        $this->assertSame(1, $old->cavityMap()->count());
        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('alert alert-warning', false)->assertSee('P-0123-R0 was superseded');
    }

    public function test_a_cavity_cannot_be_mapped_on_another_patterns_revision(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate']);
        PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => 4]);
        $other = PatternRevision::create(['pattern_id' => Pattern::create(['code' => 'P-0999', 'name' => 'Other'])->id, 'status' => 'APPROVED', 'cavities_per_mould' => 4]);

        $response = $this->post(route('patterns.cavities.add', $pattern), ['pattern_revision_id' => $other->id, 'cavity_number' => 1, 'product_revision_id' => $this->productRevision()->id, 'cavity_role' => 'WHOLE']);

        $response->assertSessionHasErrors(['pattern_revision_id' => 'Choose a revision of this pattern.']);
        $this->assertSame(0, $other->cavityMap()->count());
    }

    public function test_the_baseline_filter_lists_patterns_still_on_the_baseline_with_no_drawing(): void
    {
        $bare = Pattern::create(['code' => 'P-0123', 'name' => 'Bare plate']);
        PatternRevision::create(['pattern_id' => $bare->id, 'status' => 'APPROVED', 'legacy_baseline' => true]);
        $drawn = Pattern::create(['code' => 'P-0124', 'name' => 'Drawn plate']);
        PatternRevision::create(['pattern_id' => $drawn->id, 'status' => 'APPROVED', 'legacy_baseline' => true, 'drawing_revision' => 'B']);
        $revised = Pattern::create(['code' => 'P-0125', 'name' => 'Revised plate']);
        PatternRevision::create(['pattern_id' => $revised->id, 'status' => 'APPROVED', 'legacy_baseline' => true]);
        $revised->addRevision(['change_description' => 'New core prints']);

        $this->get(route('patterns.index'))->assertOk()->assertSee('1 still on the as-imported baseline with no drawing linked');

        $this->get(route('patterns.index', ['revision' => 'baseline']))->assertOk()
            ->assertSee('Bare plate')->assertDontSee('Drawn plate')->assertDontSee('Revised plate');
    }

    public function test_a_new_revision_carries_the_cavity_map_over_and_leaves_the_old_one_alone(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate']);
        $old = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        $product = $this->productRevision();
        $old->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $product->id, 'casting_weight_kg' => 5, 'notes' => 'Left half']);
        $old->cavityMap()->create(['cavity_number' => 2, 'product_revision_id' => $product->id, 'casting_weight_kg' => 5]);

        $response = $this->post(route('patterns.revisions.store', $pattern), [
            'change_description' => 'Moved the ingate', 'modification_cost' => '45000', 'effective_from' => '2026-10-20',
            'cost_note' => 'Pattern shop invoice 4471', 'casting_weight_kg' => '5', 'pour_weight_kg' => '8', 'pieces_per_mould' => '2', '_quick' => 'qd-revision',
        ]);

        $response->assertSessionHas('success', 'Revision P-0123-R1 is now current. P-0123-R0 is superseded and kept. Its cavity map (2 cavities) was carried over; change it on the cavity map if the new layout differs.');
        $new = $pattern->currentRevision()->sole();
        $this->assertSame([[1, 5.0, 'Left half', false, $old->id], [2, 5.0, null, false, $old->id]],
            $new->cavityMap->map(fn ($c) => [$c->cavity_number, (float) $c->casting_weight_kg, $c->notes, $c->needs_review, $c->copied_from_revision_id])->all());
        $this->assertSame([false, false], $old->cavityMap()->pluck('needs_review')->all());
        $this->assertSame(['45000.00', '2026-10-20', 'Pattern shop invoice 4471', 62.5, 2], [$new->modification_cost, $new->effective_from->toDateString(), $new->cost_note, $new->yieldPercent(), $new->pieces_per_mould]);

    }

    public function test_pour_weight_cannot_be_below_casting_weight(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate']);
        PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED']);

        $this->post(route('patterns.revisions.store', $pattern), ['change_description' => 'x', 'casting_weight_kg' => '8', 'pour_weight_kg' => '5'])
            ->assertSessionHasErrors(['pour_weight_kg' => 'The pour weight includes gating and risers, so it cannot be less than the casting weight.']);
        $this->assertSame(1, $pattern->revisions()->count());
    }

    public function test_a_baseline_shows_its_cost_as_not_recorded(): void
    {
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate']);
        PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'legacy_baseline' => true]);

        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('not recorded');
    }

    public function test_quoting_reads_weights_from_the_current_revision_only(): void
    {
        $productRevision = $this->productRevision();
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate']);
        $old = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'casting_weight_kg' => 9, 'pour_weight_kg' => 20]);
        $old->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $productRevision->id]);
        $customer = Customer::create(['code' => 'C1', 'name' => 'Acme Pumps', 'payment_terms' => '30 days']);
        $enquiry = Enquiry::create(['customer_id' => $customer->id, 'title' => 'Valve', 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()]);
        $item = $enquiry->items()->create(['line_no' => 1, 'description' => 'Valve body', 'product_id' => $productRevision->product_id, 'weight_kg' => 10, 'first_batch_qty' => 50, 'family' => 'GREY', 'feasibility_status' => 'OK']);
        $service = app(QuotationService::class);
        $rates = Rates::get();

        $first = $service->inputsFor($item, $rates);
        $pattern->addRevision(['change_description' => 'Lighter gating', 'casting_weight_kg' => 9, 'pour_weight_kg' => 12], copyLayout: true);
        $second = $service->inputsFor($item->fresh(), $rates);

        $this->assertSame([9.0, 20.0], [$first['weight_kg'], $first['poured_kg']]);
        $this->assertSame([9.0, 12.0], [$second['weight_kg'], $second['poured_kg']]);
        $this->assertStringStartsWith('From pattern revision P-0123-R1 (current)', $second['basis']);
    }

    public function test_the_revision_separator_is_set_in_settings(): void
    {
        $this->put(route('settings.codes.update'), ['code_style' => 'BOTH', 'code_digits' => 5, 'mark_style' => 'BOTH', 'mark_length' => 5, 'revision_suffix' => '.REV'])->assertSessionHasNoErrors();
        $pattern = Pattern::create(['code' => 'P-0123', 'name' => 'Plate']);

        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED']);

        $this->assertSame('P-0123.REV0', $revision->code);
    }
}
