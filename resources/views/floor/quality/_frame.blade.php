{{-- The quality tablet frame: messages on top, the tab bar at the bottom (design C1). $active is 'lots' or 'ncrs'. --}}
@php($tabs = [
    ['label' => 'Home', 'href' => route('home')],
    ['label' => 'Lots', 'href' => route('floor.quality.lots'), 'active' => ($active ?? 'lots') === 'lots'],
    ['label' => 'NCRs', 'href' => route('ncrs.index'), 'active' => ($active ?? '') === 'ncrs'],
])
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>@endif
<x-floor.tab-bar :tabs="$tabs" :scan="route('scan')" />
