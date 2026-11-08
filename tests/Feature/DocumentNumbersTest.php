<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Services\InvoiceService;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\DocumentNumbers;
use App\Support\GlobalSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentNumbersTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        $this->customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
    }

    private function issue(string $date): Invoice
    {
        $service = app(InvoiceService::class);

        return $service->issue($service->saveDraft(['customer_id' => $this->customer->id, 'invoice_date' => $date], [['description' => 'Casting', 'quantity' => 1, 'unit_price' => 100]]));
    }

    public function test_numbers_are_a_letter_and_five_digits_from_10000_each_type_its_own_series(): void
    {
        $this->assertSame(['I-10000', 'I-10001'], [$this->issue('2026-11-01')->invoice_number, $this->issue('2026-11-02')->invoice_number]);
        $this->assertSame('I-10002', DocumentNumbers::preview('invoice'));
        $this->assertSame('C-10000', DocumentNumbers::preview('credit_note'));

        AppSetting::setValue('numbers.prefix.invoice', 'inv');
        $this->assertSame('INV-10000', $this->issue('2026-11-03')->invoice_number);
    }

    public function test_renumbering_runs_in_date_order_keeps_the_old_numbers_and_can_be_undone(): void
    {
        $late = $this->issue('2026-11-05');
        $early = $this->issue('2026-10-01');
        DB::table('invoices')->where('id', $late->id)->update(['invoice_number' => 'INV-2026-0002']);
        DB::table('invoices')->where('id', $early->id)->update(['invoice_number' => 'INV-2026-0001']);

        $this->assertSame(['INV-2026-0001' => 'I-10000', 'INV-2026-0002' => 'I-10001'], DocumentNumbers::renumber('invoice'));
        $this->assertSame('I-10001', DocumentNumbers::current('INV-2026-0002'));
        $this->assertSame('I-10002', $this->issue('2026-11-09')->invoice_number);

        $found = GlobalSearch::search('INV-2026-0002', null, auth()->user());
        $this->assertSame(['I-10001', '(was INV-2026-0002)', route('invoices.show', $late)], [$found[0]['code'], $found[0]['name'], $found[0]['url']]);
        $this->assertSame(route('invoices.show', $late), GlobalSearch::clearWinner($found));

        DocumentNumbers::restore('invoices', 'invoice_number');
        $this->assertSame(['INV-2026-0002', 'INV-2026-0001'], [$late->fresh()->invoice_number, $early->fresh()->invoice_number]);
    }
}
