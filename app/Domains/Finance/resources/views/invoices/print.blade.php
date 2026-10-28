<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $invoice->isCreditNote() ? 'Credit note' : 'Invoice' }} {{ $invoice->invoice_number }}</title>
<style>
body { font: 14px/1.45 system-ui, sans-serif; color: #111; max-width: 800px; margin: 24px auto; padding: 0 16px; }
h1 { font-size: 22px; margin: 0 0 4px; } table { border-collapse: collapse; width: 100%; margin: 14px 0; } th, td { border: 1px solid #bbb; padding: 6px 8px; text-align: left; vertical-align: top; } .num { text-align: right; }
.muted { color: #555; } .flag { border: 1px solid #b00; color: #b00; padding: 6px 10px; margin: 10px 0; } @media print { .noprint { display: none; } }
.parties { display: flex; gap: 24px; flex-wrap: wrap; } .parties > div { flex: 1; min-width: 240px; }
</style></head><body>
@php
    $n = fn ($v) => \App\Support\Formats::number((float) $v, 2);
    $pct = fn ($v) => \App\Support\Formats::number((float) $v, 2).'%';
    $brand = \App\Support\UiSettings::brand()['name'];
    $withCodes = $invoice->lines->contains(fn ($l) => filled($l->tariff_code));
    $breakdown = $invoice->taxBreakdown();
    $tariffLabel = \App\Support\TaxJurisdiction::tariffLabel();
    $span = $withCodes ? 5 : 4;
@endphp
<p class="noprint"><a href="#" onclick="window.print();return false">Print</a></p>
@include($templateView)
</body></html>
