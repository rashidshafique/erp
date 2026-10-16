@php
    $links = $product->revisions->flatMap->patternCavities;
@endphp
<div class="panel">
    <div class="panel-header"><h2>Made on these patterns</h2></div>
    @if ($links->isEmpty())
        <div class="empty-state">No pattern cavity is linked to this product yet. Link cavities from the pattern page.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Pattern</th><th>Pattern rev</th><th>Cavity</th><th>Product rev</th><th>Makes</th><th>Casting kg</th></tr>
                </thead>
                <tbody>
                    @foreach ($links->sortBy(fn ($l) => $l->patternRevision->pattern->code.$l->patternRevision->revision.str_pad($l->cavity_number, 5, '0', STR_PAD_LEFT)) as $l)
                        <tr>
                            <td><a href="{{ route('patterns.show', $l->patternRevision->pattern) }}">{{ $l->patternRevision->pattern->code }}</a></td>
                            <td>{{ $l->patternRevision->revision }}</td>
                            <td>{{ $l->cavity_number }}</td>
                            <td>{{ $l->productRevision->revision }}</td>
                            <td>{{ $l->label() }}</td>
                            <td>{{ $l->casting_weight_kg !== null ? number_format((float) $l->casting_weight_kg, 3) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
