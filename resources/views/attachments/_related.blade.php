{{-- Inside the Files panel: files linked here from other records, and the files of the records this one comes from. Needs $type, $id, $canEdit. --}}
@php
    $user = auth()->user();
    $linked = \App\Support\RelatedFiles::linked($type, (string) $id, $user);
    $related = \App\Support\DocumentVersioning::showRelated() ? \App\Support\RelatedFiles::for($type, (string) $id, $user) : collect();
    $versioned = \App\Support\DocumentVersioning::enabled();
    $label = fn ($f) => ($f->title ?: $f->original_name).(\App\Support\DocumentVersioning::appliesTo($f->category) ? ' · v'.$f->version.($f->revision_label ? ' '.$f->revision_label : '') : '');
@endphp
@if ($linked->isNotEmpty())
    <div class="panel-body" style="border-top:1px solid var(--border,#ddd)">
        <h3 class="section-title">Linked files</h3>
        @foreach ($linked as $f)
            <div style="display:flex;gap:10px;align-items:center;padding:3px 0">
                <a href="{{ route('attachments.download', $f) }}" @if($f->isInlineViewable()) target="_blank" @endif>{{ $label($f) }}</a>
                <span class="muted small">{{ \App\Support\AttachableEntities::CATEGORIES[$f->category] ?? $f->category }} · from {{ \App\Support\AttachableEntities::describe($f->attachable_type, $f->attachable_id) }}</span>
                @if ($canEdit)<form method="POST" action="{{ route('attachments.unlink', $f->link_id) }}" style="margin-left:auto">@csrf<button class="btn btn-small" title="The file stays on its own record">Unlink</button></form>@endif
            </div>
        @endforeach
    </div>
@endif
@if ($related->isNotEmpty())
    <div class="panel-body" style="border-top:1px solid var(--border,#ddd)">
        <div style="display:flex;gap:10px;align-items:center;justify-content:space-between"><h3 class="section-title" style="margin:0">From related records</h3>
            @if ($versioned && $canEdit)<form method="POST" action="{{ route('attachments.pin', [$type, $id]) }}">@csrf<button class="btn btn-small" title="Keep these versions on this record even if newer ones are uploaded later">Record versions used</button></form>@endif</div>
        @foreach ($related as $group)
            <div style="margin-top:10px"><strong>@if($group['url'])<a href="{{ $group['url'] }}">{{ $group['label'] }}</a>@else{{ $group['label'] }}@endif</strong>
                @foreach ($group['files'] as $row)
                    @php $f = $row['file']; @endphp
                    <div style="display:flex;gap:10px;align-items:center;padding:2px 0 2px 12px">
                        <a href="{{ route('attachments.download', $f) }}" @if($f->isInlineViewable()) target="_blank" @endif>{{ $label($f) }}</a>
                        <span class="muted small">{{ \App\Support\AttachableEntities::CATEGORIES[$f->category] ?? $f->category }}</span>
                        @if ($row['pinned'])<span class="status status-completed" title="This record was made to this version">Made to this version</span>@endif
                        @if ($row['newer'])<span class="status status-hold" title="A newer version has been uploaded since">Newer: <a href="{{ route('attachments.download', $row['newer']) }}" @if($row['newer']->isInlineViewable()) target="_blank" @endif>v{{ $row['newer']->version }}@if($row['newer']->revision_label) {{ $row['newer']->revision_label }}@endif</a></span>@endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endif
@if ($canEdit)
    <div class="panel-body" style="border-top:1px solid var(--border,#ddd)">
        <details id="link-picker" data-find="{{ route('attachments.find') }}" data-type="{{ $type }}" data-id="{{ $id }}" data-link="{{ url('/attachments') }}">
            <summary>Link an existing file from another record</summary>
            <div style="display:flex;gap:8px;margin-top:8px;max-width:520px"><input id="link-q" placeholder="Part of the file name or title" autocomplete="off"></div>
            <div id="link-results" class="small" style="margin-top:6px"></div><input type="hidden" id="link-token" value="{{ csrf_token() }}">
        </details>
    </div>
    @once
    <script>
        (function () {
            var box = document.getElementById('link-picker'); if (!box) return;
            var q = document.getElementById('link-q'), out = document.getElementById('link-results'), timer;
            var token = document.getElementById('link-token').value;
            q.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    var text = q.value.trim(); out.textContent = '';
                    if (text.length < 2) return;
                    fetch(box.dataset.find + '?' + new URLSearchParams({ q: text, type: box.dataset.type, id: box.dataset.id }), { headers: { Accept: 'application/json' } })
                        .then(function (r) { return r.ok ? r.json() : []; }).then(function (rows) {
                            if (!rows.length) { out.textContent = 'No matching files on other records.'; return; }
                            rows.forEach(function (row) {
                                var form = document.createElement('form'); form.method = 'POST'; form.style.cssText = 'display:flex;gap:8px;align-items:center;padding:2px 0';
                                form.action = box.dataset.link + '/' + row.id + '/link/' + box.dataset.type + '/' + box.dataset.id;
                                var csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = token;
                                var label = document.createElement('span'); label.textContent = row.name + ' · ' + row.category + ' · ' + row.record;
                                var btn = document.createElement('button'); btn.className = 'btn btn-small'; btn.textContent = 'Link';
                                form.append(csrf, btn, label); out.appendChild(form);
                            });
                        });
                }, 250);
            });
        })();
    </script>
    @endonce
@endif
