<?php

namespace Tests\Feature;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceTemplate;
use App\Domains\Finance\Models\TaxRate;
use App\Domains\Finance\Services\InvoiceLayouts;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Finance\Services\InvoiceTemplates;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\Formats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InvoiceLayoutsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
        $this->customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'billing_address' => "1 Foundry Road\nLahore"]);
    }

    /** @param list<array<string, mixed>> $lines */
    private function invoice(array $lines = [], array $head = []): Invoice
    {
        $rate = TaxRate::create(['name' => 'GST 10', 'rate_percent' => 10, 'is_active' => true]);
        $lines = $lines ?: [['description' => 'Pump casing', 'quantity' => 2, 'uom' => 'pcs', 'unit_price' => 100, 'discount_percent' => 10, 'tariff_code' => '7325.1000']];

        return app(InvoiceService::class)->saveDraft($head + ['customer_id' => $this->customer->id],
            array_map(fn ($l) => $l + ['tax_rate_id' => $rate->id], $lines));
    }

    private function print(Invoice $invoice, string $template): string
    {
        return $this->get(route('invoices.print', ['invoice' => $invoice, 'template' => $template]))->assertOk()->getContent();
    }

    public function test_the_tax_layout_shows_codes_and_per_line_tax_and_the_simple_one_does_not(): void
    {
        $invoice = $this->invoice();

        $tax = $this->print($invoice, 'TAX');
        $ten = Formats::number(10, 2).'%';   // decimal places follow the site-wide setting
        foreach (['Sales tax invoice', 'Tariff code', '7325.1000', 'Tax %', $ten, 'Value incl. tax', 'Acme Pumps', '1 Foundry Road'] as $text) {
            $this->assertStringContainsString($text, $tax, "missing {$text}");
        }

        $simple = $this->print($invoice, 'SIMPLE');
        $this->assertStringContainsString('Pump casing', $simple);
        $this->assertStringNotContainsString('Tariff code', $simple);
        $this->assertStringNotContainsString('7325.1000', $simple);
        $this->assertStringContainsString($ten, $simple, 'the discount column shows the discount');
    }

    public function test_a_credit_note_keeps_its_own_title_in_a_layout_with_another_title(): void
    {
        $service = app(InvoiceService::class);
        $invoice = $service->issue($this->invoice());
        $note = $service->creditNote($invoice, [['description' => 'Pump casing', 'quantity' => 1, 'unit_price' => 100]], 'Rejected');

        $html = $this->print($note, 'TAX');
        $this->assertStringContainsString('Credit note', $html);
        $this->assertStringNotContainsString('Sales tax invoice', $html);
    }

    public function test_what_comes_from_the_invoice_and_from_a_layouts_own_text_is_escaped(): void
    {
        $this->customer->update(['name' => '<b>Bold</b> Pumps']);
        $layout = app(InvoiceLayouts::class)->builtIn('SIMPLE');
        $layout['footer'] = [[['kind' => 'text', 'align' => 'left', 'text' => '<script>alert(1)</script> {customer}', 'size' => 12]]];
        $template = InvoiceTemplate::factory()->create(['layout' => app(InvoiceLayouts::class)->normalize($layout)]);

        $html = $this->print($this->invoice(), $template->key());

        $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt; Pumps', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<b>Bold</b>', $html);
    }

    public function test_a_long_invoice_has_one_header_one_footer_and_one_set_of_totals_for_the_printer_to_repeat(): void
    {
        $lines = array_map(fn ($i) => ['description' => "Casting {$i}", 'quantity' => 1, 'unit_price' => 10], range(1, 60));
        $html = $this->print($this->invoice($lines), 'TAX');

        $this->assertSame(1, substr_count($html, '<thead>'));
        $this->assertSame(1, substr_count($html, '<tfoot>'));
        $this->assertSame(60, substr_count($html, 'Casting '));
        $this->assertSame(1, substr_count($html, 'Total including tax'));
        $this->assertStringContainsString('@bottom-right', $html);
        $this->assertStringContainsString('counter(pages)', $html);
    }

    public function test_a_custom_layout_follows_its_column_order_labels_and_widths(): void
    {
        $layout = app(InvoiceLayouts::class)->builtIn('SIMPLE');
        $layout['body']['columns'] = [
            ['key' => 'net', 'label' => 'Amount due', 'show' => true, 'width' => 20],
            ['key' => 'description', 'label' => 'What we made', 'show' => true, 'width' => 0],
            ['key' => 'tariff', 'label' => 'Tariff code', 'show' => false, 'width' => 0],
        ];
        $layout['body']['terms'] = 'Pay {terms}. Invoice {invoice_number}.';
        $template = InvoiceTemplate::factory()->create(['layout' => app(InvoiceLayouts::class)->normalize($layout)]);

        $html = $this->print($this->invoice(), $template->key());

        $this->assertLessThan(strpos($html, 'What we made'), strpos($html, 'Amount due'));
        $this->assertStringContainsString('width:20%', $html);
        $this->assertStringNotContainsString('7325.1000', $html);
        $this->assertStringContainsString('Pay as agreed. Invoice (draft).', $html);
    }

    public function test_a_layout_with_an_unknown_block_column_or_nothing_to_show_is_refused(): void
    {
        $engine = app(InvoiceLayouts::class);
        $good = $engine->builtIn('SIMPLE');
        $this->assertSame($good['body']['columns'][0]['key'], $engine->normalize($good)['body']['columns'][0]['key']);

        $bad = [
            'block' => fn ($l) => array_replace_recursive($l, ['header' => [[['kind' => 'script', 'align' => 'left']]]]),
            'align' => fn ($l) => array_replace_recursive($l, ['header' => [[['kind' => 'text', 'align' => 'up']]]]),
            'too many blocks' => fn ($l) => array_replace($l, ['header' => [array_fill(0, 5, ['kind' => 'line', 'align' => 'left'])]]),
            'column' => fn ($l) => array_replace_recursive($l, ['body' => ['columns' => [['key' => 'secret', 'show' => true]]]]),
            'nothing shown' => fn ($l) => array_replace($l, ['body' => ['columns' => [['key' => 'net', 'show' => false]], 'totals' => []]]),
            'totals' => fn ($l) => array_replace_recursive($l, ['body' => ['totals' => ['everything']]]),
            'page' => fn ($l) => array_replace_recursive($l, ['page' => ['size' => 'A0']]),
        ];
        foreach ($bad as $name => $change) {
            try {
                $engine->normalize($change($good));
                $this->fail("{$name} should have been refused");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_custom_layout_can_be_chosen_switched_off_and_limited_to_one_kind_of_document(): void
    {
        $templates = app(InvoiceTemplates::class);
        $invoice = $this->invoice();
        $mine = InvoiceTemplate::factory()->create(['name' => 'Our layout']);
        $creditsOnly = InvoiceTemplate::factory()->create(['name' => 'Credits layout', 'applies_to' => 'CREDIT_NOTE']);

        $this->assertArrayHasKey($mine->key(), $templates->options(false));
        $this->assertArrayNotHasKey($creditsOnly->key(), $templates->options(false));
        $this->assertArrayHasKey($creditsOnly->key(), $templates->options(true));

        $this->put(route('invoices.template', $invoice), ['print_template' => $mine->key()])->assertSessionHasNoErrors();
        $this->assertSame($mine->key(), $templates->keyFor($invoice->fresh()));
        $this->put(route('invoices.template', $invoice), ['print_template' => $creditsOnly->key()])->assertSessionHasErrors('print_template');

        $mine->update(['is_active' => false]);
        $this->assertSame('STANDARD', $templates->keyFor($invoice->fresh()), 'a layout switched off falls back');
        $this->get(route('invoices.print', $invoice))->assertOk();
    }
}
