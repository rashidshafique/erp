{{-- What can be done with a lot from its row on the production queue. $l (lot), $step (step key), $canMove (a later stage exists for this step) --}}
@if ($l->status === 'IN_PRODUCTION')
    @if ($canMove)<button type="button" class="btn btn-small" @click="moveOne(document.querySelector('input.queue-lot[value=\'{{ $l->id }}\']'))">Move</button>@endif
    <a class="btn btn-small" href="{{ route('lots.inspections.create', $l) }}">Inspect</a>
@elseif ($l->status === 'ON_HOLD')
    <button class="btn btn-small" form="release-{{ $l->id }}" onclick="return confirm('Release this lot from hold?')">Release</button>
@endif
<a class="btn btn-small" href="{{ route('lots.traveller', $l) }}">Traveller</a>
@if ($l->status === 'IN_PRODUCTION')<a class="btn btn-small" href="{{ route('lots.show', $l) }}#hold">Hold…</a>@endif
