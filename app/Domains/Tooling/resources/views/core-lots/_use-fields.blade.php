{{-- Fields of the lot's Use dialog (partials.quick-dialog): cores used in moulding, outside a run's own Issue. --}}
@if ($part === 'required')
    <input type="hidden" name="kind" value="USED">
    <div class="field"><label for="us-quantity">Cores <span class="required">*</span></label><input id="us-quantity" type="number" name="quantity" required min="1" max="{{ $remaining }}" value="{{ $old('quantity', $remaining) }}"></div>
    <div class="field"><label for="us-date">Date <span class="required">*</span></label><input id="us-date" type="date" name="used_on" required min="{{ $lot->made_on->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" value="{{ $old('used_on', today()->format('Y-m-d')) }}"></div>
@else
    <div class="field field-full"><label for="us-run">Moulding run</label><select id="us-run" name="moulding_run_id"><option value="">— none —</option>@foreach ($runs as $r)<option value="{{ $r->id }}" @selected($old('moulding_run_id') === $r->id)>{{ $r->run_number }}@if ($r->patternRevision?->pattern) · {{ $r->patternRevision->pattern->name }}@endif</option>@endforeach</select></div>
@endif
