@php
    $atts = \App\Models\Attachment::where('attachable_type', $type)->where('attachable_id', (string) $id)->current()->orderByDesc('created_at')->get()
        ->filter(fn ($a) => \App\Support\AttachableEntities::canView(auth()->user(), $type, $a->category));
    $canEdit = \App\Support\AttachableEntities::canEdit(auth()->user(), $type);
    $canSensitive = \App\Support\AttachableEntities::canEdit(auth()->user(), $type, 'ID_DOCUMENT');
    $older = \App\Models\Attachment::whereIn('document_id', $atts->pluck('document_id'))->whereNull('removed_at')->whereNotNull('superseded_at')->orderByDesc('version')->get()
        ->filter(fn ($a) => \App\Support\AttachableEntities::canView(auth()->user(), $type, $a->category))->groupBy('document_id');
    $cats = array_filter(\App\Support\AttachableEntities::CATEGORIES, fn ($k) => $canSensitive || ! \App\Support\AttachableEntities::isSensitive($k), ARRAY_FILTER_USE_KEY);
    $fmt = fn ($b) => $b >= 1048576 ? \App\Support\Formats::number($b / 1048576, 1).' MB' : \App\Support\Formats::number(max(1, $b / 1024), 0).' KB';
@endphp
<div class="panel">
    <div class="panel-header"><h2>Files</h2><span class="muted small">{{ $atts->count() }} attached</span></div>
    <div class="panel-body">
        @if ($errors->has('files') || $errors->has('files.*') || $errors->has('removal_reason'))
            <div class="alert alert-error">{{ $errors->first('files') ?: ($errors->first('files.0') ?: $errors->first('removal_reason')) }}</div>
        @endif
    </div>
    @if ($atts->isNotEmpty())
        <div class="table-wrap"><table><thead><tr><th>File</th><th>Category</th><th>Size</th><th>Added</th><th></th></tr></thead><tbody>
            @foreach ($atts as $a)
                <tr><td style="display:flex;gap:10px;align-items:center">@if($a->isImage())<a href="{{ route('attachments.download', $a) }}" target="_blank" data-lightbox="photos-{{ $type }}-{{ $id }}" data-caption="{{ $a->title ?: $a->original_name }}"><img src="{{ route('attachments.download', $a) }}" alt="" loading="lazy" style="width:44px;height:44px;object-fit:cover;border-radius:5px;border:1px solid var(--border);padding:0;min-height:0"></a>@endif<div><a href="{{ route('attachments.download', $a) }}" @if($a->isInlineViewable()) target="_blank" @endif>{{ $a->title ?: $a->original_name }}</a>
                        @if(\App\Support\DocumentVersioning::appliesTo($a->category))<span class="status status-completed" title="{{ $a->change_note }}">v{{ $a->version }}@if($a->revision_label) · {{ $a->revision_label }}@endif</span> @endif @if($a->title)<div class="muted small">{{ $a->original_name }}</div>@endif<div class="muted small" title="SHA-256 {{ $a->sha256 }}">{{ substr($a->sha256, 0, 12) }}…</div>
                        @if (\App\Support\DocumentVersioning::appliesTo($a->category))
                            @if ($older->has($a->document_id))
                                <details class="muted small"><summary>History ({{ $older[$a->document_id]->count() }} earlier)</summary>
                                    @foreach ($older[$a->document_id] as $o)<div><a href="{{ route('attachments.download', $o) }}" @if($o->isInlineViewable()) target="_blank" @endif>v{{ $o->version }}@if($o->revision_label) · {{ $o->revision_label }}@endif</a> · {{ $o->created_at->displayDate() }} · {{ $o->uploaded_by_name }}@if($o->change_note) · {{ $o->change_note }}@endif</div>@endforeach
                                </details>
                            @endif
                            @if ($canEdit)
                                <details class="small"><summary>New version</summary>
                                    <form method="POST" action="{{ route('attachments.version', $a) }}" enctype="multipart/form-data" style="display:grid;gap:6px;max-width:320px;margin-top:6px">@csrf
                                        <input type="file" name="file" required data-max-kb="{{ \App\Support\Uploads::effectiveMaxKb() }}"><input name="revision_label" placeholder="Revision, e.g. Rev C" maxlength="30"><input name="change_note" placeholder="What changed" maxlength="300">
                                        <div><button class="btn btn-small btn-primary">Add version</button></div>
                                    </form>
                                </details>
                            @endif
                        @endif
                    </div></td>
                    <td>{{ \App\Support\AttachableEntities::CATEGORIES[$a->category] ?? $a->category }}</td><td>{{ $fmt($a->size_bytes) }}</td>
                    <td>{{ $a->created_at->displayDateTime() }}<div class="muted small">{{ $a->uploaded_by_name }}</div></td>
                    <td>@if($canEdit)@if($a->isImage())@if($a->is_cover)<span class="status status-completed">Main photo</span>@else<form method="POST" action="{{ route('attachments.cover', $a) }}" style="display:inline">@csrf<button class="btn btn-small">Use as main photo</button></form>@endif @endif<form method="POST" action="{{ route('attachments.remove', $a) }}" style="display:flex;gap:6px;margin-top:4px">@csrf<input name="removal_reason" placeholder="Reason" required style="width:130px">@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'name' => $a->title ?: $a->original_name])</form>@endif</td></tr>
            @endforeach
        </tbody></table></div>
    @endif
    @include('attachments._related', ['type' => $type, 'id' => $id, 'canEdit' => $canEdit])
    @if ($canEdit)
    <div class="panel-body" style="border-top:1px solid var(--border,#ddd)">
        <form method="POST" action="{{ route('attachments.store', [$type, $id]) }}" enctype="multipart/form-data" class="form-grid">@csrf
            <label>Files<input type="file" name="files[]" multiple required data-max-kb="{{ \App\Support\Uploads::effectiveMaxKb() }}"></label>
            <label>Category<select name="category">@foreach ($cats as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label>Title (optional)<input name="title" maxlength="200"></label>
            <div><button class="btn btn-primary">Attach</button></div>
        </form>
        <p class="muted small">PDF, images, Excel/Word, CSV, text and CAD files up to {{ \App\Support\Uploads::megabytes(\App\Support\Uploads::effectiveMaxKb()) }} each, 10 files at a time. Files are stored privately.</p>
        @if (\App\Support\Uploads::serverLimitsApp())<p class="small" style="color:var(--warning)">This server's PHP upload limit ({{ \App\Support\Uploads::megabytes(\App\Support\Uploads::phpFileLimitKb()) }}) is lower than the app setting ({{ \App\Support\Uploads::megabytes(\App\Support\Uploads::appMaxKb()) }}). Ask the administrator to raise upload_max_filesize and post_max_size.</p>@endif
    </div>
    @endif
</div>
