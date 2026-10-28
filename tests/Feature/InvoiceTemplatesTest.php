<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Finance\Services\InvoiceTemplates;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTemplatesTest extends TestCase
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
        // a second layout, so there is something to choose (it draws with the standard body)
        app(InvoiceTemplates::class)->register('ALT', 'Alternative', 'finance::invoices.templates.standard');
    }

    private function invoice(): Invoice
    {
        return app(InvoiceService::class)->saveDraft(['customer_id' => $this->customer->id], [['description' => 'Casting', 'quantity' => 2, 'unit_price' => 50]]);
    }

    public function test_an_invoice_prints_with_the_default_layout_until_it_or_the_settings_choose_another(): void
    {
        $invoice = $this->invoice();
        $templates = app(InvoiceTemplates::class);

        $this->assertSame('STANDARD', $templates->keyFor($invoice));
        $this->get(route('invoices.print', $invoice))->assertOk()->assertViewIs('finance::invoices.print')->assertSee('Casting');

        $this->put(route('finance.settings.update'), ['due_days' => 30, 'invoice_template' => 'ALT'])->assertSessionHasNoErrors();
        $this->assertSame('ALT', $templates->keyFor($invoice->fresh()));

        $this->put(route('invoices.template', $invoice), ['print_template' => 'STANDARD'])->assertSessionHasNoErrors();
        $this->assertSame('STANDARD', $templates->keyFor($invoice->fresh()), 'the invoice\'s own choice wins over the default');

        $this->put(route('invoices.template', $invoice), ['print_template' => ''])->assertSessionHasNoErrors();
        $this->assertSame('ALT', $templates->keyFor($invoice->fresh()));
    }

    public function test_an_unknown_layout_is_refused_and_a_removed_one_falls_back_to_the_default(): void
    {
        $invoice = $this->invoice();

        $this->put(route('invoices.template', $invoice), ['print_template' => 'NOPE'])->assertSessionHasErrors('print_template');

        $invoice->update(['print_template' => 'GONE']);
        $this->assertSame('STANDARD', app(InvoiceTemplates::class)->keyFor($invoice->fresh()));
        $this->get(route('invoices.print', $invoice))->assertOk();
    }

    public function test_the_layout_can_be_changed_after_the_invoice_is_issued_and_the_page_offers_the_choice(): void
    {
        $service = app(InvoiceService::class);
        $invoice = $service->issue($this->invoice());

        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Print layout')->assertSee('Alternative');
        $this->put(route('invoices.template', $invoice), ['print_template' => 'ALT'])->assertSessionHasNoErrors();
        $this->assertSame('ALT', $invoice->fresh()->print_template);
        $this->assertSame('ISSUED', $invoice->fresh()->status);
    }

    public function test_issue_and_print_issues_the_draft_and_opens_the_print_page(): void
    {
        $invoice = $this->invoice();

        $this->post(route('invoices.issue', $invoice), ['print' => 1])->assertRedirect(route('invoices.print', $invoice));
        $this->assertSame('ISSUED', $invoice->fresh()->status);

        $plain = $this->invoice();
        $this->from(route('invoices.show', $plain))->post(route('invoices.issue', $plain))->assertRedirect(route('invoices.show', $plain));
    }

    public function test_a_credit_note_prints_in_the_chosen_layout(): void
    {
        $service = app(InvoiceService::class);
        $invoice = $service->issue($this->invoice());
        $note = $service->creditNote($invoice, [['description' => 'Casting', 'quantity' => 1, 'unit_price' => 50]], 'Rejected');

        $this->put(route('invoices.template', $note), ['print_template' => 'ALT'])->assertSessionHasNoErrors();
        $this->get(route('invoices.print', $note))->assertOk()->assertSee('Credit note');
    }
}
