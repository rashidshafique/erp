{{-- A "quick add" form for a short lookup list, in a native <dialog>: a 520px box with the required fields first and the optional
     ones after them. It is pushed to the end of the page, so it may be included from inside another form.
     Opened by any [data-quick="$id"] button or link; Escape, Cancel, × or a click on the backdrop close it.
     - Opened without data-quick-target: it posts normally and the controller redirects back. A validation error reopens it with
       the input kept and the errors shown.
     - Opened from a "+ Add" link with data-quick-target="<select id>": it posts by fetch asking for JSON, adds the returned
       {id, label} to that select (and every select sharing its data-quick-list), selects it and closes. data-quick-value="label"
       uses the label as the option's value, for lists recorded by name.
     $id, $title, $action (URL), $submit (button text), $fields (a view rendered twice: $part = 'required' then 'optional',
     with $old($key, $default) giving this dialog's old input), $multipart (true when a field uploads a file). --}}
@php
    $isMine = old('_quick') === $id;
    $old = fn (string $key, mixed $default = null) => $isMine ? old($key, $default) : $default;
@endphp
@pushOnce('dialogs', $id)
<dialog id="{{ $id }}" class="qd" aria-labelledby="{{ $id }}-title" @if ($isMine && $errors->any()) data-reopen="{{ json_encode($errors->getMessages()) }}" @endif>
    <form method="POST" action="{{ $action }}"@if (! empty($multipart)) enctype="multipart/form-data"@endif>@csrf<input type="hidden" name="_quick" value="{{ $id }}">
        <div class="qd-head"><h2 id="{{ $id }}-title">{{ $title }}</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
        <div class="qd-body">
            <div class="alert alert-error qd-errors" role="alert" @unless ($isMine && $errors->any()) hidden @endunless>@if ($isMine)@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach @endif</div>
            <div class="qd-fields">@include($fields, ['part' => 'required', 'old' => $old])@include($fields, ['part' => 'optional', 'old' => $old])</div>
        </div>
        <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">{{ $submit }}</button></div>
    </form>
</dialog>
@endPushOnce
@include('partials.quick-dialog-assets')
