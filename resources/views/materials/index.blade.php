@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1>Materials</h1>
        <p>Charge inputs, consumables, and traceable stock materials.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('materials.create') }}">+ New material</a>
</div>


<div class="table-responsive">
<table class="table">
    <thead>
        <tr>
            <th>Code</th><th>Material</th><th>Type</th><th>Unit</th>
            <th>Status</th><th>Lots</th><th>Recipes</th>
        </tr>
    </thead>
    <tbody>
    @forelse($materials as $material)
        <tr>
            <td><a href="{{ route('materials.show', $material) }}">{{ $material->code }}</a></td>
            <td>{{ $material->name }}</td>
            <td>{{ $material->material_type }}</td>
            <td>{{ $material->base_uom }}</td>
            <td>{{ $material->status }}</td>
            <td>{{ $material->lots_count }}</td>
            <td>{{ $material->charge_batch_items_count }}</td>
        </tr>
    @empty
        <tr><td colspan="7">No materials created yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>

{{ $materials->links() }}
@endsection
