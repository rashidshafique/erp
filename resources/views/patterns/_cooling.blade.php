<div class="panel">
    <div class="panel-header"><h2>Cooling before shakeout</h2></div>
    <div class="panel-body">
        <p class="muted small">Minimum minutes a casting must stay in the mould after the pour, set per revision (heavy sections need longer). Shakeout earlier than this needs a written reason. Leave empty for no rule.</p>
        @foreach ($pattern->revisions as $revision)
            <form method="POST" action="{{ route('pattern-revisions.cooling', $revision->id) }}" style="display:flex;gap:10px;align-items:center;margin-bottom:8px">@csrf @method('PUT')
                <span style="width:90px">{{ $revision->shortCode() }}</span>
                <input type="number" min="0" name="min_cooling_minutes" value="{{ \Illuminate\Support\Facades\DB::table('pattern_revisions')->where('id', $revision->id)->value('min_cooling_minutes') }}" style="width:110px"> minutes
                <button class="btn">Save</button>
            </form>
        @endforeach
    </div>
</div>
