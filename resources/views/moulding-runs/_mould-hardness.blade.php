{{-- Mould hardness (B scale) for a run: cope and drag spots on one mould, judged on the lowest and the average against the limits
     set for the run's sand system. $run. --}}
@php
    $mouldTests = \App\Models\MeasurementSet::with('measurements', 'mouldAssembly')->where('kind', 'MOULD')->where('moulding_run_id', $run->id)->orderByDesc('taken_at')->limit(15)->get();
    $spots = \App\Http\Controllers\MouldTestController::SPOTS;
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp
<section class="panel" id="mould-hardness">
    <div class="panel-header"><h2>Mould hardness (B scale)</h2><span class="muted small">Taken on the compacted mould; limits are set per sand system in the Sand lab.</span></div>
    <div class="panel-body">
        @if ($errors->has('mould_test'))<div class="alert alert-error">{{ $errors->first('mould_test') }}</div>@endif
        @if ($mouldTests->first()?->result === 'FAIL')<div class="alert alert-error">The latest mould hardness test failed: {{ implode('; ', $mouldTests->first()->findings ?? []) }}</div>@endif
        <form method="POST" action="{{ route('moulding-runs.mould-tests.store', $run) }}" class="form-grid">@csrf
            <div class="field"><label for="mt-mould">Mould tested</label>
                <select id="mt-mould" name="mould_assembly_id" required><option value="">Choose a mould</option>@foreach ($run->mouldAssemblies as $a)<option value="{{ $a->id }}" @selected(old('mould_assembly_id') === $a->id)>{{ $a->assembly_number }}</option>@endforeach</select></div>
            @foreach (['cope' => 'Cope', 'drag' => 'Drag'] as $half => $title)
                <div class="field field-full"><label>{{ $title }} spots</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">@for ($i = 0; $i < $spots; $i++)<input type="number" step="any" min="0" name="{{ $half }}[]" value="{{ old($half.'.'.$i) }}" style="width:84px" aria-label="{{ $title }} spot {{ $i + 1 }}">@endfor</div></div>
            @endforeach
            @include('maintenance::gauges._select', ['plain' => true, 'id' => 'mould_gauge', 'label' => 'Hardness tester from the register', 'types' => ['HARDNESS_TESTER']])
            <div class="field"><label for="mt-by">Tested by</label><input id="mt-by" name="tested_by" maxlength="100" value="{{ old('tested_by') }}"></div>
            <div class="field" style="align-self:end"><button class="btn btn-primary" type="submit">Save hardness test</button></div>
        </form>
    </div>
    <div class="table-wrap"><table><thead><tr><th>When</th><th>Mould</th><th>Cope</th><th>Drag</th><th>Lowest</th><th>Average</th><th>Result</th></tr></thead><tbody>
    @forelse ($mouldTests as $t)
        @php($by = $t->measurements->keyBy('characteristic_key'))
        <tr><td>{{ $t->taken_at->displayShortDateTime() }}</td><td class="code">{{ $t->mouldAssembly?->assembly_number ?? '—' }}</td>
            <td class="code">{{ $t->measurements->filter(fn ($m) => str_starts_with($m->characteristic_key, 'cope_spot_'))->map(fn ($m) => $fmt($m->value))->implode(' · ') ?: '—' }}</td>
            <td class="code">{{ $t->measurements->filter(fn ($m) => str_starts_with($m->characteristic_key, 'drag_spot_'))->map(fn ($m) => $fmt($m->value))->implode(' · ') ?: '—' }}</td>
            <td class="code">{{ $by->has('hardness_b_min') ? $fmt($by['hardness_b_min']->value) : '—' }}</td><td class="code">{{ $by->has('hardness_b_avg') ? $fmt($by['hardness_b_avg']->value) : '—' }}</td>
            <td><span class="status {{ $t->result === 'PASS' ? 'status-completed' : ($t->result === 'FAIL' ? 'status-cancelled' : '') }}">{{ $t->result }}</span></td></tr>
    @empty
        <tr><td colspan="7" class="muted">No mould hardness test for this run yet.</td></tr>
    @endforelse
    </tbody></table></div>
</section>
