<?php

namespace Tests\Feature;

use App\Domains\Purchasing\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierListTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(string $name, array $over = []): Supplier
    {
        return Supplier::create($over + ['name' => $name, 'review_interval_days' => 365, 'approval_status' => 'APPROVED']);
    }

    /** @return list<string> */
    private function names($list): array
    {
        return collect($list->viewData('suppliers')->items())->pluck('name')->all();
    }

    public function test_the_page_shows_the_status_tiles_and_the_list_with_the_old_link_parameters_still_working(): void
    {
        $this->supplier('Acme Pig Iron', ['supplies' => 'pig iron']);
        $this->supplier('Late Alloys', ['next_review_on' => today()->subDays(3)]);
        $this->supplier('New Binders', ['approval_status' => 'PENDING']);

        $this->get(route('suppliers.index'))->assertOk()->assertSee('Acme Pig Iron')->assertSee('+ Add supplier')->assertSee('Review overdue');
        $this->get(route('suppliers.index', ['state' => 'review']))->assertOk()->assertSee('Late Alloys')->assertDontSee('Acme Pig Iron');
        $this->get(route('suppliers.index', ['status' => 'PENDING']))->assertOk()->assertSee('New Binders')->assertDontSee('Acme Pig Iron');
        $this->get(route('suppliers.index', ['q' => 'pig']))->assertOk()->assertSee('Acme Pig Iron')->assertDontSee('Late Alloys');
    }

    public function test_search_filters_and_the_tiles_narrow_the_list_as_they_are_used(): void
    {
        $this->supplier('Acme Pig Iron', ['supplies' => 'pig iron']);
        $this->supplier('Late Alloys', ['next_review_on' => today()->subDays(3)]);
        $this->supplier('Cond Binders', ['approval_status' => 'CONDITIONAL']);
        $this->supplier('Banned Sand', ['approval_status' => 'SUSPENDED', 'next_review_on' => today()->subDays(9)]);

        $list = Livewire::test('purchasing::supplier-list');
        $this->assertSame(['Banned Sand', 'Cond Binders', 'Acme Pig Iron', 'Late Alloys'], $this->names($list), 'suspended first, then conditional, then the rest by name');
        $list->assertViewHas('counts', fn ($c) => $c['CONDITIONAL'] === 1 && $c['SUSPENDED'] === 1 && $c['review'] === 1 && $c['PENDING'] === 0);

        $list->set('search', 'iron');
        $this->assertSame(['Acme Pig Iron'], $this->names($list));
        $list->set('search', '')->call('showSlice', '', 'review');
        $this->assertSame(['Late Alloys'], $this->names($list), 'a blocked supplier is not counted as overdue');
        $list->call('showSlice', 'SUSPENDED');
        $this->assertSame(['Banned Sand'], $this->names($list));
        $list->call('resetFilters');
        $this->assertCount(4, $this->names($list));
    }

    public function test_a_supplier_is_added_from_the_dialog_as_pending_with_a_generated_or_typed_code(): void
    {
        $dialog = Livewire::test('purchasing::supplier-list')->call('create')->assertSet('formOpen', true)->assertSeeHtml('<dialog class="qd"');

        $dialog->call('save')->assertHasErrors('form.name');
        $dialog->set('form.name', 'Acme')->set('form.review_interval_days', 5)->call('save')->assertHasErrors('form.review_interval_days');
        $dialog->set('form.review_interval_days', 365)->set('form.email', 'not-an-email')->call('save')->assertHasErrors('form.email');
        $dialog->set('form.email', 'sales@acme.test')->set('form.supplies', 'pig iron')->call('save')->assertHasNoErrors()->assertSet('formOpen', false);

        $acme = Supplier::sole();
        $this->assertSame('PENDING', $acme->approval_status);
        $this->assertSame('Acme', $acme->name);
        $this->assertSame('sales@acme.test', $acme->email);
        $this->assertNotEmpty($acme->code);
        $this->assertNull($acme->contact_name);

        $dialog->call('create')->set('form.name', 'Beta')->set('form.code', 'beta')->call('save')->assertHasNoErrors()->assertSee('BETA added as pending');
        $this->assertSame('PENDING', Supplier::where('code', 'BETA')->sole()->approval_status);

        $dialog->call('create')->set('form.name', 'Gamma')->set('form.code', 'Acme-dup')->call('save')->assertHasNoErrors();
        $dialog->call('create')->set('form.name', 'Delta')->set('form.code', $acme->code)->call('save')->assertHasErrors('form.code');   // case-insensitive duplicate
    }

    public function test_a_supplier_is_quick_edited_without_changing_its_approval(): void
    {
        $supplier = $this->supplier('Acme', ['code' => 'ACME', 'phone' => '021-1']);

        Livewire::test('purchasing::supplier-list')
            ->call('edit', $supplier->id)->assertSet('form.name', 'Acme')->assertSet('form.phone', '021-1')
            ->set('form.phone', '021-2')->set('form.code', 'acme')->call('save')->assertHasNoErrors()->assertSee('ACME saved.');

        $this->assertSame('021-2', $supplier->fresh()->phone);
        $this->assertSame('APPROVED', $supplier->fresh()->approval_status);
    }

    public function test_the_form_and_detail_pages_use_the_shared_styles(): void
    {
        $supplier = $this->supplier('Acme');

        $this->get(route('suppliers.create'))->assertOk()->assertSee('Contact')->assertSee('Review');
        $this->get(route('suppliers.edit', $supplier))->assertOk();
        $this->get(route('suppliers.show', $supplier))->assertOk()->assertSee('Latest score')->assertSee('Next review');
    }
}
