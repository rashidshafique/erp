@extends('layouts.app')

@section('content')
<div class="page-header">
    <div><h1>Charge recipes</h1><p>Controlled charge compositions for cupola and induction heats.</p></div>
    <a class="btn btn-primary" href="{{ route('charge-batches.create') }}">+ New recipe</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="table-responsive">
<table class="table">
    <thead><tr><th>Code</th><th>Recipe</th><th>Process</th><th>Revision</th><th>Status</th><th>Components</th></tr></thead>
    <tbody>
    @forelse($chargeBatches as $batch)
        <tr>
            <td><a href="{{ route('charge-batches.show', $batch) }}">{{ $batch->code }}</a></td>
            <td>{{ $batch->name }}</td>
            <td>{{ $batch->process_type }}</td>
            <td>{{ $batch->revision }}</td>
            <td>{{ $batch->status }}</td>
            <td>{{ $batch->items_count }}</td>
        </tr>
    @empty
        <tr><td colspan="6">No charge recipes created yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>

{{ $chargeBatches->links() }}
@endsection
