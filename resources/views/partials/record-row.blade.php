{{-- One row of a record card (.record-list): a round icon, a title with an optional note, a sub line, and an optional edit link or button.
     $icon (NavIcons name), $title, $note?, $sub?, $editHref? or $editTarget? (id of a <details> to open), $editLabel? --}}
<div class="record-row">
    <span class="record-ico" aria-hidden="true">{!! \App\Support\NavIcons::get($icon ?? 'box', 17) !!}</span>
    <div class="record-text">
        <div class="record-title">{{ $title }}@if (! empty($note)) <span class="record-note">({{ $note }})</span>@endif</div>
        @if (! empty($sub))<div class="record-sub">{{ $sub }}</div>@endif
    </div>
    @if (! empty($editHref))
        @include('partials.icon-button', ['type' => 'edit', 'href' => $editHref, 'word' => $editLabel ?? 'Edit', 'name' => $title])
    @elseif (! empty($editTarget))
        <button type="button" class="icon-btn icon-btn-edit" title="{{ $editLabel ?? 'Edit' }}" aria-label="{{ ($editLabel ?? 'Edit').' '.$title }}" onclick="var d=document.getElementById('{{ $editTarget }}');d.open=true;d.scrollIntoView({block:'nearest'})">{!! \App\Support\NavIcons::get('pencil', 16) !!}</button>
    @endif
</div>
