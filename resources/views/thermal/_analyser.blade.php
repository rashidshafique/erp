{{-- The analyser picker of a thermal test: thermal analysis units first, one chosen for a new test (preferring one whose calibration is in date).
     $ip, $gauges, $mine (old input belongs to this form), optional $form (id of the form it belongs to when it sits outside it). --}}
@php
    $mine = $mine ?? true;
    $isAnalyser = fn ($g) => $g->instrument_type === 'THERMAL_ANALYSER';
    $gauges = $gauges->sortBy(fn ($g) => ($isAnalyser($g) ? '0' : '1').$g->code)->values();
    $inDate = fn ($g) => ! in_array($g->calibrationStatus(), ['OVERDUE', 'NOT_CALIBRATED'], true);
    $defaultGauge = $gauges->first(fn ($g) => $isAnalyser($g) && $inDate($g)) ?? $gauges->first($isAnalyser);
    $chosen = $mine ? old('gauge_id', $defaultGauge?->id) : $defaultGauge?->id;
@endphp
<div class="strip-device"><label for="{{ $ip }}gauge_id">Analyser</label>
    <select id="{{ $ip }}gauge_id" name="gauge_id" @if ($form ?? null) form="{{ $form }}" @endif><option value="">—</option>@foreach ($gauges as $g)<option value="{{ $g->id }}" @selected($chosen === $g->id)>{{ $g->code }} · {{ $g->name }}@if (in_array($g->calibrationStatus(), ['OVERDUE', 'NOT_CALIBRATED'], true)) ({{ strtolower(\App\Services\Quality\CalibrationStatus::LABELS[$g->calibrationStatus()]) }})@endif</option>@endforeach</select></div>
