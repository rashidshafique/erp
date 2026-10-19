@extends('layouts.app')

@section('title', 'Business partners')
@section('breadcrumb', 'Business partners')

@section('content')
<div class="page-heading">
    <div><h1>Business partners</h1><p>Every company we sell to or buy from, once. A partner can be a customer, a supplier, or both.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('parties.create') }}">+ Add partner</a></div>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" class="form-grid" style="align-items:end">
            <div class="field"><label for="q">Search code or name</label><input id="q" name="q" value="{{ request('q') }}"></div>
            <div class="field"><label for="role">Role</label><select id="role" name="role"><option value="">All</option>@foreach (['customer' => 'Customers', 'supplier' => 'Suppliers', 'both' => 'Both'] as $k => $l)<option value="{{ $k }}" @selected(request('role') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div><button class="btn btn-primary" type="submit">Filter</button> <a class="btn" href="{{ route('parties.index') }}">Clear</a></div>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Code</th><th>Name</th><th>Contact</th><th>Roles</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($parties as $party)
            <tr>
                <td class="mono"><a href="{{ route('parties.show', $party) }}">{{ $party->code }}</a></td>
                <td>{{ $party->name }}</td>
                <td>{{ collect([$party->contact_name, $party->phone])->filter()->implode(' · ') ?: '—' }}</td>
                <td>{{ implode(', ', $party->roles()) ?: '—' }}</td>
                <td>{{ $party->active ? 'Active' : 'Inactive' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No partners yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="panel-body">{{ $parties->links() }}</div>
</div>
@endsection
