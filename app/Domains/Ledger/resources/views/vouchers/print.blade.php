<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $typeName }} {{ $number }}</title>
{{-- Voucher print, A4 (design 19h). --}}
<style>
body { margin: 0; background: #f0eee9; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; color: #1c1d1f; -webkit-font-smoothing: antialiased; }
code { font-family: ui-monospace, "SF Mono", Menlo, monospace; font-size: .92em; }
.page { width: 794px; min-height: 1123px; box-sizing: border-box; margin: 24px auto; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.12); padding: 56px 60px; display: flex; flex-direction: column; gap: 26px; font-size: 13px; }
.head { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 18px; border-bottom: 2px solid #1c1d1f; }
.lab { font-size: 12px; color: #6b6e73; }
table { border-collapse: collapse; width: 100%; }
th { text-align: left; font-weight: 500; font-size: 11.5px; color: #6b6e73; padding: 9px 12px; border-bottom: 1px solid #e6e7e9; white-space: nowrap; }
td { padding: 10px 12px; border-bottom: 1px solid #eff0f2; font-size: 13px; vertical-align: middle; }
.r { text-align: right; }
.sign { display: flex; flex-direction: column; gap: 6px; } .sign div { height: 44px; border-bottom: 1px solid #1c1d1f; } .sign span { font-size: 12px; color: #6b6e73; line-height: 1.4; }
.bar { width: 794px; margin: 16px auto 0; font-size: 13px; } .bar a { color: #2f5fd0; text-decoration: none; }
@media print { body { background: #fff; } .page { margin: 0; box-shadow: none; width: auto; min-height: 0; height: 100vh; } .bar { display: none; } @page { size: A4; margin: 0; } }
</style></head><body>
@php
    $n = fn (float $v) => $v ? \App\Support\Formats::number($v, fmod($v, 1.0) == 0.0 ? 0 : 2) : '';
    $brand = \App\Support\UiSettings::brand();
    $regs = collect(app(\App\Domains\Core\Contracts\TaxCalculator::class)->registrations(null)['seller'] ?? [])->map(fn ($v, $k) => $k.' '.$v)->implode(' · ');
    $dr = array_sum(array_column($lines, 'debit'));
    $cr = array_sum(array_column($lines, 'credit'));
    $counterLine = $counter ? collect($lines)->first(fn ($l) => $l['account']?->id === $counter->id) : null;
    $amount = $counterLine ? max($counterLine['debit'], $counterLine['credit']) : $dr;
    $cur = trim(\App\Support\Formats::withCurrency(''));
    $isCash = $counter && \App\Domains\Ledger\Services\Vouchers::isCash($counter);
@endphp
<div class="bar"><a href="#" onclick="window.print();return false">Print</a></div>
<div class="page">
    <div class="head">
        <div>@if ($brand['logo'])<img src="{{ $brand['logo'] }}" alt="" style="height:{{ $brand['height'] }}px;display:block;margin-bottom:8px">@endif<div style="font-size:20px;font-weight:700;letter-spacing:-.01em">{{ $brand['name'] }}</div>@if ($regs)<div style="color:#6b6e73;font-size:12px;line-height:1.5;margin-top:4px">{{ $regs }}</div>@endif</div>
        <div style="text-align:right"><div style="font-size:16px;font-weight:600">{{ ucfirst(strtolower($typeName)) }} voucher</div><code style="font-size:14px">{{ $number }}</code></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px">
        <div><div class="lab">Date</div><div>{{ $date->format('d M Y') }}</div></div>
        @if ($side)<div><div class="lab">{{ $side === 'out' ? 'Paid to' : 'Received from' }}</div><div>{{ $paidTo ?? ($type !== 'BPV' ? $reference : null) ?? '—' }}</div></div>@endif
        @if ($counter)<div><div class="lab">{{ $isCash ? 'Cash' : 'Bank' }}</div><div>{{ $bank ? trim($bank->bank_name.' · '.$bank->account_number, ' ·') : $counter->name }}</div></div>@endif
        @if ($type === 'BPV' && $reference)<div><div class="lab">Cheque</div><div>{{ $reference }} · {{ $date->format('d M Y') }}</div></div>@endif
        @if (! $side && $reference)<div><div class="lab">Reference</div><div>{{ $reference }}</div></div>@endif
        <div><div class="lab">Period</div><div>{{ $date->format('M') }} · {{ \App\Domains\Ledger\Services\Vouchers::yearLabel($date) }}</div></div>
    </div>
    <table><thead><tr><th>Code</th><th>Account</th><th>Narration</th><th class="r">Debit{{ $cur ? ' ('.$cur.')' : '' }}</th><th class="r">Credit{{ $cur ? ' ('.$cur.')' : '' }}</th></tr></thead><tbody>
        @foreach ($lines as $l)<tr><td><code>{{ $l['account']?->code }}</code></td><td>{{ $l['account']?->name }}@if ($l['party'] ?? null) · {{ $l['party'] }}@endif</td><td style="color:#6b6e73">{{ $l['detail'] }}</td><td class="r">{{ $n($l['debit']) }}</td><td class="r">{{ $n($l['credit']) }}</td></tr>@endforeach
        <tr style="font-weight:600"><td style="border:0"></td><td style="border:0">Total</td><td style="border:0"></td><td class="r" style="border:0;border-top:2px solid #1c1d1f">{{ $n($dr) }}</td><td class="r" style="border:0;border-top:2px solid #1c1d1f">{{ $n($cr) }}</td></tr>
    </tbody></table>
    <div style="padding:12px 14px;border:1px solid #e6e7e9;border-radius:6px"><span style="color:#6b6e73">Amount {{ $side === 'out' ? 'paid ' : ($side === 'in' ? 'received ' : '') }}in words: </span>{{ \App\Domains\Ledger\Services\AmountInWords::say($amount) }}</div>
    <div style="flex:1"></div>
    <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:24px">
        <div class="sign"><div></div><span>Prepared by @if ($by)<br>{{ $by }}@endif</span></div>
        <div class="sign"><div></div><span>Checked by</span></div>
        <div class="sign"><div></div><span>Approved by</span></div>
        <div class="sign"><div></div><span>{{ $side === 'in' ? 'Paid by' : 'Received by' }}<br>CNIC</span></div>
    </div>
    <div style="font-size:11px;color:#6b6e73;display:flex;justify-content:space-between"><span>Printed {{ now()->format('d M Y H:i') }}{{ $draft ? ' · draft, not posted' : '' }}</span><span>Page 1 of 1</span></div>
</div>
</body></html>
