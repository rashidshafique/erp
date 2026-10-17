@php
    $atts = \App\Models\Attachment::where('attachable_type', $type)->where('attachable_id', (string) $id)->whereNull('removed_at')->orderByDesc('created_at')->get();
    $cats = \App\Support\AttachableEntities::CATEGORIES;
    $fmt = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : number_format(max(1, $b / 1024), 0).' KB';
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
                        @if($a->title)<div class="muted small">{{ $a->original_name }}</div>@endif<div class="muted small" title="SHA-256 {{ $a->sha256 }}">{{ substr($a->sha256, 0, 12) }}…</div></div></td>
                    <td>{{ $cats[$a->category] ?? $a->category }}</td><td>{{ $fmt($a->size_bytes) }}</td>
                    <td>{{ $a->created_at->format('d M Y H:i') }}<div class="muted small">{{ $a->uploaded_by_name }}</div></td>
                    <td>@if($a->isImage())@if($a->is_cover)<span class="status status-completed">Main photo</span>@else<form method="POST" action="{{ route('attachments.cover', $a) }}" style="display:inline">@csrf<button class="btn btn-small">Use as main photo</button></form>@endif @endif<form method="POST" action="{{ route('attachments.remove', $a) }}" style="display:flex;gap:6px;margin-top:4px">@csrf<input name="removal_reason" placeholder="Reason" required style="width:130px">@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'name' => $a->title ?: $a->original_name])</form></td></tr>
            @endforeach
        </tbody></table></div>
    @endif
    <div class="panel-body" style="border-top:1px solid var(--border,#ddd)">
        <form method="POST" action="{{ route('attachments.store', [$type, $id]) }}" enctype="multipart/form-data" class="form-grid">@csrf
            <label>Files<input type="file" name="files[]" multiple required></label>
            <label>Category<select name="category">@foreach ($cats as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label>Title (optional)<input name="title" maxlength="200"></label>
            <div><button class="btn btn-primary">Attach</button></div>
        </form>
        <p class="muted small">PDF, images, Excel/Word, CSV, text and CAD files up to {{ round(config('foundry.attachments.max_kb') / 1024, 0) }} MB each. Files are stored privately.</p>
    </div>
</div>
