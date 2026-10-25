<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\CoreBox;
use App\Models\Pattern;
use App\Models\Product;
use App\Services\Codes\CodeGenerator;
use App\Support\Code128;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodesAndMarksTest extends TestCase
{
    use RefreshDatabase;

    private function box(): CoreBox
    {
        $core = \App\Models\CoreDefinition::create(['name' => 'A core', 'core_type' => 'STANDARD']);

        return CoreBox::create(['core_definition_id' => $core->id, 'name' => 'Box']);
    }

    private function pattern(array $a = []): Pattern
    {
        return Pattern::create($a + ['name' => 'P', 'pattern_type' => 'LOOSE_PATTERN', 'status' => 'ACTIVE']);
    }

    public function test_codes_are_generated_in_order_and_typed_codes_are_kept(): void
    {
        $a = Product::create(['name' => 'A', 'product_type' => 'CASTING', 'base_uom' => 'EA']);
        $b = Product::create(['name' => 'B', 'product_type' => 'CASTING', 'base_uom' => 'EA']);
        $this->assertSame('PRD-00001', $a->code);
        $this->assertSame('PRD-00002', $b->code);
        $c = Product::create(['code' => 'GV-001', 'name' => 'C', 'product_type' => 'CASTING', 'base_uom' => 'EA']);
        $this->assertSame('GV-001', $c->code);
        $this->assertStringStartsWith('PAT-', $this->pattern()->code);
    }

    public function test_a_generated_code_never_collides_with_a_typed_one(): void
    {
        Product::create(['code' => 'PRD-00001', 'name' => 'Typed', 'product_type' => 'CASTING', 'base_uom' => 'EA']);
        $p = Product::create(['name' => 'Auto', 'product_type' => 'CASTING', 'base_uom' => 'EA']);
        $this->assertSame('PRD-00002', $p->code);
    }

    public function test_code_styles_numbers_letters_and_both(): void
    {
        AppSetting::setValue('codes.code_style', 'NUMBERS');
        $this->assertMatchesRegularExpression('/^1\d{5}$/', Product::create(['name' => 'N', 'product_type' => 'CASTING', 'base_uom' => 'EA'])->code);
        $this->assertMatchesRegularExpression('/^2\d{5}$/', $this->pattern()->code);
        AppSetting::setValue('codes.code_style', 'LETTERS');
        $this->assertMatchesRegularExpression('/^PRD[A-Z]{5}$/', Product::create(['name' => 'L', 'product_type' => 'CASTING', 'base_uom' => 'EA'])->code);
        AppSetting::setValue('codes.code_style', 'BOTH');
        AppSetting::setValue('codes.prefix.product', 'CST');
        $this->assertMatchesRegularExpression('/^CST-\d{5}$/', Product::create(['name' => 'B', 'product_type' => 'CASTING', 'base_uom' => 'EA'])->code);
    }

    public function test_tool_marks_are_numbers_only_by_default_and_unique_across_all_tooling(): void
    {
        $p1 = $this->pattern();
        $p2 = $this->pattern();
        $box = $this->box();
        $marks = [$p1->mark_code, $p2->mark_code, $box->coreDefinition->mark_code, $box->mark_code];
        $this->assertSame(['00001', '00002', '00003', '00004'], $marks);
        $this->assertSame(4, \DB::table('tool_marks')->count());
        $this->assertCount(4, array_unique($marks));
    }

    public function test_mark_style_and_length_follow_settings(): void
    {
        AppSetting::setValue('codes.mark_style', 'LETTERS');
        AppSetting::setValue('codes.mark_length', '4');
        $m = $this->pattern()->mark_code;
        $this->assertMatchesRegularExpression('/^[A-Z]{4}$/', $m);
        $this->assertDoesNotMatchRegularExpression('/[IOQ]/', $m);

        AppSetting::setValue('codes.mark_style', 'BOTH');
        AppSetting::setValue('codes.mark_length', '6');
        $m = $this->pattern()->mark_code;
        $this->assertMatchesRegularExpression('/^[2-9A-HJ-NP-Z]{6}$/', $m);   // no 0 1 I O Q
        $this->assertSame(6, strlen($m));
    }

    public function test_old_tooling_gets_a_mark_on_demand_and_marks_can_be_set_but_never_duplicated(): void
    {
        $p = $this->pattern();
        \DB::table('patterns')->where('id', $p->id)->update(['mark_code' => null]);
        \DB::table('tool_marks')->delete();
        $fresh = Pattern::find($p->id);
        $this->assertNotEmpty($fresh->ensureMark());

        $other = $this->pattern();
        $this->put(route('tools.mark', ['pattern', $p->id]), ['mark_code' => '777'])->assertSessionHasNoErrors();
        $this->assertSame('777', $p->fresh()->mark_code);
        $this->put(route('tools.mark', ['pattern', $other->id]), ['mark_code' => '777'])->assertSessionHasErrors('mark_code');
        $this->assertNotSame('777', $other->fresh()->mark_code);
    }

    public function test_barcode_matches_the_standard_and_labels_scan_and_settings_pages_work(): void
    {
        // Reference module string from an independent Code 128 implementation.
        $this->assertSame('1101001000011101110110110001011101011000100010011011100101000110001001110011010001011000101000111101100011101011', Code128::modules('PRD-A1B'));
        $this->assertSame('11010011100101100111001000101100011100010110100011011101100011101011', Code128::modules('123456'));
        $this->assertStringContainsString('<svg', Code128::svg('PAT-00001'));

        $product = Product::create(['name' => 'Scan me', 'product_type' => 'CASTING', 'base_uom' => 'EA']);
        $pattern = $this->pattern();
        $box = $this->box();

        $this->get(route('labels.show', ['product', $product->id]))->assertOk()->assertSee($product->code)->assertSee('<svg', false);
        $this->get(route('labels.show', ['pattern', $pattern->id]))->assertOk()->assertSee('Engraved mark');
        $this->get(route('scan', ['code' => strtolower($product->code)]))->assertRedirect(route('products.show', $product->id));
        $this->get(route('scan', ['code' => $pattern->mark_code]))->assertRedirect(route('patterns.show', $pattern->id));
        $this->get(route('scan', ['code' => $box->mark_code]))->assertRedirect(route('core-boxes.show', $box->id));
        $this->get(route('scan', ['code' => 'NOPE-1']))->assertOk()->assertSee('Nothing has the code');
        $this->get(route('tools.show', ['core_box', $box->id]))->assertOk()->assertSee($box->mark_code);

        $this->get(route('settings.codes'))->assertOk()->assertSee('Engraving marks');
        $this->put(route('settings.codes.update'), ['code_style' => 'NUMBERS', 'code_digits' => 6, 'mark_style' => 'NUMBERS', 'mark_length' => 6, 'prefix' => ['product' => 'abc']])->assertSessionHasNoErrors();
        $this->assertSame('NUMBERS', CodeGenerator::codeStyle());
        $this->assertSame(6, CodeGenerator::markLength());
        $this->put(route('settings.codes.update'), ['code_style' => 'WRONG', 'code_digits' => 6, 'mark_style' => 'NUMBERS', 'mark_length' => 6])->assertSessionHasErrors('code_style');
    }

    public function test_forms_accept_a_blank_code(): void
    {
        $this->post(route('products.store'), ['name' => 'Auto product', 'product_type' => 'CASTING', 'base_uom' => 'EA', 'active' => 1, 'revision' => 'A', 'revision_status' => 'DRAFT'])->assertSessionHasNoErrors();
        $this->assertSame('PRD-00001', Product::where('name', 'Auto product')->value('code'));
        $this->get(route('products.create'))->assertOk()->assertSee('PRD-00002');
    }

    public function test_row_click_setting_switches_the_behaviour(): void
    {
        $this->get(route('dashboard'))->assertSee('data-row-click="1"', false);
        $this->put(route('settings.interface.update'), [])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertSee('data-row-click="0"', false);
        $this->put(route('settings.interface.update'), ['row_click' => 1]);
        $this->get(route('settings.edit'))->assertOk()->assertSee('Click anywhere on a table row');
        $this->get(route('dashboard'))->assertSee('data-row-click="1"', false);
    }

    public function test_display_settings_control_toolbar_and_density(): void
    {
        $this->get(route('dashboard'))->assertSee('data-toolbar="1"', false)->assertSee('Compact density', false);
        $this->get(route('settings.display'))->assertOk()->assertSee('Search and filters in the table header');
        $this->put(route('settings.display.update'), ['density' => 'COMFORTABLE'])->assertSessionHasNoErrors();   // toolbar box unticked
        $this->get(route('dashboard'))->assertSee('data-toolbar="0"', false)->assertDontSee('Compact density', false);
        $this->put(route('settings.display.update'), ['density' => 'WRONG'])->assertSessionHasErrors('density');
    }
}
