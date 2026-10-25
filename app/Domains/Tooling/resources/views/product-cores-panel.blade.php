{{-- The cores a product needs, by revision, and whether a core box can make each. Slot product.show. --}}
@php
    $needs = \App\Models\CoreRequirement::whereIn('product_revision_id', $product->revisions->pluck('id'))->with('coreDefinition', 'productRevision')->get()
        ->sortBy(fn ($n) => [$n->productRevision->revision, $n->coreDefinition->code]);
    $boxes = \App\Models\CoreBoxCavity::whereIn('core_definition_id', $needs->pluck('core_definition_id'))->whereHas('coreBox', fn ($q) => $q->where('status', 'ACTIVE'))->with('coreBox')->get()->groupBy('core_definition_id');
@endphp
<div class="tab-marker" data-tab-start="Cores"></div>
<div class="panel">
    <div class="panel-header"><h2>Cores</h2>@unless ($product->trashed())<button type="button" class="btn btn-small" data-quick="qd-core-need">+ Core needed</button>@endunless</div>
    <div class="table-wrap"><table>
        <thead><tr><th>Core</th><th>Revision</th><th>Per casting</th><th>Core box</th><th></th></tr></thead>
        <tbody>
        @forelse ($needs as $n)
            @php $made = ($boxes[$n->core_definition_id] ?? collect())->pluck('coreBox')->unique('id'); @endphp
            <tr>
                <td><x-item :name="$n->coreDefinition->name" :code="$n->coreDefinition->code" :href="route('cores.show', $n->coreDefinition)" /></td>
                <td class="mono">Rev {{ $n->productRevision->revision }}</td>
                <td>{{ $n->quantity_per_casting }}</td>
                <td>@forelse ($made as $b)<a href="{{ route('core-boxes.show', $b) }}">{{ $b->code }}</a>@if (! $loop->last), @endif @empty<span class="status status-on-hold">No core box</span> <a href="{{ route('core-boxes.create', ['core' => $n->core_definition_id]) }}">Add one</a>@endforelse</td>
                <td><form method="POST" action="{{ route('core-requirements.destroy', $n) }}" onsubmit="return confirm('Remove this core from the product?')">@csrf @method('DELETE')<button class="btn btn-small" type="submit">Remove</button></form></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No cores needed. Add one if a casting of this product is made with cores.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@include('partials.quick-dialog', ['id' => 'qd-core-need', 'title' => 'Core needed by '.$product->code, 'action' => route('core-requirements.store'), 'submit' => 'Add core', 'fields' => 'tooling::_requirement-fields', 'product' => $product])
