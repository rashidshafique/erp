@extends('layouts.app')
@section('title', 'Feasibility')
@section('breadcrumb', 'Feasibility')
@section('content')
@php $fcls = ['PENDING' => 'status-on-hold', 'OK' => 'status-completed', 'CONDITIONAL' => 'status-in-progress', 'NOT_FEASIBLE' => 'status-cancelled']; $all = request('show') === 'all'; @endphp
<div class="page-heading">
    <div><h1>Feasibility</h1><p>Parts waiting for a decision on whether we can make them, and which departments have signed.</p></div>
    <div class="actions"><a class="btn" href="{{ route('feasibility.index', $all ? [] : ['show' => 'all']) }}">{{ $all ? 'Only waiting' : 'Show decided too' }}</a></div>
</div>
<div class="panel">
    <div class="panel-header"><h2>{{ $all ? 'All parts on open enquiries' : 'Waiting for a decision' }}</h2><span class="muted small">{{ $items->total() }} parts</span></div>
    @if ($items->isEmpty())
        <div class="empty-state"><strong>Nothing waiting</strong><p>Every part on an open enquiry has a feasibility decision.</p></div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Enquiry</th><th>Customer</th><th>Part</th><th>Signed</th><th>So far</th><th>Decision</th><th></th></tr></thead>
            <tbody>
            @foreach ($items as $it)
                @php $outcome = $it->reviewOutcome(); @endphp
                <tr>
                    <td><a href="{{ route('enquiries.show', $it->enquiry) }}"><strong>{{ $it->enquiry->code }}</strong></a></td>
                    <td>{{ $it->enquiry->customer?->name }}</td>
                    <td>{{ $it->line_no }}. {{ $it->description }}</td>
                    <td>{{ $it->reviews->count() }} of {{ count($departments) }}</td>
                    <td>@if ($outcome)<span class="status {{ $fcls[$outcome] }}">{{ \App\Domains\Enquiry\Models\EnquiryItem::FEASIBILITY[$outcome] }}</span>@else<span class="muted">—</span>@endif</td>
                    <td><span class="status {{ $fcls[$it->feasibility_status] }}">{{ \App\Domains\Enquiry\Models\EnquiryItem::FEASIBILITY[$it->feasibility_status] }}</span></td>
                    <td><a href="{{ route('feasibility.show', $it) }}">Review →</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="pagination">{{ $items->links() }}</div>
    @endif
</div>
@endsection
