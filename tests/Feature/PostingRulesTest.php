<?php

namespace Tests\Feature;

use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Design 19g: which account each document posts to, with a live preview, applied to documents posted from then on. */
class PostingRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
    }

    public function test_a_changed_rule_shows_in_the_preview_saves_and_posts_the_next_document_to_the_new_account(): void
    {
        $customer = Customer::create(['code' => 'MER', 'name' => 'Meridian Auto Systems']);
        $invoices = app(InvoiceService::class);
        $first = $invoices->issue($invoices->saveDraft(['customer_id' => $customer->id, 'invoice_date' => '2026-10-31'], [['description' => 'Bracket', 'quantity' => 1, 'unit_price' => 2455000]]));
        $export = LedgerAccount::where('code', '41-01-02')->sole();

        Livewire::test('ledger::posting-rules')
            ->assertSee('Preview · '.$first->invoice_number)->assertSee('Casting sales')->assertSee('Meridian Auto Systems')->assertSee('Heat close')
            ->set('roles.sales', $export->id)->assertSee('Export casting sales (zero-rated)')
            ->call('save')->assertSee('Posting rules saved.');

        $this->assertSame($export->id, LedgerSettings::account('sales')->id);
        $second = $invoices->issue($invoices->saveDraft(['customer_id' => $customer->id, 'invoice_date' => '2026-10-31'], [['description' => 'Bracket', 'quantity' => 1, 'unit_price' => 100]]));
        $this->assertSame(-100.0, $export->balance());
        $this->assertSame(-2455000.0, LedgerAccount::where('code', '41-01-01')->sole()->balance());   // already posted: not re-posted
        $this->assertSame(2, Journal::count());
        $this->get(route('ledger.settings.posting-rules'))->assertOk()->assertSee('Posting rules');
        $this->assertNotNull($second);
    }

    public function test_discard_puts_the_saved_rules_back(): void
    {
        $saved = LedgerSettings::account('receivables')->id;

        Livewire::test('ledger::posting-rules')->call('pick', 'receipt')->set('roles.receivables', LedgerAccount::where('code', '22-02-03')->value('id'))
            ->call('discard')->assertSet('roles.receivables', $saved);
    }
}
