{{-- Draws an invoice from a layout (see InvoiceLayouts): $layout, $invoice, $registrations. One table, so the header and column headings
     repeat at the top of every printed page and the footer at the bottom, and a row is never split between pages. --}}
@php
    $engine = app(\App\Domains\Finance\Services\InvoiceLayouts::class);
    $columns = collect($layout['body']['columns'])->where('show', true)->values();
    $count = $columns->count();
    $paper = ($layout['page']['size'] === 'LETTER' ? 'letter' : 'A4').' '.$layout['page']['orientation'];
    $pageNumbers = collect($layout['footer'])->flatten(1)->where('kind', 'page')->first();
    $drawRows = function (array $rows) use ($engine, $invoice, $registrations) {
        $out = '';
        foreach ($rows as $row) {
            $cells = '';
            foreach ($row as $block) {
                if ($block['kind'] === 'page') {
                    continue;
                }
                $cells .= '<div class="lay-cell" style="text-align:'.$block['align'].'">'.$engine->block($block, $invoice, $registrations).'</div>';
            }
            $out .= $cells === '' ? '' : '<div class="lay-row">'.$cells.'</div>';
        }

        return new \Illuminate\Support\HtmlString($out);
    };
@endphp
<style>
@page { size: {{ $paper }}; margin: {{ $layout['page']['margin_mm'] }}mm; @if ($pageNumbers) @bottom-{{ $pageNumbers['align'] }} { content: "Page " counter(page) " of " counter(pages); font: {{ $layout['page']['font_pt'] - 1 }}pt system-ui, sans-serif; } @endif }
body { max-width: none; margin: 0; padding: 0; font-size: {{ $layout['page']['font_pt'] }}pt; }
@media screen { .lay { max-width: {{ $layout['page']['orientation'] === 'landscape' ? '297mm' : '210mm' }}; margin: 12px auto; padding: 0 12px; } }
.lay { width: 100%; border-collapse: collapse; } .lay th, .lay td { border: 0; padding: 0; vertical-align: top; }
.lay thead { display: table-header-group; } .lay tfoot { display: table-footer-group; } .lay tr { break-inside: avoid; page-break-inside: avoid; }
.lay-row { display: flex; gap: 16px; margin: 2px 0 6px; } .lay-cell { flex: 1; min-width: 0; } .lay hr { border: 0; border-top: 1px solid #bbb; margin: 4px 0; }
.lay-title { font-size: 1.7em; font-weight: 600; } .lay-lines th { border-bottom: 1.5px solid #444; padding: 4px 6px; text-align: left; } .lay-lines td.lay-l { border-bottom: 1px solid #ddd; padding: 4px 6px; }
.lay .r { text-align: right; } .lay-total td { padding: 3px 6px; } .lay-total.strong td { font-weight: 700; border-top: 1px solid #444; }
</style>
<table class="lay">
<thead>
    @if ($layout['header'] !== [])<tr><td colspan="{{ $count }}">{{ $drawRows($layout['header']) }}</td></tr>@endif
    <tr class="lay-lines">@foreach ($columns as $c)<th @class(['r' => $engine->isNumeric($c['key'])]) @if ($c['width'] > 0) style="width:{{ $c['width'] }}%" @endif>{{ $c['label'] }}</th>@endforeach</tr>
</thead>
<tbody>
    @if ($layout['intro'] !== [])<tr><td colspan="{{ $count }}">{{ $drawRows($layout['intro']) }}</td></tr>@endif
    @foreach ($invoice->lines as $line)
        <tr class="lay-lines">@foreach ($columns as $c)<td @class(['lay-l', 'r' => $engine->isNumeric($c['key'])])>{{ $engine->cell($c['key'], $line) }}</td>@endforeach</tr>
    @endforeach
    @foreach ($engine->totalRows($invoice, $layout['body']['totals']) as $row)
        <tr @class(['lay-total', 'strong' => $row['strong']])>@if ($count > 1)<td colspan="{{ $count - 1 }}" class="r">{{ $row['label'] }}</td><td class="r">{{ $row['value'] }}</td>@else<td class="r">{{ $row['label'] }}: {{ $row['value'] }}</td>@endif</tr>
    @endforeach
    @if ($invoice->status === 'VOID')<tr><td colspan="{{ $count }}" class="flag">This document is void.</td></tr>@endif
    @if ($layout['body']['terms'] !== '')<tr><td colspan="{{ $count }}" style="padding-top:10px">{!! nl2br(e($engine->fill($layout['body']['terms'], $invoice))) !!}</td></tr>@endif
    @if ($invoice->notes)<tr><td colspan="{{ $count }}" style="padding-top:6px">{!! nl2br(e($invoice->notes)) !!}</td></tr>@endif
</tbody>
@if ($layout['footer'] !== [])<tfoot><tr><td colspan="{{ $count }}">{{ $drawRows($layout['footer']) }}</td></tr></tfoot>@endif
</table>
