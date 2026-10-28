<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceTemplate;
use App\Domains\Finance\Services\InvoiceLayouts;
use App\Domains\Finance\Services\InvoiceService;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTemplateBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
    }

    /** @return array<string, mixed> */
    private function layout(): array
    {
        return app(InvoiceLayouts::class)->builtIn('SIMPLE');
    }

    private function save(array $layout, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('invoice-templates.store'), $extra + ['name' => 'Our invoice', 'applies_to' => 'BOTH', 'layout' => json_encode($layout)]);
    }

    public function test_the_builder_opens_blank_or_from_a_ready_made_layout_and_lists_templates(): void
    {
        $this->get(route('invoice-templates.index'))->assertOk()->assertSee('Sales tax invoice')->assertSee('None yet');
        $this->get(route('invoice-templates.create'))->assertOk()->assertSee('Above the lines')->assertSee('Open in a dialog')->assertSee('tb-preview-dialog', false);
        $this->get(route('invoice-templates.create', ['from' => 'TAX']))->assertOk()->assertSee('Sales tax invoice (copy)')->assertSee('Tariff code');
    }

    public function test_a_template_is_saved_edited_and_copied(): void
    {
        $layout = $this->layout();
        $this->save($layout)->assertSessionHasNoErrors();
        $template = InvoiceTemplate::sole();
        $this->assertSame('Our invoice', $template->name);
        $this->assertSame($layout['body']['columns'][0]['key'], $template->layout['body']['columns'][0]['key']);

        $layout['body']['columns'][0]['label'] = 'What we made';
        $this->put(route('invoice-templates.update', $template), ['name' => 'Renamed', 'applies_to' => 'INVOICE', 'layout' => json_encode($layout)])->assertSessionHasNoErrors();
        $template->refresh();
        $this->assertSame(['Renamed', 'INVOICE', 'What we made'], [$template->name, $template->applies_to, $template->layout['body']['columns'][0]['label']]);

        $this->get(route('invoice-templates.edit', $template))->assertOk()->assertSee('What we made');
        $this->get(route('invoice-templates.create', ['from' => $template->key()]))->assertOk()->assertSee('Renamed (copy)');
    }

    public function test_a_layout_that_is_not_allowed_is_refused_with_a_reason_and_nothing_is_saved(): void
    {
        $layout = $this->layout();
        $layout['header'] = [[['kind' => 'script', 'align' => 'left']]];

        $this->save($layout)->assertSessionHasErrors('layout');
        $this->save($this->layout(), ['applies_to' => 'EVERYTHING'])->assertSessionHasErrors('applies_to');
        $this->save($this->layout(), ['layout' => 'not json'])->assertSessionHasErrors('layout');
        $this->assertSame(0, InvoiceTemplate::count());
    }

    public function test_the_preview_draws_the_unsaved_layout_over_a_sample_or_a_real_invoice_and_says_what_is_wrong(): void
    {
        $layout = $this->layout();
        $layout['body']['columns'][0]['label'] = 'Heading typed a moment ago';

        $this->post(route('invoice-templates.preview'), ['layout' => json_encode($layout)])->assertOk()->assertSee('Heading typed a moment ago')->assertSee('Sample Pumps Ltd');

        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $invoice = app(InvoiceService::class)->issue(app(InvoiceService::class)->saveDraft(['customer_id' => $customer->id], [['description' => 'Real casting', 'quantity' => 1, 'unit_price' => 10]]));
        $this->post(route('invoice-templates.preview'), ['layout' => json_encode($layout), 'invoice' => $invoice->id])->assertOk()->assertSee('Real casting')->assertDontSee('Sample Pumps Ltd');

        $layout['body']['columns'] = [['key' => 'net', 'show' => false]];
        $this->post(route('invoice-templates.preview'), ['layout' => json_encode($layout)])->assertOk()->assertSee('Show at least one column');
        $this->assertSame(0, InvoiceTemplate::count(), 'previewing saves nothing');
    }

    public function test_a_template_in_use_is_switched_off_not_deleted_and_an_unused_one_is_deleted(): void
    {
        $used = InvoiceTemplate::factory()->create();
        $free = InvoiceTemplate::factory()->create();
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $invoice = app(InvoiceService::class)->saveDraft(['customer_id' => $customer->id], [['description' => 'Casting', 'quantity' => 1, 'unit_price' => 10]]);
        $invoice->update(['print_template' => $used->key()]);

        $this->delete(route('invoice-templates.destroy', $used))->assertSessionHasErrors('template');
        $this->assertNotNull($used->fresh());
        $this->delete(route('invoice-templates.destroy', $free))->assertRedirect(route('invoice-templates.index'));
        $this->assertNull($free->fresh());

        $this->post(route('invoice-templates.toggle', $used))->assertSessionHasNoErrors();
        $this->assertFalse($used->fresh()->is_active);
        $this->assertNotSame($used->key(), app(\App\Domains\Finance\Services\InvoiceTemplates::class)->keyFor($invoice->fresh()));
    }

    public function test_only_people_who_manage_finance_can_change_templates(): void
    {
        config(['foundry.require_login' => true]);
        $nobody = User::factory()->create(['is_active' => true, 'must_change_password' => false]);

        $this->actingAs($nobody)->post(route('invoice-templates.store'), ['name' => 'x', 'applies_to' => 'BOTH', 'layout' => json_encode($this->layout())])->assertForbidden();
        $template = InvoiceTemplate::factory()->create();
        $this->actingAs($nobody)->post(route('invoice-templates.toggle', $template))->assertForbidden();
        $this->actingAs($nobody)->delete(route('invoice-templates.destroy', $template))->assertForbidden();
        $this->assertTrue($template->fresh()->is_active);
        $this->assertSame(1, InvoiceTemplate::count());
    }
}
