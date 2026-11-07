{{-- The furnace tablet frame: messages on top, the tab bar at the bottom (design C1). $active is 'heat' or 'heats'. --}}
@php($tabs = app(\App\Services\Production\FurnaceFloor::class)->tabs($active ?? 'heat'))
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>@endif
<x-floor.tab-bar :tabs="$tabs" :scan="route('scan')" />
