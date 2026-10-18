{{-- Engraving mark with a way to record an existing one. Needs $tool and $type; $bare leaves out the label when the page shows its own; $optional shows a blank when the tool has none instead of making one. --}}
@php($mark = empty($optional) ? $tool->ensureMark() : $tool->mark_code)
<div style="margin:{{ empty($bare) ? '6px 0' : '0' }}">
    @if (empty($bare))<span class="muted small">Engraved mark</span>@endif
    <div style="font:700 22px monospace;letter-spacing:3px">@if (filled($mark)){{ $mark }}@else<span class="muted" style="font:400 14px sans-serif;letter-spacing:0">Not engraved</span>@endif</div>
    <details style="margin-top:4px"><summary class="small" style="cursor:pointer">{{ filled($mark) ? 'Change (tool already engraved with another number)' : 'Record the engraved number' }}</summary>
        <form method="POST" action="{{ route('tools.mark', [$type, $tool->id]) }}" style="display:flex;gap:6px;margin-top:6px;max-width:320px">@csrf @method('PUT')
            <input name="mark_code" value="{{ $mark }}" minlength="3" maxlength="20" pattern="[A-Za-z0-9]+" required><button class="btn btn-small">Save</button></form>
    </details>
    @if (filled($mark))<a class="small" href="{{ route('labels.show', [$type, $tool->id]) }}" target="_blank">Print label →</a>@endif
</div>
