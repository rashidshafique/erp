{{-- One pattern revision of a product: photo, pattern, status chips, cavities and castings per mould. $g (revision and cavities), $product, $map, $photos. --}}
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
                    <span class="mono">{{ $pattern->code }} Rev {{ $rev->revision }}</span>
                    @if ($rev->is_current)<span class="status status-completed">Current</span>@elseif ($rev->isSuperseded())<span class="status status-on-hold">Superseded</span>@endif
                    @if ($pattern->trashed())<span class="status status-cancelled">{{ $pattern->custody === 'RETURNED' ? 'Returned' : ($pattern->custody === 'SCRAPPED' ? 'Scrapped' : 'Archived') }}</span>@endif
                    <span class="pill">{{ $pattern->owner_type === 'FOUNDRY' ? 'Foundry-owned' : 'Owner: '.$pattern->ownerLabel() }}</span>
                    <span class="pill">{{ \Illuminate\Support\Str::before($rev->arrangementLabel(), ' (') }}</span>
                    <span class="pill">{{ $g['cavities']->count() }} cavit{{ $g['cavities']->count() === 1 ? 'y' : 'ies' }}@if ($g['cavities']->contains('cavity_role', 'PART')) · {{ $g['cavities']->where('cavity_role', 'PART')->pluck('component_label')->unique()->implode(', ') }}@endif</span>
                </div>
            </div>
            <div class="mo-count"><strong>{{ rtrim(rtrim(\App\Support\Formats::number($perMould, 2), '0'), '.') }}</strong>per mould</div>
        </div>
