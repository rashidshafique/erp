{{-- Engraving mark with a way to record an existing one. Needs $tool and $type. --}}
@php($mark = $tool->ensureMark())
<div style="margin:6px 0">
    <span class="muted small">Engraved mark</span>
    <div style="font:700 22px monospace;letter-spacing:3px">{{ $mark }}</div>
    <details style="margin-top:4px"><summary class="small" style="cursor:pointer">Change (tool already engraved with another number)</summary>
        <form method="POST" action="{{ route('tools.mark', [$type, $tool->id]) }}" style="display:flex;gap:6px;margin-top:6px;max-width:320px">@csrf @method('PUT')
            <input name="mark_code" value="{{ $mark }}" maxlength="20" required><button class="btn btn-small">Save</button></form>
    </details>
    <a class="small" href="{{ route('labels.show', [$type, $tool->id]) }}" target="_blank">Print label →</a>
</div>
