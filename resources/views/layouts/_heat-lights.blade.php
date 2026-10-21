@if ($topbarHeats->isNotEmpty())
<div class="heat-lights" aria-label="Heats on the floor">
    @foreach ($topbarHeats->take(4) as $h)
        <a class="heat-chip" href="{{ $h->light() === 'green' && $h->pours->first() ? route('pours.show', $h->pours->first()) : route('heats.show', $h) }}" title="{{ $h->lightLabel() }}"><x-heat-light :heat="$h" /><span>{{ $h->heat_number }}</span></a>
    @endforeach
    @if ($topbarHeats->count() > 4)<a class="heat-chip" href="{{ route('heats.index') }}">+{{ $topbarHeats->count() - 4 }} more</a>@endif
</div>
@endif
