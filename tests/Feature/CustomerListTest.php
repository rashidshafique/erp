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
        return Customer::create($over + ['code' => $code, 'name' => $name, 'active' => true]);
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

        $this->get(route('customers.index'))->assertOk()->assertSee('Acme Pumps')->assertSee('+ New customer');
        $this->get(route('customers.index', ['search' => 'acme']))->assertOk()->assertSee('Acme Pumps')->assertDontSee('Beta Valves');
    }

    public function test_search_the_status_filter_and_the_tiles_narrow_the_list_as_they_are_used(): void
    {
        $this->customer('ACME', 'Acme Pumps', ['contact_name' => 'Sara Khan']);
        $this->customer('BETA', 'Beta Valves', ['phone' => '042-555']);
        $this->customer('OLD', 'Old Castings', ['active' => false]);

        $list = Livewire::test('customer-list');
        $this->assertSame(['ACME', 'BETA', 'OLD'], $this->codes($list));
        $list->assertViewHas('counts', fn ($c) => $c[''] === 3 && $c['active'] === 2 && $c['inactive'] === 1);

        $list->set('search', 'beta');
        $this->assertSame(['BETA'], $this->codes($list));
        $list->set('search', 'sara');
        $this->assertSame(['ACME'], $this->codes($list), 'the contact person is searched too');
        $list->set('search', '555');
        $this->assertSame(['BETA'], $this->codes($list));

        $list->set('search', '')->call('showSlice', 'inactive');
        $this->assertSame(['OLD'], $this->codes($list));
        $list->set('status', 'active');
        $this->assertSame(['ACME', 'BETA'], $this->codes($list));
        $list->call('resetFilters');
        $this->assertCount(3, $this->codes($list));
    }

    public function test_each_customer_shows_the_date_of_its_latest_dispatch(): void
    {
        $acme = $this->customer('ACME', 'Acme Pumps');
        $this->customer('BETA', 'Beta Valves');
        foreach (['D-1' => '2026-09-02 10:00', 'D-2' => '2026-10-14 15:30', 'D-3' => null] as $number => $at) {
            DB::table('dispatches')->insert(['id' => (string) Str::uuid(), 'dispatch_number' => $number, 'customer_id' => $acme->id, 'status' => $at ? 'DISPATCHED' : 'DRAFT', 'dispatched_at' => $at, 'created_at' => now(), 'updated_at' => now()]);
        }

        $list = Livewire::test('customer-list')->assertSee('Last dispatch');
        $dates = collect($list->viewData('customers')->items())->mapWithKeys(fn ($c) => [$c->code => $c->last_dispatched_at?->toDateString()])->all();

        $this->assertSame(['ACME' => '2026-10-14', 'BETA' => null], $dates, 'the latest sent dispatch counts; a draft is not sent yet');
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
}
