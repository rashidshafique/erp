{{-- The patterns that make a product, one entry per pattern with its latest revision, earlier ones folded: which cavities make it and how many of the product
     one mould gives. Current revisions come first; superseded and archived ones stay listed for traceability. --}}
@php
    $map = app(\App\Services\PatternCavityMap::class);
    $groups = $product->revisions->flatMap->patternCavities
        ->groupBy('pattern_revision_id')
        ->map(fn ($cavities) => ['revision' => $cavities->first()->patternRevision, 'cavities' => $cavities->sortBy('cavity_number')->values()])
        ->sortBy(fn ($g) => [! $g['revision']->is_current, $g['revision']->pattern->name, -$g['revision']->sequence])
        ->values();
    // One entry per pattern, its latest revision first: the current one, else the highest sequence.
    $patterns = $groups->groupBy(fn ($g) => $g['revision']->pattern_id)->map(fn ($revisions) => $revisions->values())
        ->sortBy(fn ($revisions) => [! $revisions->first()['revision']->is_current, $revisions->first()['revision']->pattern->name])->values();
    $photos = \App\Models\Attachment::where('attachable_type', 'pattern')->whereIn('attachable_id', $groups->map(fn ($g) => $g['revision']->pattern_id)->unique())
        ->whereNull('removed_at')->whereIn('mime_type', ['image/png', 'image/jpeg', 'image/webp'])
        ->orderByDesc('is_cover')->orderByDesc('created_at')->get()->unique('attachable_id')->keyBy('attachable_id');
@endphp
<style>
    .made-on { display: grid; gap: 10px; }
    @media (min-width: 1024px) { .made-on { grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; } .made-on .made-on { grid-template-columns: minmax(0, 1fr); } }
    .made-on .mo-row { position: relative; }
    .made-on .mo-row:hover { border-color: var(--accent, var(--border)); }
    .made-on .mo-name a::after { content: ''; position: absolute; inset: 0; border-radius: 10px; }
    .made-on .mo-photo { position: relative; z-index: 1; }
    .made-on .mo-row { display: grid; grid-template-columns: 56px minmax(0, 1fr) auto; gap: 12px 16px; align-items: center; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; }
    .made-on .mo-row.old { opacity: .75; }
    .made-on .mo-photo { width: 56px; height: 56px; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); }
    .made-on .mo-photo img { width: 100%; height: 100%; object-fit: cover; padding: 0; min-height: 0; }
    .made-on .mo-name { font-size: 15px; font-weight: 600; }
    .made-on .mo-meta { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: 4px; }
    .made-on .mo-count { text-align: right; font-size: 12px; color: var(--muted); }
    .made-on .mo-count strong { display: block; font-size: 20px; color: var(--text); }
    .mo-earlier { margin: -4px 0 0 24px; }
    .mo-earlier summary { cursor: pointer; font-size: 12.5px; color: var(--muted); padding: 4px 0; }
    .mo-earlier .made-on { margin-top: 6px; }
    @media (max-width: 768px) { .made-on .mo-row { grid-template-columns: 48px 1fr; } .made-on .mo-count { grid-column: 2; text-align: left; } }
</style>
<div class="panel">
    <div class="panel-header"><h2>Made on these patterns</h2><span class="muted small">Cavities are linked to products on each pattern's cavity map.</span></div>
    <div class="panel-body">
        @if ($groups->isEmpty())
            <div class="empty-state" style="padding:12px 0">No pattern cavity is linked to this product yet. Link cavities from the <a href="{{ route('patterns.index') }}">pattern page</a>.</div>
        @else
            <div class="made-on">
                @foreach ($patterns as $revisions)
                    <div class="mo-item">
                    @include('products._made-on-row', ['g' => $revisions->first()])
                    @if ($revisions->count() > 1)
                        <details class="mo-earlier">
                            <summary>{{ $revisions->count() - 1 }} earlier revision{{ $revisions->count() === 2 ? '' : 's' }}</summary>
                            <div class="made-on">
                                @foreach ($revisions->slice(1) as $g)
                                    @include('products._made-on-row', ['g' => $g])
                                @endforeach
                            </div>
                        </details>
                    @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
