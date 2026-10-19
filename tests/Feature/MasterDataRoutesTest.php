<?php

namespace Tests\Feature;

use App\Models\Flask;
use App\Models\FlaskSize;
use App\Models\IronGrade;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class MasterDataRoutesTest extends TestCase
{
    use BuildsCastingLots;
    use RefreshDatabase;

    private function product(string $code = 'FLG-1'): Product
    {
        return Product::create(['code' => $code, 'name' => 'Flange', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'active' => true]);
    }

    /** @return array<string, mixed> */
    private function productForm(array $over = []): array
    {
        return $over + ['code' => 'FLG-1', 'name' => 'Flange', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'active' => 1];
    }

    public function test_a_product_is_edited_with_a_unique_case_insensitive_code(): void
    {
        $product = $this->product();
        $other = $this->product('VLV-1');

        $this->get(route('products.edit', $product))->assertOk();
        $this->put(route('products.update', $product), $this->productForm(['name' => 'Flange 6 inch', 'code' => 'flg-1']))->assertSessionHasNoErrors();
        $this->assertSame('Flange 6 inch', $product->fresh()->name);
        $this->assertSame('FLG-1', $product->fresh()->code);

        $this->put(route('products.update', $other), $this->productForm(['code' => 'flg-1']))->assertSessionHasErrors('code');
        $this->put(route('products.update', $product), $this->productForm(['product_type' => 'GOLD']))->assertSessionHasErrors('product_type');
    }

    public function test_a_new_product_cannot_reuse_a_code_in_another_case(): void
    {
        $this->product();

        $this->post(route('products.store'), $this->productForm(['code' => 'flg-1', 'revision' => 'A', 'revision_status' => 'DRAFT']))->assertSessionHasErrors('code');
        $this->assertSame(1, Product::count());
    }

    public function test_a_product_revision_needs_a_new_letter_and_a_sensible_date_range(): void
    {
        $product = $this->product();
        $form = ['revision' => 'B', 'status' => 'DRAFT', 'net_weight_kg' => 4.2, 'effective_from' => '2026-10-01'];

        $this->post(route('products.revisions.store', $product), $form)->assertSessionHasNoErrors();
        $this->assertSame(1, $product->revisions()->count());
        $this->post(route('products.revisions.store', $product), $form)->assertSessionHasErrors('revision');
        $this->post(route('products.revisions.store', $product), ['revision' => 'C', 'effective_to' => '2026-09-01'] + $form)->assertSessionHasErrors('effective_to');
    }

    public function test_flask_sizes_are_added_renamed_and_kept_unique(): void
    {
        $this->post(route('flask-sizes.store'), ['name' => '600 x 500'])->assertSessionHasNoErrors();
        $size = FlaskSize::sole();
        $this->post(route('flask-sizes.store'), ['name' => '600 x 500'])->assertSessionHasErrors('name');

        $this->put(route('flask-sizes.update', $size), ['name' => '600 x 500 deep', 'status' => 'INACTIVE', 'inside_length_mm' => 600])->assertSessionHasNoErrors();
        $this->assertSame('INACTIVE', $size->fresh()->status);
        $this->put(route('flask-sizes.update', $size), ['name' => 'x', 'status' => 'GONE'])->assertSessionHasErrors('status');
    }

    public function test_a_flask_in_a_mould_cannot_change_size_and_cannot_be_its_own_partner(): void
    {
        $small = FlaskSize::create(['code' => 'S1', 'name' => 'Small', 'status' => 'ACTIVE']);
        $large = FlaskSize::create(['code' => 'L1', 'name' => 'Large', 'status' => 'ACTIVE']);
        $flask = Flask::create(['flask_size_id' => $small->id, 'position' => 'COPE', 'status' => 'AVAILABLE']);
        $form = fn (array $over = []) => $over + ['flask_size_id' => $small->id, 'position' => 'COPE', 'mark_code' => 'FLK001'];

        $this->put(route('flasks.update', $flask), $form(['location' => 'Bay 2']))->assertSessionHasNoErrors();
        $this->assertSame('Bay 2', $flask->fresh()->location);
        $this->put(route('flasks.update', $flask), $form(['partner_flask_id' => $flask->id]))->assertSessionHasErrors('partner_flask_id');

        $flask->update(['status' => 'IN_MOULD', 'current_mould_assembly_id' => $this->castingLot()->pourMouldAssembly->mould_assembly_id]);
        $this->put(route('flasks.update', $flask), $form(['flask_size_id' => $large->id]))->assertSessionHasErrors('flask_size_id');
        $this->assertSame($small->id, $flask->fresh()->flask_size_id);
    }

    public function test_releasing_a_flask_that_holds_nothing_says_so(): void
    {
        $size = FlaskSize::create(['code' => 'S1', 'name' => 'Small', 'status' => 'ACTIVE']);
        $flask = Flask::create(['flask_size_id' => $size->id, 'position' => 'COPE', 'status' => 'AVAILABLE']);

        $this->post(route('flasks.release', $flask))->assertSessionHas('success', 'Nothing to release.');
    }

    public function test_iron_grade_limits_must_be_ordered(): void
    {
        $grade = IronGrade::where('family', 'GREY')->firstOrFail();

        $this->put(route('iron-grades.update', $grade), ['name' => $grade->name, 'tap_temp_min_c' => 1450, 'tap_temp_max_c' => 1500])->assertSessionHasNoErrors();
        $this->assertSame(1500, (int) $grade->fresh()->tap_temp_max_c);
        $this->put(route('iron-grades.update', $grade), ['name' => $grade->name, 'tap_temp_min_c' => 1500, 'tap_temp_max_c' => 1450])->assertSessionHasErrors('tap_temp_max_c');
        $this->put(route('iron-grades.update', $grade), ['name' => $grade->name, 'pour_temp_min_c' => 500])->assertSessionHasErrors('pour_temp_min_c');
        $this->assertSame(1500, (int) $grade->fresh()->tap_temp_max_c);
    }
}
