@php
    $judge = app(\App\Services\Quality\ChemistryJudge::class);
    $readings = $heat->chemistryReadings()->get();
    $grade = $heat->ironGrade;
    $limitText = function ($key) use ($grade) {
        if (! $grade) return '';
        $min = $grade->{$key.'_min'}; $max = $grade->{$key.'_max'};
        if ($min === null && $max === null) return '';
        return ($min !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $min, 3), '0'), '.') : '') . '–' . ($max !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $max, 3), '0'), '.') : '');
    };
@endphp
<div class="panel" id="chemistry">
    <div class="panel-header"><h2>Chemical analysis</h2></div>
    <div class="panel-body">
        <p class="muted small">{{ $grade ? 'Judged against '.$grade->code.'.' : 'This heat has no iron grade, so results are saved but not judged.' }}
            Carbon equivalent is worked out as C + (Si + P) / 3.</p>

        @if ($readings->isNotEmpty())
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Stage</th><th>Tested</th>
                @foreach (\App\Services\Quality\ChemistryJudge::COLUMNS as $key => $col)<th>{{ \App\Models\IronGrade::ELEMENTS[$key] }}<div class="muted small">{{ $limitText($key) }}</div></th>@endforeach
                <th>CE</th><th>Result</th><th></th></tr></thead>
            <tbody>
            @foreach ($readings->sortByDesc(fn ($r) => $r->tested_at ?? $r->created_at) as $r)
                @php $v = $judge->judge($grade, $r->only(array_values(\App\Services\Quality\ChemistryJudge::COLUMNS)), $r->carbon_equivalent !== null ? (float) $r->carbon_equivalent : null); @endphp
                <tr>
                    <td>{{ $r->reading_number }}</td>
                    <td>{{ str_replace('_', ' ', $r->sample_stage) }}<div class="muted small">{{ $r->sample_reference }}</div></td>
                    <td>{{ $r->tested_at?->displayAt() }}<div class="muted small">{{ $r->tested_by }}</div></td>
                    @foreach (\App\Services\Quality\ChemistryJudge::COLUMNS as $key => $col)<td>{{ $r->$col !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $r->$col, 3), '0'), '.') : '—' }}</td>@endforeach
                    <td>{{ $r->carbon_equivalent !== null ? \App\Support\Formats::number((float) $r->carbon_equivalent, 2) : '—' }}</td>
                    <td>
                        @if ($v['ok'] === null)<span class="status">NOT JUDGED</span>
                        @elseif ($v['ok'])<span class="status status-completed">IN LIMITS</span>
                        @else<span class="status status-cancelled" title="{{ implode(' ', $v['findings']) }}">OUT OF LIMITS</span>@endif
                        @if ($r->is_verified)<div class="muted small">verified</div>@endif
                    </td>
                    <td>@unless ($r->is_verified)<form method="POST" action="{{ route('heats.chemistry.verify', [$heat, $r]) }}">@csrf<button class="btn" type="submit">Verify</button></form>@endunless</td>
                </tr>
                @if ($v['ok'] === false)<tr><td></td><td colspan="{{ 5 + count(\App\Services\Quality\ChemistryJudge::COLUMNS) }}" class="muted small" style="color:var(--danger)">{{ implode(' ', $v['findings']) }}</td></tr>@endif
            @endforeach
            </tbody>
        </table></div>
        @else
            <p class="muted">No analysis recorded yet.</p>
        @endif

        <h3 style="margin-top:22px;font-size:15px">Record an analysis</h3>
        @error('chemistry')<div class="alert alert-error">{{ $message }}</div>@enderror
        <form method="POST" action="{{ route('heats.chemistry.store', $heat) }}">@csrf
            @include('heats._chemistry-fields', ['heat' => $heat, 'ip' => '', 'mine' => true, 'limitText' => $limitText])
            <button class="btn btn-primary" type="submit">Save analysis</button>
        </form>
    </div>
</div>
