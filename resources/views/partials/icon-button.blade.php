{{-- A row tool shown as an icon: a pencil for edit, a bin for delete or remove, two sheets for duplicate. The word stays in title and aria-label
     ("Delete River sand"), so tooltips and screen readers still read it. What the button does is set by the caller.
     type: edit|delete|duplicate; word (default Edit/Delete); name (the row's name, for the label); href (renders a link);
     form (form="…" attribute); buttonType (default submit); confirm (onclick confirm text); data (data-* attributes);
     class (extra classes); tag ('summary' for a <details> toggle). --}}
@php
    $type = $type ?? 'edit';
    $word = $word ?? ['edit' => 'Edit', 'delete' => 'Delete', 'duplicate' => 'Duplicate'][$type];
    $tag = isset($href) ? 'a' : ($tag ?? 'button');
    $attributes = new \Illuminate\View\ComponentAttributeBag(array_filter([
        'class' => trim('icon-btn icon-btn-'.$type.' '.($class ?? '')),
        'title' => $word,
        'aria-label' => trim($word.' '.($name ?? '')),
        'href' => $href ?? null,
        'type' => $tag === 'button' ? ($buttonType ?? 'submit') : null,
        'form' => $form ?? null,
        'onclick' => isset($confirm) ? 'return confirm('.\Illuminate\Support\Js::from($confirm).')' : null,
    ] + collect($data ?? [])->mapWithKeys(fn ($value, $key) => ['data-'.$key => $value])->all(), fn ($value) => $value !== null));
@endphp
<{{ $tag }} {{ $attributes }}>{!! \App\Support\NavIcons::get(['edit' => 'pencil', 'delete' => 'trash', 'duplicate' => 'copy'][$type], 16) !!}</{{ $tag }}>
