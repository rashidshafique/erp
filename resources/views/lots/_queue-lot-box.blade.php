{{-- A lot's tick box on the production queue, carrying what the move bar needs to suggest stages and warn. $r (queue row), $step (step key) --}}
@php $l = $r['lot']; @endphp
<input type="checkbox" name="lots[]" value="{{ $l->id }}" class="queue-lot" aria-label="Select {{ $l->label }}" @checked(in_array($l->id, (array) old('lots', []), true))
    data-label="{{ $l->label }}" data-code="{{ Str::lower(trim($l->label.' '.$l->lot_number)) }}" data-step="{{ $step }}" data-seq="{{ $r['last_seq'] }}"
    data-pieces="{{ $r['pieces'] }}" data-age="{{ $r['age_h'] }}" data-cool="{{ $r['cool']['wait'] ?? 0 }}">
