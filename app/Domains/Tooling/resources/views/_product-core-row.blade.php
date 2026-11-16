{{-- One core a product revision needs, with the boxes in use that make it (product-cores-panel). --}}
<tr>
    <td><x-item :name="$n->coreDefinition->name" :code="$n->coreDefinition->code" :href="route('cores.show', $n->coreDefinition)" />@if ($showRevision)<div class="muted small mono">Rev {{ $n->productRevision->revision }}</div>@endif</td>
    <td class="r">{{ $n->quantity_per_casting }}</td>
    <td>@if ($made->isNotEmpty())<span style="color:#1e7a46" aria-hidden="true">✓</span> @foreach ($made as $b)<a href="{{ route('core-boxes.show', $b) }}" class="mono">{{ $b->code }}</a>@if (! $loop->last), @endif @endforeach
        @else<span class="status status-on-hold">! No active box</span> <a class="small" href="{{ route('core-boxes.create', ['core' => $n->core_definition_id]) }}">Add one</a>@endif</td>
    <td class="r">@unless ($product->trashed())<form method="POST" action="{{ route('core-requirements.destroy', $n) }}" onsubmit="return confirm('Remove this core from the product?')">@csrf @method('DELETE')<button class="btn btn-small" type="submit">Remove</button></form>@endunless</td>
</tr>
