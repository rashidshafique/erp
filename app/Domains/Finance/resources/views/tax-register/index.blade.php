@extends('layouts.app')
@section('title', 'Tax register')
@push('styles')<style>@media print{.sidebar,.page-heading .actions,.no-print{display:none!important}}</style>@endpush
@section('content')
@php
    $m = fn (float $v) => $v < 0 ? '('.\App\Support\Formats::number(-$v, 2).')' : \App\Support\Formats::number($v, 2);
    $d = fn (string $date) => \Illuminate\Support\Carbon::parse($date)->displayDate();
    $rate = fn (float $r) => rtrim(rtrim(number_format($r, 3, '.', ''), '0'), '.').'%';
    $sales = $side === 'sales';
@endphp
<div class="page-heading"><div><h1>Tax register: {{ $sales ? 'sales (output tax)' : 'purchases (input tax)' }}</h1>
<p>{{ $d($from) }} to {{ $d($to) }}. {{ $sales ? 'Issued invoices and credit notes' : 'Posted supplier bills and credit notes' }} by their own date; voided ones are left out and credit notes are in brackets.@unless ($sales) Bills carry one typed percent per line, so input tax has no authority yet.@endunless</p></div>
<div class="actions"><a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Download CSV</a><button class="btn btn-primary" onclick="window.print()">Print</button></div></div>
<div class="panel no-print"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="side">Register</label><select id="side" name="side"><option value="sales" @selected($sales)>Sales: output tax</option><option value="purchases" @selected(! $sales)>Purchases: input tax</option></select></div>
    <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="{{ $from }}"></div>
    <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="{{ $to }}"></div>
    @if ($authorities->count() > 1 || $authority !== null)<div class="field"><label for="authority">Authority</label><select id="authority" name="authority"><option value="">All</option>@foreach ($authorities as $a)<option value="{{ $a }}" @selected($authority === $a)>{{ $a === '-' ? 'None named' : $a }}</option>@endforeach</select></div>@endif
    <button class="btn">Show</button>
</form></div></div>
<div class="panel"><div class="panel-header"><h2>By authority and tax</h2></div><div class="table-wrap"><table><thead><tr><th>Authority</th><th>Tax</th><th class="num">Rate</th><th></th><th class="num">Documents</th><th class="num">Value excluding tax</th><th class="num">Tax</th></tr></thead><tbody>
@forelse ($summary as $s)
<tr><td>{{ $s['authority'] ?? '—' }}</td><td>{{ $s['label'] }}</td><td class="num">{{ $rate($s['rate']) }}</td><td class="small">{{ $s['withheld'] ? 'Withheld by the buyer' : 'Charged' }}</td><td class="num">{{ $s['documents'] }}</td><td class="num">{{ $m($s['base']) }}</td><td class="num">{{ $m($s['amount']) }}</td></tr>
@empty<tr><td colspan="7" class="muted">Nothing in this period.</td></tr>@endforelse
</tbody>@if ($summary->isNotEmpty())<tfoot>
<tr><th colspan="6" class="num">Tax charged</th><th class="num">{{ $m(round($summary->where('withheld', false)->sum('amount'), 2)) }}</th></tr>
@if ($summary->where('withheld', true)->isNotEmpty())<tr><th colspan="6" class="num">Tax withheld by buyers</th><th class="num">{{ $m(round($summary->where('withheld', true)->sum('amount'), 2)) }}</th></tr>@endif
</tfoot>@endif</table></div></div>
<div class="panel"><div class="panel-header"><h2>Line by line</h2></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Document</th><th>{{ $sales ? 'Customer' : 'Supplier' }}</th><th>Registration</th>@if ($sales)<th>Tariff code</th>@endif<th>Tax</th><th class="num">Rate</th><th class="num">Value excluding tax</th><th class="num">Tax</th></tr></thead><tbody>
@forelse ($rows as $r)
<tr><td>{{ \Illuminate\Support\Carbon::parse($r['date'])->displayShortDate() }}</td><td><a href="{{ $r['url'] }}" class="mono">{{ $r['number'] }}</a>@if ($r['kind'] === 'Credit note') <span class="muted small">credit note</span>@endif</td>
<td>{{ $r['party'] }}</td><td class="small">{{ collect($r['registrations'])->map(fn ($v, $k) => "{$k} {$v}")->implode(' · ') ?: '—' }}</td>
@if ($sales)<td class="mono small">{{ $r['tariff'] }}</td>@endif
<td class="small">{{ $r['label'] }}@if ($r['authority']) <span class="muted">{{ $r['authority'] }}</span>@endif @if ($r['withheld'])<span class="muted">(withheld)</span>@endif</td>
<td class="num">{{ $rate($r['rate']) }}</td><td class="num">{{ $m($r['base']) }}</td><td class="num">{{ $m($r['amount']) }}</td></tr>
@empty<tr><td colspan="{{ $sales ? 9 : 8 }}" class="muted">Nothing in this period.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
