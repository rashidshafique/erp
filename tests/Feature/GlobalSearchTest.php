<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Material;
use App\Models\Pattern;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name): Product
    {
        return Product::create(['name' => $name, 'product_type' => 'CASTING', 'base_uom' => 'EA']);
    }

    private function pattern(string $name): Pattern
    {
        return Pattern::create(['name' => $name, 'pattern_type' => 'LOOSE_PATTERN', 'status' => 'ACTIVE']);
    }

    public function test_suggestions_rank_matches_across_types_and_offer_a_type_for_a_short_word(): void
    {
        $housing = $this->product('Pump housing');
        $this->pattern('Housing pattern A');
        $this->product('Valve body');

        $found = $this->getJson(route('scan.suggest', ['q' => 'housing']))->assertOk()->json('results');
        $this->assertSame(['pattern', 'product'], array_column($found, 'type'));   // name starts with the word beats a later word
        $this->assertSame(route('products.show', $housing->id), $found[1]['url']);

        $this->getJson(route('scan.suggest', ['q' => 'pat']))->assertJsonPath('offer.type', 'pattern');
        $this->getJson(route('scan.suggest', ['q' => 'prd']))->assertJsonPath('offer.type', 'product');
        $this->getJson(route('scan.suggest', ['q' => 'housing', 'type' => 'pattern']))->assertJsonCount(1, 'results')->assertJsonPath('results.0.type', 'pattern');
    }

    public function test_enter_opens_a_clear_match_and_lists_the_rest(): void
    {
        $product = $this->product('Pump housing');
        $this->pattern('Pump housing pattern');
        $this->product('Bracket left');
        $this->product('Bracket right');
        $material = Material::create(['code' => 'FESI-75', 'name' => 'Ferrosilicon 75', 'material_type' => 'ALLOY', 'base_uom' => 'kg', 'status' => 'ACTIVE']);

        $this->get(route('scan', ['code' => 'pump housing']))->assertRedirect(route('products.show', $product->id));   // exact name beats a partial one
        $this->get(route('scan', ['code' => 'ferrosil']))->assertRedirect(route('materials.show', $material->id));    // the only match
        $this->get(route('scan', ['code' => 'bracket']))->assertOk()->assertSee('Bracket left')->assertSee('Bracket right');
        $this->get(route('scan', ['code' => 'pump', 'type' => 'pattern']))->assertRedirect();
        $this->get(route('scan', ['code' => 'nothing like this']))->assertOk()->assertSee('Nothing has the code');
    }

    public function test_employees_are_only_found_by_people_who_may_see_them(): void
    {
        Employee::create(['first_name' => 'Imran', 'last_name' => 'Ali', 'employment_type' => 'PERMANENT', 'joined_on' => '2025-01-01', 'status' => 'ACTIVE', 'weekly_off' => '0']);
        config(['foundry.require_login' => true]);
        $sales = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $sales->roles()->attach(Role::where('code', 'SALES')->value('id'));
        $hr = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $hr->roles()->attach(Role::where('code', 'HR_MANAGER')->value('id'));

        $this->actingAs($sales)->getJson(route('scan.suggest', ['q' => 'imran']))->assertJsonCount(0, 'results');
        $this->actingAs($hr)->getJson(route('scan.suggest', ['q' => 'imran']))->assertJsonPath('results.0.type', 'employee');
    }
}
