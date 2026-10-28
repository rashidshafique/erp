<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceLine;
use App\Support\Formats;
use App\Support\UiSettings;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Invoice layouts as data. A layout names the page, the blocks of the header, the intro (first page only) and the footer, and the
 * columns, totals and terms of the body. Everything drawn comes from the invoice and is escaped here, so a layout never holds HTML.
 *
 * @phpstan-type Block array{kind: string, align: string, text: string, size: int}
 * @phpstan-type Column array{key: string, label: string, show: bool, width: int}
 * @phpstan-type Layout array{page: array{size: string, orientation: string, margin_mm: int, font_pt: int}, header: list<list<Block>>, intro: list<list<Block>>, footer: list<list<Block>>, body: array{columns: list<Column>, totals: list<string>, terms: string}}
 */
class InvoiceLayouts
{
    /** What a header, intro or footer block can show. */
    public const BLOCKS = [
        'logo' => 'Logo', 'company' => 'Company name (and address lines)', 'company_regs' => 'Our tax registrations', 'title' => 'Document title',
        'number' => 'Document number', 'dates' => 'Dates', 'customer' => 'Customer name and address', 'customer_regs' => 'Customer tax registrations',
        'reference' => 'Customer reference', 'terms' => 'Payment terms', 'text' => 'Free text', 'line' => 'Line', 'spacer' => 'Space', 'page' => 'Page x of y (footer, current Chrome and Edge)',
    ];

    /** The columns of the lines table, with the label they start with. */
    public const COLUMNS = [
        'no' => '#', 'description' => 'Description', 'tariff' => 'Tariff code', 'qty' => 'Quantity', 'unit' => 'Unit', 'price' => 'Unit price',
        'discount' => 'Discount', 'net' => 'Value excl. tax', 'tax_pct' => 'Tax %', 'tax_amt' => 'Tax', 'gross' => 'Value incl. tax',
    ];

    public const TOTALS = ['subtotal' => 'Value excluding tax', 'discount' => 'Discounts given', 'tax' => 'Tax (each tax on its own row)', 'total' => 'Total', 'withheld' => 'Tax withheld by the customer', 'payable' => 'Payable to us'];

    public const SIZES = ['A4' => 'A4', 'LETTER' => 'Letter'];

    public const FIELDS = ['invoice_number', 'date', 'due_date', 'customer', 'customer_reference', 'total', 'currency', 'company', 'terms'];

    private const NUMERIC = ['qty', 'price', 'discount', 'net', 'tax_pct', 'tax_amt', 'gross'];

    /** @return Layout|null */
    public function builtIn(string $key): ?array
    {
        $b = fn (string $kind, string $align = 'left', string $text = '', int $size = 12) => ['kind' => $kind, 'align' => $align, 'text' => $text, 'size' => $size];
        $col = fn (string $key, bool $show = true, int $width = 0) => ['key' => $key, 'label' => self::COLUMNS[$key], 'show' => $show, 'width' => $width];
        $page = ['size' => 'A4', 'orientation' => 'portrait', 'margin_mm' => 14, 'font_pt' => 10];

        return match ($key) {
            'TAX' => ['page' => $page,
                'header' => [[$b('logo'), $b('company', 'right'), ], [$b('line')]],
                'intro' => [[$b('title', 'left', 'Sales tax invoice'), $b('number', 'right')], [$b('dates'), $b('company_regs', 'right')], [$b('customer'), $b('customer_regs', 'right')], [$b('reference'), $b('terms', 'right')]],
                'footer' => [[$b('line')], [$b('text', 'left', '{company} · {invoice_number}'), $b('page', 'right')]],
                'body' => ['columns' => [$col('no', true, 4), $col('description', true, 30), $col('tariff', true, 11), $col('qty'), $col('unit', false), $col('price'), $col('discount', false), $col('net'), $col('tax_pct'), $col('tax_amt'), $col('gross')],
                    'totals' => ['subtotal', 'tax', 'total', 'withheld', 'payable'], 'terms' => ''],
            ],
            'SIMPLE' => ['page' => $page,
                'header' => [[$b('company'), $b('logo', 'right')]],
                'intro' => [[$b('title', 'left', 'Invoice'), $b('number', 'right')], [$b('dates'), $b('customer', 'right')]],
                'footer' => [[$b('page', 'right')]],
                'body' => ['columns' => [$col('description', true, 46), $col('qty'), $col('price'), $col('discount'), $col('net')], 'totals' => ['subtotal', 'tax', 'total'], 'terms' => ''],
            ],
            default => null,
        };
    }

    /**
     * Checks a layout typed on the builder and returns it clean. Anything unknown, too long or the wrong shape is refused.
     *
     * @param  array<string, mixed>  $raw
     * @return Layout
     *
     * @throws InvalidArgumentException
     */
    public function normalize(array $raw): array
    {
        $page = (array) ($raw['page'] ?? []);
        $size = (string) ($page['size'] ?? 'A4');
        $orientation = (string) ($page['orientation'] ?? 'portrait');
        if (! isset(self::SIZES[$size]) || ! in_array($orientation, ['portrait', 'landscape'], true)) {
            throw new InvalidArgumentException('Choose a page size and orientation from the lists.');
        }
        $margin = (int) ($page['margin_mm'] ?? 14);
        $font = (int) ($page['font_pt'] ?? 10);
        if ($margin < 5 || $margin > 40 || $font < 7 || $font > 14) {
            throw new InvalidArgumentException('Margins run from 5 to 40 mm and text from 7 to 14 pt.');
        }

        $layout = ['page' => ['size' => $size, 'orientation' => $orientation, 'margin_mm' => $margin, 'font_pt' => $font]];
        foreach (['header', 'intro', 'footer'] as $part) {
            $rows = array_values((array) ($raw[$part] ?? []));
            if (count($rows) > 8) {
                throw new InvalidArgumentException("The {$part} can have at most 8 rows.");
            }
            $layout[$part] = array_map(fn ($row) => $this->normalizeRow((array) $row, $part), $rows);
        }

        $body = (array) ($raw['body'] ?? []);
        $columns = [];
        foreach ((array) ($body['columns'] ?? []) as $c) {
            $c = (array) $c;
            $key = (string) ($c['key'] ?? '');
            if (! isset(self::COLUMNS[$key]) || isset($columns[$key])) {
                throw new InvalidArgumentException('A column is unknown or listed twice.');
            }
            $label = trim((string) ($c['label'] ?? '')) ?: self::COLUMNS[$key];
            $width = (int) ($c['width'] ?? 0);
            if (mb_strlen($label) > 40 || $width < 0 || $width > 60) {
                throw new InvalidArgumentException('A column label can be 40 characters and a width up to 60 percent.');
            }
            $columns[$key] = ['key' => $key, 'label' => $label, 'show' => (bool) ($c['show'] ?? true), 'width' => $width];
        }
        if (! collect($columns)->contains('show', true)) {
            throw new InvalidArgumentException('Show at least one column.');
        }
        $totals = array_values(array_unique(array_map('strval', (array) ($body['totals'] ?? []))));
        if (array_diff($totals, array_keys(self::TOTALS)) !== []) {
            throw new InvalidArgumentException('A totals row is unknown.');
        }
        $terms = trim((string) ($body['terms'] ?? ''));
        if (mb_strlen($terms) > 2000) {
            throw new InvalidArgumentException('The terms can be 2000 characters.');
        }
        $layout['body'] = ['columns' => array_values($columns), 'totals' => $totals, 'terms' => $terms];

        return $layout;
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @return list<Block>
     */
    private function normalizeRow(array $row, string $part): array
    {
        $row = array_values($row);
        if ($row === [] || count($row) > 4) {
            throw new InvalidArgumentException("A row in the {$part} holds one to four blocks.");
        }

        return array_map(function ($b) use ($part) {
            $b = (array) $b;
            $kind = (string) ($b['kind'] ?? '');
            $align = (string) ($b['align'] ?? 'left');
            $text = trim((string) ($b['text'] ?? ''));
            if (! isset(self::BLOCKS[$kind]) || ! in_array($align, ['left', 'center', 'right'], true)) {
                throw new InvalidArgumentException("A block in the {$part} has an unknown kind or alignment.");
            }
            if (mb_strlen($text) > 500) {
                throw new InvalidArgumentException('A block\'s text can be 500 characters.');
            }

            return ['kind' => $kind, 'align' => $align, 'text' => $text, 'size' => max(2, min(80, (int) ($b['size'] ?? 12)))];
        }, $row);
    }

    /**
     * One block as safe HTML. Everything from the invoice is escaped; a layout's own text has its {fields} filled in and is escaped too.
     *
     * @param  Block  $block
     * @param  array{seller: array<string, string>, party: array<string, string>}  $registrations
     */
    public function block(array $block, Invoice $invoice, array $registrations): HtmlString
    {
        $e = fn ($v) => e((string) $v);
        $brand = UiSettings::brand();
        $money = fn ($v) => Formats::number((float) $v, 2);
        $regs = fn (array $list) => collect($list)->map(fn ($n, $l) => $e($l).': '.$e($n))->implode('<br>');
        $credit = $invoice->isCreditNote();

        $html = match ($block['kind']) {
            'logo' => $brand['logo'] ? '<img src="'.$e($brand['logo']).'" alt="" style="height:'.(int) $brand['height'].'px">' : '',
            'company' => '<strong>'.$e($brand['name']).'</strong>'.($block['text'] !== '' ? '<br>'.nl2br($e($this->fill($block['text'], $invoice))) : ''),
            'company_regs' => $regs($registrations['seller'] ?? []),
            'title' => '<span class="lay-title">'.$e($credit ? 'Credit note' : ($block['text'] !== '' ? $this->fill($block['text'], $invoice) : 'Invoice')).'</span>',
            'number' => $e(($credit ? 'Credit note no. ' : 'Invoice no. ').($invoice->invoice_number ?? '(draft)')),
            'dates' => $e('Date: '.$invoice->invoice_date->displayDate()).($invoice->due_date ? '<br>'.$e('Due: '.$invoice->due_date->displayDate()) : '').($invoice->original ? '<br>'.$e('Against invoice '.$invoice->original->invoice_number) : ''),
            'customer' => '<span class="muted">To</span><br><strong>'.$e($invoice->customer?->name).'</strong>'.($invoice->customer?->billing_address ? '<br>'.nl2br($e($invoice->customer->billing_address)) : ''),
            'customer_regs' => $regs($registrations['party'] ?? []),
            'reference' => $invoice->customer_reference ? $e('Your reference: '.$invoice->customer_reference) : '',
            'terms' => $e('Payment: '.($invoice->payment_terms ?: 'as agreed').'.'),
            'text' => nl2br($e($this->fill($block['text'], $invoice))),
            'line' => '<hr>',
            'spacer' => '<div style="height:'.(int) $block['size'].'px"></div>',
            default => '',
        };

        return new HtmlString($html);
    }

    /** Fills a layout's own text: {invoice_number}, {date}, {due_date}, {customer}, {customer_reference}, {total}, {currency}, {company}, {terms}. Unknown fields stay as typed. */
    public function fill(string $text, Invoice $invoice): string
    {
        $values = [
            'invoice_number' => (string) ($invoice->invoice_number ?? '(draft)'), 'date' => $invoice->invoice_date->displayDate(), 'due_date' => $invoice->due_date?->displayDate() ?? '',
            'customer' => (string) $invoice->customer?->name, 'customer_reference' => (string) $invoice->customer_reference, 'total' => Formats::number((float) $invoice->total, 2),
            'currency' => (string) $invoice->currency, 'company' => UiSettings::brand()['name'], 'terms' => (string) ($invoice->payment_terms ?: 'as agreed'),
        ];

        return (string) preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => $values[$m[1]] ?? $m[0], $text);
    }

    public function isNumeric(string $column): bool
    {
        return in_array($column, self::NUMERIC, true);
    }

    public function cell(string $column, InvoiceLine $line): string
    {
        $n = fn ($v) => Formats::number((float) $v, 2);
        $pct = fn ($v) => (float) $v > 0 ? Formats::number((float) $v, 2).'%' : '';

        return match ($column) {
            'no' => (string) $line->line_number,
            'description' => $line->description.($line->supply_type === 'SERVICE' ? ' (service)' : ''),
            'tariff' => (string) $line->tariff_code,
            'qty' => Formats::number((float) $line->quantity, 3).($line->uom ? ' '.$line->uom : ''),
            'unit' => (string) $line->uom,
            'price' => $n($line->unit_price),
            'discount' => $pct($line->discount_percent),
            'net' => $n($line->line_net),
            'tax_pct' => $pct($line->tax_percent),
            'tax_amt' => $n($line->line_tax),
            'gross' => $n((float) $line->line_net + (float) $line->line_tax),
            default => '',
        };
    }

    /**
     * The totals rows to draw, in the order the layout lists them.
     *
     * @param  list<string>  $keys
     * @return list<array{label: string, value: string, strong: bool}>
     */
    public function totalRows(Invoice $invoice, array $keys): array
    {
        $n = fn ($v) => Formats::number((float) $v, 2);
        $pct = fn ($v) => Formats::number((float) $v, 2).'%';
        $breakdown = $invoice->taxBreakdown();
        $given = round($invoice->lines->sum(fn (InvoiceLine $l) => round((float) $l->quantity * (float) $l->unit_price, 2) - (float) $l->line_net), 2);
        $rows = [];
        foreach ($keys as $key) {
            if ($key === 'subtotal') {
                $rows[] = ['label' => 'Value excluding tax', 'value' => $n($invoice->subtotal), 'strong' => false];
            } elseif ($key === 'discount' && $given > 0) {
                $rows[] = ['label' => 'Discounts given (already taken off above)', 'value' => $n($given), 'strong' => false];
            } elseif ($key === 'tax') {
                $charged = $breakdown->reject(fn ($t) => $t['withheld']);
                foreach ($charged as $t) {
                    $rows[] = ['label' => $t['label'].' @ '.$pct($t['rate']).($t['authority'] ? ' ('.$t['authority'].')' : ''), 'value' => $n($t['amount']), 'strong' => false];
                }
                if ($charged->isEmpty()) {
                    $rows[] = ['label' => 'Tax', 'value' => $n($invoice->tax_total), 'strong' => false];
                }
            } elseif ($key === 'total') {
                $rows[] = ['label' => 'Total including tax '.$invoice->currency, 'value' => $n($invoice->total), 'strong' => true];
            } elseif ($key === 'withheld') {
                foreach ($breakdown->filter(fn ($t) => $t['withheld']) as $t) {
                    $rows[] = ['label' => 'Less '.strtolower($t['label']).' @ '.$pct($t['rate']), 'value' => '('.$n($t['amount']).')', 'strong' => false];
                }
            } elseif ($key === 'payable' && $invoice->tax_withheld > 0) {
                $rows[] = ['label' => 'Payable to '.UiSettings::brand()['name'], 'value' => $n($invoice->payableToUs()), 'strong' => true];
            }
        }

        return $rows;
    }
}
