<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceTemplate;
use App\Models\AppSetting;

/**
 * The print layouts an invoice or credit note can use, by key. A layout is either a view that draws the body of the page (the
 * standard one, or one a module registers with {@see register()}), or data drawn by the shared layout view: the ready-made tax and
 * simple layouts and the ones finance staff build, which are kept as `custom:<id>`. The wrapper (title, styles, print link) is shared.
 */
class InvoiceTemplates
{
    public const DEFAULT = 'finance.invoice_template';

    public const LAYOUT_VIEW = 'finance::invoices.templates.layout';

    /** @var array<string, array{label: string, view: string, layout?: string}> */
    private array $templates = [
        'STANDARD' => ['label' => 'Standard invoice', 'view' => 'finance::invoices.templates.standard'],
        'TAX' => ['label' => 'Sales tax invoice', 'view' => self::LAYOUT_VIEW, 'layout' => 'TAX'],
        'SIMPLE' => ['label' => 'Simple invoice', 'view' => self::LAYOUT_VIEW, 'layout' => 'SIMPLE'],
    ];

    public function register(string $key, string $label, string $view): void
    {
        $this->templates[$key] = ['label' => $label, 'view' => $view];
    }

    /** The active layouts built on the builder. @return \Illuminate\Support\Collection<string, InvoiceTemplate> Keyed by their key. */
    public function custom(): \Illuminate\Support\Collection
    {
        return InvoiceTemplate::where('is_active', true)->orderBy('name')->get()->keyBy(fn (InvoiceTemplate $t) => $t->key());
    }

    public function has(string $key): bool
    {
        return isset($this->templates[$key]) || ($this->isCustomKey($key) && $this->custom()->has($key));
    }

    /**
     * The choices, key => label. Given whether the document is a credit note, a built layout meant for the other kind is left out.
     *
     * @return array<string, string>
     */
    public function options(?bool $creditNote = null): array
    {
        $options = array_map(fn (array $t) => $t['label'], $this->templates);
        foreach ($this->custom() as $key => $template) {
            if ($creditNote === null || $template->appliesTo($creditNote)) {
                $options[$key] = $template->name;
            }
        }

        return $options;
    }

    /** The template the foundry prints with unless an invoice says otherwise. */
    public function default(): string
    {
        $key = (string) AppSetting::getValue(self::DEFAULT, 'STANDARD');

        return $this->has($key) ? $key : 'STANDARD';
    }

    /** The key an invoice prints with: its own choice while that layout still exists and suits it, else the default, else the standard one. */
    public function keyFor(Invoice $invoice): string
    {
        $options = $this->options($invoice->isCreditNote());
        foreach ([$invoice->print_template, $this->default()] as $key) {
            if ($key && isset($options[$key])) {
                return $key;
            }
        }

        return 'STANDARD';
    }

    /**
     * What the print page needs to draw a layout: the view, and for a data layout the layout itself.
     *
     * @return array{view: string, layout: ?array<string, mixed>}
     */
    public function render(string $key): array
    {
        if ($this->isCustomKey($key) && ($template = $this->custom()->get($key))) {
            return ['view' => self::LAYOUT_VIEW, 'layout' => $template->layout];
        }
        $entry = $this->templates[$key] ?? $this->templates['STANDARD'];

        return ['view' => $entry['view'], 'layout' => isset($entry['layout']) ? app(InvoiceLayouts::class)->builtIn($entry['layout']) : null];
    }

    private function isCustomKey(string $key): bool
    {
        return str_starts_with($key, 'custom:');
    }
}
