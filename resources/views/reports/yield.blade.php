@extends('layouts.app')
@section('title', 'Yield and scrap')
@section('content')
<div class="page-heading"><div><h1>Yield and scrap</h1><p>Metal yield is the weight of good castings divided by the metal poured. Scrap rate is rejected pieces divided by pieces poured.</p></div></div>
<div class="panel"><div class="panel-body"><form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">From <input type="date" name="from" value="{{ $from->toDateString() }}"> to <input type="date" name="to" value="{{ $to->toDateString() }}"><button class="btn">Show</button></form></div></div>
<div class="metrics">
<div class="metric"><div class="metric-label">Metal poured</div><div class="metric-value">{{ number_format($totals['poured_kg'], 0) }} kg</div></div>
<div class="metric"><div class="metric-label">Good castings</div><div class="metric-value">{{ number_format($totals['good_kg'], 0) }} kg</div></div>
<div class="metric"><div class="metric-label">Metal yield</div><div class="metric-value">{{ $totals['yield'] !== null ? number_format($totals['yield'], 1).' %' : '—' }}</div></div>
<div class="metric"><div class="metric-label">Scrap rate</div><div class="metric-value">{{ $totals['pieces'] > 0 ? number_format(100 * $totals['rejected'] / $totals['pieces'], 1).' %' : '—' }}</div><div class="metric-note">{{ $totals['rejected'] }} of {{ $totals['pieces'] }} pieces</div></div>
</div>

<div class="panel"><div class="panel-header"><h2>Defect Pareto</h2></div><div class="table-wrap"><table><thead><tr><th>Defect</th><th>Category</th><th>ICF group</th><th>Pieces</th><th>NCRs</th><th style="width:30%">Share</th><th>Cumulative</th></tr></thead><tbody>
@forelse($pareto as $d)<tr><td>{{ $d['name'] }}</td><td>{{ $d['category'] }}</td><td>@if($d['icf']){{ $d['icf'] }} — {{ $groups[$d['icf']] }}@endif</td><td>{{ $d['qty'] }}</td><td>{{ $d['ncrs'] }}</td>
<td><div style="background:#e8e8e8;height:12px;border-radius:3px"><div style="background:var(--accent);height:12px;border-radius:3px;width:{{ min(100, round($d['pct'])) }}%"></div></div> {{ number_format($d['pct'], 0) }} %</td><td>{{ number_format($d['cum'], 0) }} %</td></tr>
@empty<tr><td colspan="7" class="muted">No nonconformances in this period.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body muted small">Counts the pieces rejected or sent to rework on NCRs raised in the period. Aim first at the top defects until together they pass 80 %. An NCR with no defect chosen shows as Unclassified; classify them to make this useful.</div></div>

<div class="panel"><div class="panel-header"><h2>By pour / heat</h2></div><div class="table-wrap"><table><thead><tr><th>Pour</th><th>Heat</th><th>Metal poured kg</th><th>Good castings kg</th><th>Yield %</th><th>Pieces</th><th>Rejected</th></tr></thead><tbody>
@forelse($byHeat as $h)<tr><td>{{ $h['pour'] }}</td><td>{{ $h['heat'] }}</td><td>{{ number_format($h['poured_kg'], 0) }}</td><td>{{ number_format($h['good_kg'], 0) }}</td><td>{{ $h['yield'] !== null ? number_format($h['yield'], 1) : '—' }}</td><td>{{ $h['pieces'] }}</td><td>{{ $h['rejected'] }}</td></tr>
@empty<tr><td colspan="7" class="muted">No completed pours with lots in this period.</td></tr>@endforelse</tbody></table></div></div>

<div class="panel"><div class="panel-header"><h2>Scrap by moulding team</h2></div><div class="table-wrap"><table><thead><tr><th>Team</th><th>Pieces</th><th>Rejected</th><th>Scrap %</th></tr></thead><tbody>
@forelse($byTeam as $t)<tr><td>{{ $t['name'] }}</td><td>{{ $t['pieces'] }}</td><td>{{ $t['rejected'] }}</td><td>{{ $t['rate'] !== null ? number_format($t['rate'], 1) : '—' }}</td></tr>@empty<tr><td colspan="4" class="muted">No data.</td></tr>@endforelse</tbody></table></div></div>

<div class="panel"><div class="panel-header"><h2>Scrap by pattern</h2></div><div class="table-wrap"><table><thead><tr><th>Pattern</th><th>Pieces</th><th>Rejected</th><th>Scrap %</th></tr></thead><tbody>
@forelse($byPattern as $t)<tr><td>{{ $t['name'] }}</td><td>{{ $t['pieces'] }}</td><td>{{ $t['rejected'] }}</td><td>{{ $t['rate'] !== null ? number_format($t['rate'], 1) : '—' }}</td></tr>@empty<tr><td colspan="4" class="muted">No data.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body muted small">Scrap attributed to a team or pattern means the lot's moulds came from that team's run. A defect may still have another root cause (metal, sand, pouring); use the Pareto and the NCR root cause to decide.</div></div>
@endsection
