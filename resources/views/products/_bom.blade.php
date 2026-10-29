{{-- Design 10b: the current revision's bill of materials with its material cost per casting. $product, $facts. --}}
@php
    $f = $facts;
    $revision = $f['revision'];
    $types = \App\Models\Material::TYPES;
    $money = fn ($v) => $v === null ? '—' : \App\Support\Formats::money($v);
    $num = fn ($v) => $v === null ? '—' : \App\Support\Formats::number($v, 3);
@endphp
<div class="pd-grid">
    <div class="pd-main">
        <div class="pd-facts">
            <div><div class="k">Revision</div><div>{{ $revision ? $revision->revision.($revision->effective_from ? ' · released '.$revision->effective_from->format('j M') : '') : '—' }}</div></div>
            <div><div class="k">Casting weight</div><div>{{ $f['castingKg'] !== null ? \App\Support\Formats::number($f['castingKg'], 2).' kg' : '—' }}</div></div>
            <div><div class="k">Poured weight</div><div>{{ $f['pouredKg'] ? \App\Support\Formats::number($f['pouredKg'], 2).' kg'.($f['castingKg'] ? ' · yield '.round(100 * $f['castingKg'] / $f['pouredKg']).'%' : '') : '—' }}</div></div>
            <div><div class="k">Per mould</div><div>{{ $f['perMould'] ? $f['perMould'].' cavities'.($f['patternRevision'] ? ' on '.$f['patternRevision']->pattern->code : '') : '—' }}</div></div>
        </div>
        <div class="table-wrap">
            <table class="pd-table">
                <thead><tr><th>Type</th><th>Item</th><th>Description</th><th class="r">Per casting</th><th>Unit</th><th>Source</th></tr></thead>
                <tbody>
                    @if ($f['grade'] && $f['pouredKg'])
                        <tr><td><span class="pd-pill info">Metal</span></td><td class="mono">{{ $f['grade']->code }}</td><td>Liquid iron, poured weight</td><td class="r">{{ \App\Support\Formats::number($f['pouredKg'], 2) }}</td><td>kg</td><td>Melt</td></tr>
                    @endif
                    @forelse ($f['bom'] as $row)
                        @php $m = $row['line']->material; @endphp
                        <tr>
                            <td><span class="pd-pill">{{ $types[$m?->material_type] ?? 'Other' }}</span></td>
                            <td class="mono">{{ $m?->code }}</td>
                            <td>{{ $m?->name }}{{ $row['line']->notes ? ' · '.$row['line']->notes : '' }}</td>
                            <td class="r">{{ $num($row['perCasting']) }}</td>
                            <td>{{ $m?->base_uom }}</td>
                            <td>Stores</td>
                        </tr>
                    @empty
                        @unless ($f['grade'] && $f['pouredKg'])<tr><td colspan="6" class="muted">No items listed for this revision.</td></tr>@endunless
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($f['previous'] && $revision && $f['previous']->id !== $revision->id)
            <div class="pd-callout"><strong>Rev {{ $f['previous']->revision }} to {{ $revision->revision }}</strong>{{ $revision->engineering_notes ? ': '.$revision->engineering_notes : '' }}</div>
        @endif
    </div>
    <aside class="pd-side">
        <div class="pd-h">Material cost per casting</div>
        @foreach ($f['bom'] as $row)
            <div class="pd-row"><span>{{ $row['line']->material?->name }}</span><span>{{ $money($row['cost']) }}</span></div>
        @endforeach
        @if ($f['materialCost'] !== null)
            <hr>
            <div class="pd-row"><strong>Total</strong><strong>{{ $money($f['materialCost']) }}</strong></div>
        @else
            <p class="muted">No standard cost on the items yet.</p>
        @endif
    </aside>
</div>
