{{-- A pattern plate on the work list: its photo, name, code with revision, and type. Needs $revision (a PatternRevision)
     and $patternPhotos (main photo per pattern id, from Attachment::coversFor). --}}
@php
    $pattern = $revision->pattern;
    $photo = $patternPhotos[$pattern->id] ?? null;
@endphp
<div class="wl-plate">
    @if ($photo)
        <a href="{{ route('attachments.download', $photo) }}" data-lightbox="wl-plates" data-caption="{{ $pattern->name }} · {{ $pattern->code }}">
            <img src="{{ route('attachments.download', $photo) }}" alt="" loading="lazy">
        </a>
    @else
        <span class="wl-plate-none" title="No photo of this plate yet">{!! \App\Support\NavIcons::get('layers', 18) !!}</span>
    @endif
    <div class="wl-plate-text">
        <a href="{{ route('patterns.show', $pattern) }}"><strong>{{ $pattern->name }}</strong></a>
        <div class="muted small">{{ $pattern->code }} rev {{ $revision->revision }}</div>
        <div class="muted small">{{ str_replace('_', ' ', $pattern->pattern_type) }}</div>
    </div>
</div>
@once
<style>
    .wl-plate { display: flex; gap: 10px; align-items: center; }
    .wl-plate > a, .wl-plate-none { flex: none; width: 44px; height: 44px; border-radius: 5px; border: 1px solid var(--border); overflow: hidden; }
    .wl-plate img { display: block; width: 100%; height: 100%; object-fit: cover; padding: 0; min-height: 0; }
    .wl-plate-none { display: inline-flex; align-items: center; justify-content: center; border-style: dashed; color: var(--muted); }
    .wl-plate-text { min-width: 0; line-height: 1.3; }
</style>
@endonce
