<?php

namespace Tests\Feature;

use App\Domains\Parties\Models\Party;
use App\Domains\Purchasing\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnersAndSuppliersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_partner_is_listed_filtered_shown_and_edited(): void
    {
        $this->post(route('parties.store'), ['name' => 'Karachi Valves', 'email' => 'buyer@kv.example', 'role_customer' => 1, 'role_supplier' => 1])->assertSessionHasNoErrors();
        $party = Party::sole();
        $this->post(route('parties.store'), ['name' => 'Lahore Castings', 'role_customer' => 1]);

        $this->get(route('parties.index'))->assertOk()->assertSee('Karachi Valves')->assertSee('Lahore Castings');
        $this->get(route('parties.index', ['q' => 'karachi']))->assertOk()->assertSee('Karachi Valves')->assertDontSee('Lahore Castings');
        $this->get(route('parties.index', ['role' => 'both']))->assertOk()->assertSee('Karachi Valves')->assertDontSee('Lahore Castings');
        $this->get(route('parties.create'))->assertOk();
        $this->get(route('parties.show', $party))->assertOk()->assertSee('Karachi Valves');
        $this->get(route('parties.edit', $party))->assertOk();

        $this->put(route('parties.update', $party), ['name' => 'Karachi Valves Ltd', 'phone' => '021 111'])->assertSessionHasNoErrors();
        $this->assertSame('Karachi Valves Ltd', $party->fresh()->name);
        $this->assertFalse($party->fresh()->active);       // a save without the active box switches the partner off
        $this->put(route('parties.update', $party), ['name' => ''])->assertSessionHasErrors('name');
        $this->put(route('parties.update', $party), ['name' => 'X', 'email' => 'not-an-email'])->assertSessionHasErrors('email');
    }

    public function test_one_partner_holds_both_customer_and_supplier_roles(): void
    {
        $this->post(route('parties.store'), ['name' => 'Karachi Valves', 'role_customer' => 1, 'role_supplier' => 1]);
        $party = Party::sole()->load(['customer', 'supplier']);

        $this->assertNotNull($party->customer);
        $this->assertSame('PENDING', $party->supplier->approval_status);
        $this->assertSame(1, Party::count());
    }

    public function test_a_supplier_is_listed_shown_and_edited_with_a_unique_upper_cased_code(): void
    {
        $this->post(route('suppliers.store'), ['name' => 'Acme Pig Iron', 'code' => 'acme', 'review_interval_days' => 365])->assertSessionHasNoErrors();
        $supplier = Supplier::sole();
        $this->assertSame('ACME', $supplier->code);

        $this->get(route('suppliers.index'))->assertOk()->assertSee('Acme Pig Iron');
        $this->get(route('suppliers.index', ['q' => 'nomatch']))->assertOk()->assertDontSee('Acme Pig Iron');
        $this->get(route('suppliers.create'))->assertOk();
        $this->get(route('suppliers.show', $supplier))->assertOk()->assertSee('Acme Pig Iron');
        $this->get(route('suppliers.edit', $supplier))->assertOk();

        $this->put(route('suppliers.update', $supplier), ['name' => 'Acme Iron', 'code' => 'acme', 'review_interval_days' => 180])->assertSessionHasNoErrors();
        $this->assertSame(180, $supplier->fresh()->review_interval_days);

        $other = Supplier::create(['name' => 'Beta', 'review_interval_days' => 365]);
        $this->put(route('suppliers.update', $other), ['name' => 'Beta', 'code' => 'ACME', 'review_interval_days' => 365])->assertSessionHasErrors('code');
        $this->put(route('suppliers.update', $other), ['name' => 'Beta', 'code' => 'acme', 'review_interval_days' => 365])->assertSessionHasErrors('code');   // case does not make a new code
        $this->put(route('suppliers.update', $other), ['name' => 'Beta', 'review_interval_days' => 10])->assertSessionHasErrors('review_interval_days');
    }

    public function test_suppliers_sit_in_the_melting_and_materials_menu_after_materials_and_not_under_quality(): void
    {
        $html = $this->get(route('suppliers.index'))->assertOk()->getContent();
        $menu = [];
        foreach (array_slice(preg_split('/<div class="nav-group/', $html), 1) as $group) {
            preg_match('/<span class="nav-lab">.*?<span>(.*?)<\/span><\/span>/s', $group, $title);
            preg_match_all('/<span>([^<]+)<\/span><\/a>/', explode("</div>\n            </div>", $group)[0], $items);
            $menu[html_entity_decode($title[1])] = array_map('html_entity_decode', $items[1]);
        }

        $melting = $menu['Melting & Materials'];
        $this->assertSame(array_search('Materials', $melting) + 1, array_search('Suppliers', $melting));
        $this->assertNotContains('Suppliers', $menu['Quality']);
        $this->assertStringContainsString('class="nav-group is-active"', $html);
    }

    public function test_finished_goods_packing_and_dispatch_sit_in_the_sales_menu(): void
    {
        $html = $this->get(route('dispatches.index'))->assertOk()->getContent();
        $menu = [];
        foreach (array_slice(preg_split('/<div class="nav-group/', $html), 1) as $group) {
            preg_match('/<span class="nav-lab">.*?<span>(.*?)<\/span><\/span>/s', $group, $title);
            preg_match_all('/<span>([^<]+)<\/span><\/a>/', explode("</div>\n            </div>", $group)[0], $items);
            $menu[html_entity_decode($title[1])] = array_map('html_entity_decode', $items[1]);
        }

        $sales = $menu['Sales & Planning'];
        $this->assertSame([], array_diff(['Finished goods', 'Packing', 'Dispatch'], $sales));
        $this->assertSame([], array_intersect(['Finished goods', 'Packing', 'Dispatch'], $menu['Quality']));
        $this->assertArrayNotHasKey('Dispatch', $menu, 'No menu of its own.');
        $this->assertStringContainsString('class="nav-group is-active"', $html, 'The Sales menu opens on the dispatch pages.');
    }
}
