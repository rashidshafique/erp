{{-- A dialog for recording a thermal test, and (when the heat is fixed) a chemical analysis, from any screen but the record page.
     Opened by any [data-quick="$id"] button; add data-heat="<heat id>" to a button to pick the heat when the dialog offers a picker.
     $id (default 'qd-record-test'). The heat is either fixed, $heat, or chosen from $heats (selected: $selected). Tabs: thermal
     always, chemistry only for a fixed heat. A saved test returns to the page it was opened from; an error reopens the dialog. --}}
@php
    $id = $id ?? 'qd-record-test';
    $fixed = $heat ?? null;
    $heats = $heats ?? collect();
    $isMine = old('_quick') === $id;
    $tab = $isMine ? old('_tab', 'thermal') : 'thermal';
    $gauges = $gauges ?? \App\Models\Gauge::where('status', 'IN_SERVICE')->orderBy('code')->get();
    $ip = $id.'-';
    // The heat as the dialog title shows it: line, grade and the grade's target CE.
    $ceRange = fn ($h) => $h?->ironGrade && ($h->ironGrade->ce_min !== null || $h->ironGrade->ce_max !== null)
        ? rtrim(rtrim((string) ($h->ironGrade->ce_min ?? '…'), '0'), '.').'–'.rtrim(rtrim((string) ($h->ironGrade->ce_max ?? '…'), '0'), '.').'%' : null;
    $heatInfo = fn ($h) => 'Line: '.($h->furnace?->code ?? '—').' · Grade: '.($h->ironGrade?->code ?? 'none').($ceRange($h) ? ' · Target CE: '.$ceRange($h) : '');
@endphp
@pushOnce('dialogs', $id)
<dialog id="{{ $id }}" class="qd qd-wide" aria-labelledby="{{ $id }}-title" @if ($isMine && $errors->any()) data-reopen="{{ json_encode($errors->getMessages()) }}" @endif>
    <div class="qd-head"><h2 id="{{ $id }}-title" class="qd-heat-title">
        @if ($fixed)
            <span class="qd-heat"><x-heat-light :heat="$fixed" /> Heat <strong>{{ $fixed->heat_number }}</strong></span>
            <span class="qd-heat-meta">{{ $heatInfo($fixed) }}</span>
        @else
            <span class="qd-heat">Record a test</span><span class="qd-heat-meta" data-heat-info></span>
        @endif
    </h2>@include('thermal._analyser', ['ip' => $ip.'t-', 'gauges' => $gauges, 'mine' => $isMine && $tab === 'thermal', 'form' => $id.'-thermal-form'])
        <button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
    <div class="qd-tabs" role="tablist">
        <button type="button" role="tab" data-rt-tab="thermal" aria-selected="{{ $tab === 'thermal' ? 'true' : 'false' }}">Thermal test</button>
        @if ($fixed)<button type="button" role="tab" data-rt-tab="chemistry" aria-selected="{{ $tab === 'chemistry' ? 'true' : 'false' }}">Chemical analysis</button>@endif
        <span class="qd-tab-actions" data-rt-actions @if ($tab !== 'thermal') hidden @endif><button type="button" class="btn btn-small" data-all-now>All timestamps now</button><button type="button" class="link-btn" data-clear-cups>Clear</button></span>
    </div>
    <div class="qd-body">
        <div class="alert alert-error qd-errors" role="alert" @unless ($isMine && $errors->any()) hidden @endunless>@if ($isMine)@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach @endif</div>

        <form id="{{ $id }}-thermal-form" method="POST" action="{{ route('thermal.store') }}" enctype="multipart/form-data" data-rt-pane="thermal" @if ($tab !== 'thermal') hidden @endif>@csrf
            <input type="hidden" name="_quick" value="{{ $id }}"><input type="hidden" name="_tab" value="thermal"><input type="hidden" name="_dialog" value="1">
            @include('thermal._fields', ['ip' => $ip.'t-', 'mine' => $isMine && $tab === 'thermal', 'fixedHeat' => $fixed, 'heats' => $heats, 'selected' => $isMine ? old('heat_id') : ($heats->first()?->id), 'gauges' => $gauges, 'heatInfo' => $heatInfo, 'inDialog' => true])
            <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">Analyse &amp; save test</button></div>
        </form>

        @if ($fixed)
        <form method="POST" action="{{ route('heats.chemistry.store', $fixed) }}" data-rt-pane="chemistry" @if ($tab !== 'chemistry') hidden @endif>@csrf
            <input type="hidden" name="_quick" value="{{ $id }}"><input type="hidden" name="_tab" value="chemistry">
            <p class="muted small">{{ $fixed->ironGrade ? 'Judged against '.$fixed->ironGrade->code.'.' : 'This heat has no iron grade, so results are saved but not judged.' }} Carbon equivalent is worked out as C + (Si + P) / 3.</p>
            @include('heats._chemistry-fields', ['heat' => $fixed, 'ip' => $ip.'c-', 'mine' => $isMine && $tab === 'chemistry'])
            <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save analysis</button></div>
        </form>
        @endif
    </div>
</dialog>
@endPushOnce
@include('partials.quick-dialog-assets')
@include('thermal._fields-assets')
@include('partials.record-test-dialog-assets')
