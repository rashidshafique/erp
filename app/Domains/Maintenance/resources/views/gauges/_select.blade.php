@if (app(\App\Domains\Core\ModuleRegistry::class)->isEnabled('maintenance'))
{{-- Instrument picker. $types narrows the list to the kinds that fit, falling back to every instrument in service when none of those kinds are registered. $plain renders the bare-label style some forms use instead of a .field block. --}}
@php
    $inService = \App\Domains\Maintenance\Models\Gauge::where('status', 'IN_SERVICE')->orderBy('code')->get();
    $fitting = isset($types) ? $inService->whereIn('instrument_type', $types) : $inService;
    $choices = $fitting->isNotEmpty() ? $fitting : $inService;
    $fieldId = $id ?? 'gauge_id';
    $text = $label ?? 'Instrument from the register';
@endphp
@if (! empty($plain))<label>{{ $text }}@else<div class="field"><label for="{{ $fieldId }}">{{ $text }}</label>@endif
    <select id="{{ $fieldId }}" name="gauge_id">
        <option value="">—</option>
        @foreach ($choices as $g)
            <option value="{{ $g->id }}" @selected(old('gauge_id') === $g->id)>{{ $g->code }} · {{ $g->name }}@if (in_array($g->calibrationStatus(), ['OVERDUE', 'NOT_CALIBRATED'], true)) ({{ strtolower(\App\Domains\Maintenance\Services\CalibrationStatus::LABELS[$g->calibrationStatus()]) }})@endif</option>
        @endforeach
    </select>
@if (! empty($plain))</label>@else</div>@endif
@endif
