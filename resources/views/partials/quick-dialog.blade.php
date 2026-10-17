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
@pushOnce('dialogs', 'quick-dialog-assets')
<style>
.qd { width: min(520px, calc(100vw - 32px)); padding: 0; border: 0; border-radius: 12px; background: var(--surface); color: var(--text); box-shadow: 0 24px 60px rgb(15 23 42 / 28%); }
.qd::backdrop { background: rgb(15 23 42 / 45%); }
.qd-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border); }
.qd-head h2 { margin: 0; font-size: 15px; }
.qd-x { border: 0; background: transparent; font-size: 20px; line-height: 1; color: var(--muted); cursor: pointer; }
.qd-body { padding: 12px 16px 4px; max-height: calc(100vh - 160px); overflow-y: auto; }
.qd-errors { margin: 0 0 10px; }
.qd-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; }
.qd-fields .field-full { grid-column: 1 / -1; }
.qd-fields .field input, .qd-fields .field select, .qd-fields .field textarea { max-width: 100%; }
.qd [aria-invalid=true] { border-color: var(--danger) !important; }
.qd-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px 14px; }
.qd-add { margin-left: 6px; font-size: 12px; font-weight: 600; color: var(--accent); white-space: nowrap; }
@media (max-width: 768px) { .qd-fields { grid-template-columns: 1fr; } }
</style>
<script>
(function () {
    function showErrors(dlg, errors) {
        var box = dlg.querySelector('.qd-errors'), keys = Object.keys(errors || {});
        dlg.querySelectorAll('[aria-invalid]').forEach(function (f) { f.removeAttribute('aria-invalid'); });
        box.innerHTML = '';
        box.hidden = keys.length === 0;
        keys.forEach(function (k) {
            [].concat(errors[k]).forEach(function (m) { var d = document.createElement('div'); d.textContent = m; box.appendChild(d); });
            var field = dlg.querySelector('[name="' + k + '"]');
            if (field) field.setAttribute('aria-invalid', 'true');
        });
    }
    function open(dlg, link) {
        dlg.dataset.target = (link && link.dataset.quickTarget) || '';
        dlg.dataset.value = (link && link.dataset.quickValue) || 'id';
        if (dlg.showModal) { if (!dlg.open) dlg.showModal(); } else { dlg.setAttribute('open', ''); }
        var first = dlg.querySelector('.qd-fields input:not([type=hidden]), .qd-fields select, .qd-fields textarea');
        if (first) setTimeout(function () { first.focus(); }, 30);
    }
    function addOption(target, item, valueKey) {
        var value = valueKey === 'label' ? item.label : item.id, group = target.dataset.quickList;
        var selects = group ? document.querySelectorAll('select[data-quick-list="' + group + '"]') : [target];
        selects.forEach(function (s) {
            if (![].some.call(s.options, function (o) { return o.value === value; })) s.add(new Option(item.label, value));
        });
        target.value = value;
        target.dispatchEvent(new Event('change', { bubbles: true }));
    }
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-quick]');
        if (link) {
            var dlg = document.getElementById(link.dataset.quick);
            if (dlg) { e.preventDefault(); open(dlg, link); }
            return;
        }
        var d = e.target.closest('dialog.qd');
        if (d && (e.target === d || e.target.closest('[data-close]'))) d.close();
    });
    document.addEventListener('submit', function (e) {
        var form = e.target, dlg = form.closest('dialog.qd'), target = dlg && document.getElementById(dlg.dataset.target);
        if (!target || !window.fetch) return;
        e.preventDefault();
        var btn = form.querySelector('[type=submit]');
        btn.disabled = true;
        fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (body) { return { ok: r.ok, body: body }; }); })
            .then(function (res) {
                if (!res.ok) { showErrors(dlg, res.body.errors || { form: [res.body.message || 'It could not be saved. Try again.'] }); return; }
                addOption(target, res.body, dlg.dataset.value);
                form.reset(); showErrors(dlg, {}); dlg.close();
            })
            .catch(function () { showErrors(dlg, { form: ['The server could not be reached. Try again.'] }); })
            .then(function () { btn.disabled = false; });
    });
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('dialog.qd[data-reopen]').forEach(function (dlg) { open(dlg, null); showErrors(dlg, JSON.parse(dlg.dataset.reopen)); });
    });
})();
</script>
@endPushOnce
