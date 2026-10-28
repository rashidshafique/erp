<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\Invoice;
use App\Models\AppSetting;

/**
 * The print layouts an invoice or credit note can use, by key. Each is a view that draws the body of the page; the wrapper
 * (title, styles, print link) is shared. A module adds its own from its service provider with {@see register()}.
 */
class InvoiceTemplates
{
    public const DEFAULT = 'finance.invoice_template';

    /** @var array<string, array{label: string, view: string}> */
    private array $templates = [
        'STANDARD' => ['label' => 'Standard invoice', 'view' => 'finance::invoices.templates.standard'],
    ];

    public function register(string $key, string $label, string $view): void
    {
        $this->templates[$key] = ['label' => $label, 'view' => $view];
    }

    public function has(string $key): bool
    {
        return isset($this->templates[$key]);
    }

    /** @return array<string, string> Key => label. */
    public function options(): array
    {
        return array_map(fn (array $t) => $t['label'], $this->templates);
    }

    /** The template the foundry prints with unless an invoice says otherwise. */
    public function default(): string
    {
        $key = (string) AppSetting::getValue(self::DEFAULT, 'STANDARD');

        return $this->has($key) ? $key : 'STANDARD';
    }

    /** The key an invoice prints with: its own choice while that template still exists, else the default. */
    public function keyFor(Invoice $invoice): string
    {
        return $invoice->print_template && $this->has($invoice->print_template) ? $invoice->print_template : $this->default();
    }

    public function viewFor(string $key): string
    {
        return $this->templates[$this->has($key) ? $key : 'STANDARD']['view'];
    }
}
