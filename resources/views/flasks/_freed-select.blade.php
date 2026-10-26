{{-- When flasks are freed, as a small select that saves on change. $action (PUT URL), $value (own setting or null), $default (what applies
     when it is empty, e.g. 'POUR'), $from (where the default comes from, e.g. "Sheera"), $label (accessible name). --}}
<form method="POST" action="{{ $action }}" style="margin:0">@csrf @method('PUT')
    <select name="flasks_freed" onchange="this.form.submit()" aria-label="{{ $label ?? 'Flasks freed' }}" style="min-height:24px;padding:1px 6px;font-size:12px;max-width:220px">
        <option value="" @selected($value === null)>{{ \App\Services\Production\FlaskManager::freedLabel($default) }} ({{ $from }})</option>
        @foreach (\App\Services\Production\FlaskManager::FREED as $key => $text)<option value="{{ $key }}" @selected($value === $key)>{{ $text }}</option>@endforeach
    </select>
    <noscript><button class="btn btn-small">Save</button></noscript>
</form>
