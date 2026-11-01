<?php

namespace Tests\Feature;

use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Design 19a and 19i to 19m: the chart in category, section and account, automatic codes, locks that follow postings, bulk delete and section order. */
class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
    }

    private function account(string $code): LedgerAccount
    {
        return LedgerAccount::where('code', $code)->firstOrFail();
    }

    private function postSale(): void
    {
        $customer = Customer::create(['code' => 'KOV', 'name' => 'Kovar Pumps']);
        app(InvoiceService::class)->issue(app(InvoiceService::class)->saveDraft(['customer_id' => $customer->id, 'invoice_date' => '2026-10-01'],
            [['description' => 'Pump casing', 'quantity' => 2, 'unit_price' => 100]]));
    }

    public function test_the_starting_chart_has_the_clients_structure(): void
    {
        $this->assertSame([12, 48, 121], [LedgerAccount::whereRaw('length(code) = 2')->count(), LedgerAccount::whereRaw('length(code) = 5')->count(), LedgerAccount::whereRaw('length(code) = 8')->count()]);
        $pig = $this->account('22-06-01');
        $this->assertSame(['Pig iron', 'ASSET', '22-06'], [$pig->name, $pig->type, $pig->parent->code]);
        $this->assertSame(['Stock in trade', 'Current Assets'], [$pig->parent->report_line, $pig->parent->parent->name]);
        $this->assertTrue($this->account('42-01-03')->is_charge);
        $this->assertNotNull($this->account('22-02-01')->control);
        $this->assertSame('31', LedgerAccount::whereNull('parent_id')->orderBy('sort_order')->value('code'));
    }

    public function test_a_new_account_takes_the_next_free_code_in_its_section(): void
    {
        Livewire::test('ledger::chart')
            ->call('openAccount', '')
            ->set('m.cat', '22')->set('m.sec', '22-07')->set('m.name', 'Chromite sand')->set('m.desc', 'For heavy-section cores')
            ->assertSee('22-07-06')->assertSee('Saves as 22-07-06 Chromite sand.')
            ->call('saveDialog')
            ->assertSet('m', [])->assertSee('Added 22-07-06 Chromite sand.');

        $a = $this->account('22-07-06');
        $this->assertSame(['Chromite sand', 'ASSET', '22-07', false], [$a->name, $a->type, $a->parent->code, $a->is_charge]);
    }

    public function test_a_duplicate_name_in_the_section_cannot_be_saved(): void
    {
        Livewire::test('ledger::chart')
            ->call('openAccount', '22-07')->set('m.name', 'silica sand')
            ->assertSee('An account called silica sand already exists in 22-07.')
            ->call('saveDialog');

        $this->assertNull(LedgerAccount::where('code', '22-07-06')->first());
    }

    public function test_a_new_section_takes_the_next_code_and_needs_a_report_line(): void
    {
        $c = Livewire::test('ledger::chart')->call('openSection', '22')->set('m.name', 'Consignment Stock')
            ->assertSee('Pick a report line.')->call('saveDialog');
        $this->assertNull(LedgerAccount::where('code', '22-11')->first());

        $c->set('m.line', 'Stock in trade')->assertSee('Its first account will be 22-11-01.')->call('saveDialog');

        $s = $this->account('22-11');
        $this->assertSame(['Consignment Stock', 'Stock in trade', '22'], [$s->name, $s->report_line, $s->parent->code]);
    }

    public function test_an_account_with_postings_keeps_its_place_cannot_be_deleted_or_deactivated_but_can_be_renamed(): void
    {
        $this->postSale();
        $sales = $this->account('41-01-01');

        Livewire::test('ledger::chart')->call('openAccountEdit', $sales->id)
            ->assertSee('Locked: the account has postings. Renaming is fine.')
            ->call('setInactive', true)->assertSet('m.inactive', false)
            ->call('deleteFromDialog')
            ->set('m.name', 'Casting sales, local')->set('m.dc', true)->call('saveDialog');

        $sales->refresh();
        $this->assertSame(['41-01-01', 'Casting sales, local', true, true], [$sales->code, $sales->name, $sales->is_charge, $sales->is_active]);
    }

    public function test_an_unused_account_can_be_deactivated_or_deleted(): void
    {
        $a = $this->account('61-04-01');

        Livewire::test('ledger::chart')->call('openAccountEdit', $a->id)->call('setInactive', true)->call('saveDialog');
        $this->assertFalse($a->fresh()->is_active);

        Livewire::test('ledger::chart')->call('openAccountEdit', $a->id)->assertSee('Not used yet, so it can be deleted.')->call('deleteFromDialog');
        $this->assertModelMissing($a);
    }

    public function test_bulk_delete_removes_unused_ticked_accounts_and_keeps_used_ones(): void
    {
        $this->postSale();
        $unused = $this->account('61-04-02');
        $used = $this->account('41-01-01');

        Livewire::test('ledger::chart')->call('tick', $unused->id)->call('tick', $used->id)->call('deleteTicked')
            ->assertSee('Deleted 61-04-02. 1 kept: they have postings or a balance. Deactivate them instead.');

        $this->assertModelMissing($unused);
        $this->assertModelExists($used);
    }

    public function test_an_account_a_posting_rule_uses_cannot_be_deleted(): void
    {
        $bank = $this->account('22-01-03');

        Livewire::test('ledger::chart')->call('openAccountEdit', $bank->id)->assertSee('a posting rule uses it')->call('deleteFromDialog');

        $this->assertModelExists($bank);
    }

    public function test_a_section_with_accounts_cannot_be_deleted_and_its_line_locks_once_posted(): void
    {
        $this->postSale();
        $section = $this->account('41-01');

        Livewire::test('ledger::sections')->call('openSectionEdit', $section->id)
            ->assertSee('Locked: accounts in this section have postings.')
            ->assertSee('Delete: has 2 accounts. Delete or deactivate them first.')
            ->call('deleteFromDialog')
            ->set('m.name', 'Casting Sales and Exports')->set('m.line', 'Other income')->call('saveDialog');

        $section->refresh();
        $this->assertSame(['Casting Sales and Exports', 'Revenue'], [$section->name, $section->report_line]);
    }

    public function test_up_and_down_change_the_order_of_sections_but_not_their_codes(): void
    {
        $cash = $this->account('22-01');
        $debtors = $this->account('22-02');

        Livewire::test('ledger::sections')->call('move', $debtors->id, -1)->call('move', $debtors->id, -1);

        $this->assertSame(['22-02', '22-01', '22-03'], LedgerAccount::where('parent_id', $cash->parent_id)->orderBy('sort_order')->limit(3)->pluck('code')->all());
    }

    public function test_search_finds_a_code_or_a_name_and_inactive_accounts_show_on_request(): void
    {
        Livewire::test('ledger::chart')
            ->set('q', '22-06')->assertSee('Steel scrap')->assertDontSee('Silica sand')
            ->set('q', 'borings')->assertSee('Cast iron borings')->assertDontSee('Steel scrap')
            ->set('q', 'HBL collection')->assertDontSee('HBL collection a/c')
            ->set('showInactive', true)->assertSee('HBL collection a/c');
    }

    public function test_the_chart_sections_and_export_open(): void
    {
        $this->get(route('ledger.accounts.index'))->assertOk()->assertSee('+ Nominal account')->assertSee('Capital &amp; Reserves', false);
        $this->get(route('ledger.sections.index'))->assertOk()->assertSee('Up and Down set the order of sections on reports and in pickers.');
        $this->get(route('ledger.accounts.export'))->assertOk()->assertDownload();
    }
}
