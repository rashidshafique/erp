{{-- The patterns that make a product, one entry per pattern revision: which cavities make it and how many of the product
     one mould gives. Current revisions come first; superseded and archived ones stay listed for traceability. --}}
@php
    $map = app(\App\Services\PatternCavityMap::class);
    $groups = $product->revisions->flatMap->patternCavities
        ->groupBy('pattern_revision_id')
        ->map(fn ($cavities) => ['revision' => $cavities->first()->patternRevision, 'cavities' => $cavities->sortBy('cavity_number')->values()])
        ->sortBy(fn ($g) => [! $g['revision']->is_current, $g['revision']->pattern->name, -$g['revision']->sequence])
        ->values();
    $photos = \App\Models\Attachment::where('attachable_type', 'pattern')->whereIn('attachable_id', $groups->map(fn ($g) => $g['revision']->pattern_id)->unique())
        ->whereNull('removed_at')->whereIn('mime_type', ['image/png', 'image/jpeg', 'image/webp'])
        ->orderByDesc('is_cover')->orderByDesc('created_at')->get()->unique('attachable_id')->keyBy('attachable_id');
@endphp
<style>
    .made-on { display: grid; gap: 10px; }
    .made-on .mo-row { display: grid; grid-template-columns: 56px minmax(0, 1fr) auto; gap: 12px 16px; align-items: center; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; }
    .made-on .mo-row.old { opacity: .75; }
    .made-on .mo-photo { width: 56px; height: 56px; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); }
    .made-on .mo-photo img { width: 100%; height: 100%; object-fit: cover; padding: 0; min-height: 0; }
    .made-on .mo-name { font-size: 15px; font-weight: 600; }
    .made-on .mo-meta { display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: center; font-size: 12.5px; color: var(--muted); margin-top: 3px; }
    .made-on .mo-count { text-align: right; font-size: 12px; color: var(--muted); }
    .made-on .mo-count strong { display: block; font-size: 20px; color: var(--text); }
    @media (max-width: 700px) { .made-on .mo-row { grid-template-columns: 48px 1fr; } .made-on .mo-count { grid-column: 2; text-align: left; } }
</style>
<div class="panel">
    <div class="panel-header"><h2>Made on these patterns</h2><span class="muted small">Cavities are linked to products on each pattern's cavity map.</span></div>
    <div class="panel-body">
        @if ($groups->isEmpty())
            <div class="empty-state" style="padding:12px 0">No pattern cavity is linked to this product yet. Link cavities from the <a href="{{ route('patterns.index') }}">pattern page</a>.</div>
        @else
            <div class="made-on">
                @foreach ($groups as $g)
                    @php
                        $rev = $g['revision']; $pattern = $rev->pattern; $photo = $photos[$pattern->id] ?? null;
                        $perMould = $map->expectedProductsPerMould($rev, $product->id);
                    @endphp
                    <div class="mo-row {{ $rev->is_current && ! $pattern->trashed() ? '' : 'old' }}">
                        <a class="mo-photo" href="{{ $photo ? route('attachments.download', $photo) : route('patterns.show', $pattern) }}" @if ($photo) data-lightbox="made-on-{{ $product->id }}" data-caption="{{ $pattern->name }}" @endif>
                            @if ($photo)<img src="{{ route('attachments.download', $photo) }}" alt="" loading="lazy">@else{!! \App\Support\NavIcons::get('layers', 22) !!}@endif
                        </a>
                        <div>
                            <div class="mo-name"><a href="{{ route('patterns.show', $pattern) }}#cavities-{{ $rev->id }}">{{ $pattern->name }}</a></div>
                            <div class="mo-meta">
                                <span class="mono">{{ $pattern->code }}</span>
                                <span class="mono">{{ $rev->code }}</span>
                                @if ($rev->is_current)<span class="status status-completed">Current</span>@elseif ($rev->isSuperseded())<span class="status status-on-hold">Superseded</span>@endif
                                @if ($pattern->trashed())<span class="status status-cancelled">Archived</span>@endif
                                <span>{{ \Illuminate\Support\Str::before($rev->arrangementLabel(), ' (') }}</span>
                                <span>cavit{{ $g['cavities']->count() === 1 ? 'y' : 'ies' }} {{ $g['cavities']->map(fn ($c) => $c->cavity_number.($c->cavity_mark ? ' ('.$c->cavity_mark.')' : ''))->implode(', ') }}@if ($g['cavities']->contains('cavity_role', 'PART')) · {{ $g['cavities']->where('cavity_role', 'PART')->pluck('component_label')->unique()->implode(', ') }}@endif</span>
                            </div>
                        </div>
                        <div class="mo-count"><strong>{{ rtrim(rtrim(number_format($perMould, 2), '0'), '.') }}</strong>per mould</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
