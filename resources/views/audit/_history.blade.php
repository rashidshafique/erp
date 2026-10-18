@php $hist = \App\Models\AuditLog::where('entity_type', $type)->where('entity_id', (string) $id)->orderByDesc('id')->limit(15)->get(); @endphp
<div class="panel">
    <div class="panel-header"><h2>History</h2><a class="small" href="{{ route('audit.index', ['entity_type' => $type, 'entity_id' => $id]) }}">Full history</a></div>
    <div class="table-wrap"><table><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Changes</th></tr></thead><tbody>
        @forelse ($hist as $e)<tr><td>{{ $e->occurred_at->setTimezone(config('app.timezone'))->displayShortDateTime() }}</td><td>{{ $e->user_name ?? '—' }}</td><td>{{ $e->action }}</td><td>@include('audit._changes', ['entry' => $e])</td></tr>
        @empty<tr><td colspan="4" class="muted">No changes recorded.</td></tr>@endforelse
    </tbody></table></div>
</div>
