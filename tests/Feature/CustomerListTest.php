<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerListTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $code, string $name, array $over = []): Customer
    {
        $hold = $over['credit_hold'] ?? false;
        unset($over['credit_hold']);
        $customer = Customer::create($over + ['code' => $code, 'name' => $name, 'active' => true]);

        if ($hold) {
            $customer->forceFill(['credit_hold' => true])->save();
        }

        return $customer;
    }

    /** @return list<string> */
    private function codes($list): array
    {
        return collect($list->viewData('customers')->items())->pluck('code')->all();
    }

    public function test_the_page_shows_the_list_and_the_old_search_link_still_works(): void
    {
        $this->customer('ACME', 'Acme Pumps');
        $this->customer('BETA', 'Beta Valves');

        $this->get(route('customers.index'))->assertOk()->assertSee('Acme Pumps')->assertSee('New customer');
        $this->get(route('customers.index', ['search' => 'acme']))->assertOk()->assertSee('Acme Pumps')->assertDontSee('Beta Valves');
    }

    public function test_search_the_status_filter_and_the_tiles_narrow_the_list_as_they_are_used(): void
    {
        $this->customer('ACME', 'Acme Pumps', ['contact_name' => 'Sara Khan']);
        $this->customer('BETA', 'Beta Valves', ['phone' => '042-555']);
        $this->customer('OLD', 'Old Castings', ['active' => false]);

        $list = Livewire::test('customer-list');
        $this->assertSame(['ACME', 'BETA', 'OLD'], $this->codes($list));
        $list->assertViewHas('counts', fn ($c) => $c[''] === 3 && $c['active'] === 2);

        $list->set('search', 'beta');
        $this->assertSame(['BETA'], $this->codes($list));
        $list->set('search', 'sara');
        $this->assertSame(['ACME'], $this->codes($list), 'the contact person is searched too');
        $list->set('search', '555');
        $this->assertSame(['BETA'], $this->codes($list));

        $list->set('search', '')->call('showSlice', 'active');
        $this->assertSame(['ACME', 'BETA'], $this->codes($list));
        $list->call('resetFilters');
        $this->assertCount(3, $this->codes($list));
    }

    public function test_a_customer_is_added_from_the_dialog_with_a_generated_or_typed_code(): void
    {
        $dialog = Livewire::test('customer-list')->call('create')->assertSet('formOpen', true)->assertSeeHtml('<dialog class="qd"');

        $dialog->call('save')->assertHasErrors('form.name');
        $dialog->set('form.name', 'Acme Pumps')->set('form.email', 'not-an-email')->call('save')->assertHasErrors('form.email');
        $dialog->set('form.email', 'buy@acme.test')->set('form.payment_terms', '30 days')->call('save')->assertHasNoErrors()->assertSet('formOpen', false);

        $acme = Customer::sole();
        $this->assertSame('Acme Pumps', $acme->name);
        $this->assertSame('buy@acme.test', $acme->email);
        $this->assertSame('30 days', $acme->payment_terms);
        $this->assertTrue($acme->active);
        $this->assertNotEmpty($acme->code);
        $this->assertNull($acme->contact_name);

        $dialog->call('create')->set('form.name', 'Beta Valves')->set('form.code', 'beta')->call('save')->assertHasNoErrors()->assertSee('BETA added.');
        $dialog->call('create')->set('form.name', 'Other')->set('form.code', 'Beta')->call('save')->assertHasErrors('form.code');
    }

    public function test_clicking_a_row_opens_its_side_panel_instead_of_the_rows_first_link(): void
    {
        $acme = $this->customer('ACME', 'Acme Pumps');

        Livewire::test('customer-list')
            ->assertSeeHtml('<table class="no-row-click">')
            ->assertSeeHtml("wire:click=\"select('{$acme->id}')\"");
    }

    public function test_a_customer_is_quick_edited_and_can_be_marked_inactive(): void
    {
        $customer = $this->customer('ACME', 'Acme Pumps', ['phone' => '021-1']);
        $this->customer('BETA', 'Beta Valves');

        $dialog = Livewire::test('customer-list')
            ->call('edit', $customer->id)->assertSet('form.name', 'Acme Pumps')->assertSet('form.phone', '021-1')->assertSet('form.active', true)
            ->set('form.phone', '021-2')->set('form.code', 'acme')->set('form.active', false)->call('save')->assertHasNoErrors()->assertSee('ACME saved.');

        $customer->refresh();
        $this->assertSame('021-2', $customer->phone);
        $this->assertFalse($customer->active);

        $dialog->call('edit', $customer->id)->set('form.code', 'BETA')->call('save')->assertHasErrors('form.code');
    }

    public function test_the_tabs_tell_active_prospect_and_on_hold_customers_apart(): void
    {
        $this->customer('ACME', 'Acme Pumps');
        $this->customer('NEWB', 'Newbie Gears', ['is_prospect' => true]);
        $this->customer('HOLD', 'Hartwell Hydraulics', ['credit_hold' => true]);

        $list = Livewire::test('customer-list');
        $list->assertViewHas('counts', fn ($c) => $c['active'] === 1 && $c['prospect'] === 1 && $c['hold'] === 1);
        $this->assertSame(['NEWB'], $this->codes($list->call('showSlice', 'prospect')));
        $this->assertSame(['HOLD'], $this->codes($list->call('showSlice', 'hold')));
    }

    public function test_the_side_panel_follows_the_stage_and_prospect_and_hold_can_be_changed_from_it(): void
    {
        $prospect = $this->customer('NEWB', 'Newbie Gears', ['is_prospect' => true]);
        $held = $this->customer('HOLD', 'Hartwell Hydraulics', ['credit_hold' => true]);
        $held->forceFill(['credit_hold_reason' => 'Overdue 47 days'])->save();
        $held = $held->fresh();

        Livewire::test('customer-list')
            ->call('select', $prospect->id)->assertSee('Path to serial supply')->assertSee('Missing before first order')
            ->call('markActive', $prospect->id)->assertSee('Active')->assertDontSee('Path to serial supply')
            ->call('select', $held->id)->assertSee('Overdue 47 days')->assertSee('Blocked while on hold')
            ->call('releaseHold', $held->id)->assertDontSee('Blocked while on hold');

        $this->assertFalse($held->fresh()->credit_hold);
    }

    public function test_a_customer_added_from_the_list_starts_as_a_prospect_until_its_first_order(): void
    {
        Livewire::test('customer-list')->call('create')->assertSet('form.is_prospect', true)
            ->set('form.name', 'Orbis Gears')->call('save');

        $customer = Customer::where('code', '!=', null)->firstOrFail();
        $this->assertSame('prospect', $customer->stage());

        $customer->salesOrders()->create(['order_number' => 'SO-1', 'order_date' => today(), 'status' => 'CONFIRMED']);
        $this->assertSame('active', $customer->fresh()->stage());
    }
}
