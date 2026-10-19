@foreach (app(\App\Domains\Core\ModuleRegistry::class)->navGroups() as $group)
    @php($patterns = array_merge($group['active'], ...array_column($group['items'], 'active')))
    <div class="nav-group {{ request()->routeIs(...$patterns) ? 'is-active' : '' }}">
        <button type="button" class="nav-group-toggle" aria-expanded="false">
            <span class="nav-lab">{!! \App\Support\NavIcons::get($group['icon']) !!}<span>{{ $group['label'] }}</span></span><span class="nav-chevron">›</span>
        </button>
        <div class="nav-submenu" hidden>
            @foreach ($group['items'] as $item)
                @if (! isset($item['visible']) || ($item['visible'])())
                    <a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs(...$item['active']) ? 'active' : '' }}">{!! \App\Support\NavIcons::get($item['icon']) !!}<span>{{ $item['label'] }}</span></a>
                @endif
            @endforeach
        </div>
    </div>
@endforeach
