<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternLinkFilterTest extends TestCase
{
    use RefreshDatabase;

    private function pattern(string $code): Pattern
    {
        return Pattern::create(['code' => $code, 'name' => "Pattern {$code}", 'pattern_type' => 'MATCHPLATE', 'status' => 'ACTIVE']);
    }

    private function linked(string $code): Pattern
    {
        $pattern = $this->pattern($code);
        $revision = $pattern->addRevision([]);
        $product = Product::create(['code' => 'PR-'.$code, 'name' => 'Valve', 'status' => 'ACTIVE']);
        $productRevision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $revision->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $productRevision->id]);

        return $pattern;
    }

    public function test_unlinked_filter_lists_bare_and_empty_patterns_but_not_linked_ones(): void
    {
        $bare = $this->pattern('P-1');
        $empty = $this->pattern('P-2');
        $empty->addRevision([]);
        $linked = $this->linked('P-3');

        $this->get(route('patterns.index', ['link' => 'unlinked']))->assertOk()
            ->assertSee($bare->code)->assertSee($empty->code)->assertDontSee($linked->name);
    }

    public function test_previous_and_next_stay_inside_the_unlinked_set(): void
    {
        $a = $this->pattern('P-1');
        $this->linked('P-2');
        $c = $this->pattern('P-3');

        $this->get(route('patterns.show', ['pattern' => $a, 'link' => 'unlinked']))->assertOk()
            ->assertSee(route('patterns.show', ['pattern' => $c, 'link' => 'unlinked']), false);
    }

    public function test_first_revision_needs_no_description_but_later_ones_do(): void
    {
        $pattern = $this->pattern('P-1');

        $this->post(route('patterns.revisions.store', $pattern), [])->assertSessionHasNoErrors();
        $this->assertSame('Initial revision', $pattern->fresh()->currentRevision->change_description);

        $this->post(route('patterns.revisions.store', $pattern), [])->assertSessionHasErrors('change_description');
    }
}
