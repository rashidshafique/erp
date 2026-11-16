{{-- The cores the product's current revision needs and whether a core box in use makes each (design 29j). Needs on other
     revisions fold below. Slot product.show. --}}
@php
    $current = $product->currentRevision();
    $needs = \App\Models\CoreRequirement::whereIn('product_revision_id', $product->revisions->pluck('id'))->with('coreDefinition', 'productRevision')->get()
        ->sortBy(fn ($n) => [$n->productRevision->revision, $n->coreDefinition->code]);
    [$now, $other] = $needs->partition(fn ($n) => $n->product_revision_id === $current?->id);
    $boxes = \App\Models\CoreBoxCavity::whereIn('core_definition_id', $needs->pluck('core_definition_id'))->whereHas('coreBox', fn ($q) => $q->where('status', 'ACTIVE'))->with('coreBox')->get()->groupBy('core_definition_id');
@endphp
<div class="tab-marker" data-tab-start="Cores"></div>
<div class="panel">
    <div class="panel-header"><h2>Cores @if ($current)<span class="muted small" style="font-weight:400">· revision {{ $current->revision }}</span>@endif</h2>@unless ($product->trashed())<button type="button" class="btn btn-small" data-quick="qd-core-need">+ Core</button>@endunless</div>
    <div class="table-wrap"><table>
        <thead><tr><th>Core</th><th class="r">Per casting</th><th>Box</th><th></th></tr></thead>
        <tbody>
        @forelse ($now as $n)
            @include('tooling::_product-core-row', ['made' => ($boxes[$n->core_definition_id] ?? collect())->pluck('coreBox')->unique('id'), 'showRevision' => false])
        @empty
            <tr><td colspan="4" class="muted">No cores needed. Add one if a casting of this product is made with cores.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($other->isNotEmpty())
        <details style="padding:8px 16px;border-top:1px solid var(--border)"><summary class="muted small" style="cursor:pointer">Other revisions · {{ $other->count() }} {{ $other->count() === 1 ? 'core' : 'cores' }}</summary>
            <div class="table-wrap"><table><tbody>@foreach ($other as $n)@include('tooling::_product-core-row', ['made' => ($boxes[$n->core_definition_id] ?? collect())->pluck('coreBox')->unique('id'), 'showRevision' => true])@endforeach</tbody></table></div>
        </details>
    @endif
</div>
@include('partials.quick-dialog', ['id' => 'qd-core-need', 'title' => 'Core needed by '.$product->code, 'action' => route('core-requirements.store'), 'submit' => 'Add core', 'fields' => 'tooling::_requirement-fields', 'product' => $product])
