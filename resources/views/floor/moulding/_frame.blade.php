{{-- The tablet frame for moulding screens: messages on top, the tab bar at the bottom (design C1). --}}
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>@endif
<x-floor.tab-bar :tabs="$tabs" :scan="route('scan')" />
