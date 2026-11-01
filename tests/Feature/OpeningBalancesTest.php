<?php

namespace Tests\Feature;

use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\OpeningBalances;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** Design 19f: opening balances at go-live, with receivables from open invoices, typed balances, the difference and the lock. */
class OpeningBalancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        AppSetting::setValue('books.year_start_month', '7');
    }

    private function id(string $code): string
    {
        return LedgerAccount::where('code', $code)->value('id');
    }

    public function test_locking_posts_typed_balances_open_invoices_by_customer_and_the_difference(): void
    {
        OpeningBalances::setGoLive('2026-07-01');
        $kovar = Customer::create(['code' => 'KOV', 'name' => 'Kovar Pumps']);
        $invoices = app(InvoiceService::class);
        $invoices->issue($invoices->saveDraft(['customer_id' => $kovar->id, 'invoice_date' => '2026-06-20'], [['description' => 'Pump casing', 'quantity' => 1, 'unit_price' => 8000]]));
        $this->assertSame(0, Journal::count());   // dated before go-live: carried in the opening voucher

        Livewire::test('ledger::opening-balances')
            ->set('newAccount', $this->id('22-01-01'))->set('newDebit', '62000')->call('add')
            ->set('newAccount', $this->id('31-01-01'))->set('newCredit', '50000')->call('add')
            ->set('newAccount', $this->id('22-02-01'))->set('newDebit', '1')->call('add')->assertSee('comes from its documents and cannot be typed')
            ->assertSee('1 open sales invoices')->assertSee('Difference, posted here until you move it')
            ->call('lock')->assertSee('Locked. Posted OB-26-0001.');

        $ob = Journal::sole();
        $this->assertSame(['OB-26-0001', '2026-06-30', 70000.0], [$ob->number, $ob->journal_date->toDateString(), $ob->total]);
        $bal = fn (string $code) => LedgerAccount::where('code', $code)->firstOrFail()->balance();
        $this->assertSame([62000.0, 8000.0, -50000.0, -20000.0], [$bal('22-01-01'), $bal('22-02-01'), $bal('31-01-01'), $bal('31-03-02')]);
        $this->assertSame($kovar->id, $ob->lines()->where('account_id', $this->id('22-02-01'))->value('party_id'));

        Livewire::test('ledger::opening-balances')->assertSee('Locked')->call('lock')->assertSee('already locked');
        $this->assertSame(1, Journal::count());
    }

    public function test_balances_can_be_imported_from_a_csv(): void
    {
        $csv = UploadedFile::fake()->createWithContent('ob.csv', "code,debit,credit\n22-01-03,14880000,\n12-08-01,,45000000\n99-99-99,1,\n");

        Livewire::test('ledger::opening-balances')->set('csv', $csv)->assertSee('Imported 2 balances.')->assertSee('99-99-99: no such account');

        $this->get(route('ledger.opening-balances'))->assertOk()->assertSee('Running finance: HBL')->assertSee('Not locked');
    }
}
