@extends('layouts.app')

@section('title', 'Iron grades')
@section('breadcrumb', 'Iron grades')

@section('content')
<div class="page-heading"><div><h1>Iron grades</h1><p>Chemistry windows, standards equivalents, furnace capability and thermal acceptance windows.</p></div></div>
<div class="alert">Chemistry windows are starting values. Review each grade against your control plan and the customer's specification before relying on the gates.</div>

<div class="panel"><div class="table-wrap"><table>
    <thead><tr><th>Grade</th><th>Family</th><th>Min tensile</th><th>Min yield</th><th>Elong.</th><th>CE</th><th>Equivalents</th><th>Thermal window</th></tr></thead>
    <tbody>
    @foreach ($grades as $g)
        <tr>
            <td><a href="{{ route('iron-grades.show', $g) }}"><strong>{{ $g->code }}</strong></a></td>
            <td>{{ $g->family }}</td>
            <td>{{ $g->min_tensile_mpa ? $g->min_tensile_mpa.' MPa' : '—' }}</td>
            <td>{{ $g->min_yield_mpa ? $g->min_yield_mpa.' MPa' : '—' }}</td>
            <td>{{ $g->min_elongation_pct !== null ? $g->min_elongation_pct.' %' : '—' }}</td>
            <td>{{ $g->ce_min ?? '—' }} – {{ $g->ce_max ?? '—' }}</td>
            <td>{{ $g->aliases_count }}</td>
            <td>{{ in_array($g->id, $windows, true) ? 'Set' : 'Not set' }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div></div>
@endsection
