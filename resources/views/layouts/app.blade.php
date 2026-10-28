<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Foundry ERP') — {{ \App\Support\UiSettings::brand()['name'] }}</title>
    @php($uiFont = \App\Support\UiSettings::font())
    @if($uiFont['url'])<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="{{ $uiFont['url'] }}">@endif

    @php($css = fn (string $file): string => asset('css/'.$file.'.css').'?v='.filemtime(public_path('css/'.$file.'.css')))
    <link rel="stylesheet" href="{{ $css('app-base') }}">
    @if (\App\Support\UiSettings::all()['compact'])<link rel="stylesheet" href="{{ $css('app-compact') }}">@endif
    <link rel="stylesheet" href="{{ $css('app-overrides') }}">
    {{-- Values the administrator sets under Settings → Display; everything else lives in public/css/app-*.css --}}
    <style>
        :root {
            --accent: {{ \App\Support\UiSettings::theme()['accent'] }} !important;
            --accent-hover: {{ \App\Support\UiSettings::theme()['hover'] }} !important;
            --accent-dark: {{ \App\Support\UiSettings::theme()['dark'] }};
            --accent-soft: {{ \App\Support\UiSettings::theme()['soft'] }};
            --side-w: {{ \App\Support\UiSettings::all()['compact'] ? 215 : 245 }}px;
            --ui-font: {!! $uiFont['stack'] !!};
        }
    </style>
    <script>try{if(matchMedia('(min-width:769px) and (max-width:1024px)').matches||(matchMedia('(min-width:1025px)').matches&&localStorage.getItem('erp.menuSlim')==='1'))document.documentElement.classList.add('menu-slim');}catch(e){}</script>
    <script>try{var w=parseInt(localStorage.getItem('erp.menuWidth'),10);if(w>=200&&w<=420)document.documentElement.style.setProperty('--side-w',w+'px');}catch(e){}</script>
    @stack('styles')
</head>
<body class="menu-labels" data-row-click="{{ \App\Models\AppSetting::getValue('ui.row_click', '1') === '1' ? '1' : '0' }}" data-toolbar="{{ \App\Support\UiSettings::all()['toolbar'] ? '1' : '0' }}" data-tidy-forms="{{ \App\Support\UiSettings::all()['tidy_forms'] ? '1' : '0' }}">
<div class="app-shell">
    <aside class="sidebar" id="main-sidebar">
        <button type="button" class="nav-link menu-toggle menu-expand" id="menu-expand" aria-controls="main-sidebar" aria-pressed="true" title="Show the full menu" aria-label="Show the full menu">{!! \App\Support\NavIcons::get('panel-left') !!}</button>
        @php($brand = \App\Support\UiSettings::brand())
        <a href="{{ route('dashboard') }}" class="brand" style="text-decoration:none">
            @if ($brand['logo'] && $brand['position'] === 'TOP_LEFT')
                <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" style="display:block;max-width:100%;height:{{ $brand['height'] }}px;object-fit:contain;object-position:left;margin-bottom:6px">
            @endif
            @unless ($brand['logo'])<span class="brand-name">{{ $brand['name'] }}</span><span class="brand-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($brand['name'], 0, 1)) }}</span>@endunless
            <small>OPERATIONS PLATFORM</small>
        </a>

        @php($menu = app(\App\Domains\Core\Contracts\MenuFilter::class))
        @php($menuCounts = app(\App\Support\MenuCounts::class)->forMenu($menu))
        <nav class="sidebar-nav" aria-label="Main navigation">
            <a href="{{ route('dashboard') }}" class="nav-link nav-top {{ request()->routeIs('dashboard') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('dashboard') !!}<span>Dashboard</span></a>
            @if ($menu->shows('calendar.index')) <a href="{{ route('calendar.index') }}" class="nav-link nav-top {{ request()->routeIs('calendar.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('calendar') !!}<span>Calendar</span></a> @endif

            @include('layouts._module-nav-groups', ['position' => 'top'])

            @if ($menu->showsAny(['patterns.index', 'casting-analyses.index', 'production-orders.index', 'planner.index', 'lots.queue', 'moulding-runs.index', 'pours.index', 'work-lists.index', 'moulding-teams.index', 'sand-systems.index', 'mould-readiness.index']))
            <div class="nav-group {{ request()->routeIs('patterns.*', 'pattern-ownership.*', 'casting-analyses.*', 'production-orders.*', 'planner.*', 'lots.queue', 'lots.bulk-stage', 'moulding-runs.*', 'pours.*', 'work-lists.*', 'moulding-teams.*', 'sand-systems.*', 'mould-readiness.*') ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('factory') !!}<span>Production</span>@if (($n = \App\Support\MenuCounts::sum($menuCounts, \App\Support\MenuCounts::PRODUCTION)) > 0)<span class="nav-count" title="Waiting for someone">{{ $n }}</span>@endif</span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    @if ($menu->shows('patterns.index')) <a href="{{ route('patterns.index') }}" class="nav-link {{ request()->routeIs('patterns.*', 'pattern-ownership.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('layers') !!}<span>Patterns</span></a> @endif
                    @if ($menu->shows('casting-analyses.index')) <a href="{{ route('casting-analyses.index') }}" class="nav-link {{ request()->routeIs('casting-analyses.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('chart-line') !!}<span>Casting analysis</span></a> @endif
                    @if ($menu->shows('production-orders.index')) <a href="{{ route('production-orders.index') }}" class="nav-link {{ request()->routeIs('production-orders.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('ticket') !!}<span>Production orders</span>@if (($menuCounts['production-orders.index'] ?? 0) > 0)<span class="nav-count" title="Waiting for someone">{{ $menuCounts['production-orders.index'] }}</span>@endif</a> @endif
                    @if ($menu->shows('planner.index')) <a href="{{ route('planner.index') }}" class="nav-link {{ request()->routeIs('planner.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('gantt') !!}<span>Planner</span></a> @endif
                    @if ($menu->shows('lots.queue')) <a href="{{ route('lots.queue') }}" class="nav-link {{ request()->routeIs('lots.queue') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('list') !!}<span>Production queue</span></a> @endif
                    @if ($menu->shows('moulding-runs.index')) <a href="{{ route('moulding-runs.index') }}" class="nav-link {{ request()->routeIs('moulding-runs.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('mould') !!}<span>Moulding runs</span>@if (($menuCounts['moulding-runs.index'] ?? 0) > 0)<span class="nav-count" title="Waiting for someone">{{ $menuCounts['moulding-runs.index'] }}</span>@endif</a> @endif
                    @if ($menu->shows('pours.index')) <a href="{{ route('pours.index') }}" class="nav-link {{ request()->routeIs('pours.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('pour') !!}<span>Pour register</span>@if (($menuCounts['pours.index'] ?? 0) > 0)<span class="nav-count" title="Waiting for someone">{{ $menuCounts['pours.index'] }}</span>@endif</a> @endif
                    @if ($menu->shows('work-lists.index')) <a href="{{ route('work-lists.index') }}" class="nav-link {{ request()->routeIs('work-lists.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('clipboard') !!}<span>Moulding work lists</span></a> @endif
                    @if ($menu->shows('moulding-teams.index')) <a href="{{ route('moulding-teams.index') }}" class="nav-link {{ request()->routeIs('moulding-teams.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('users') !!}<span>Teams &amp; stations</span></a> @endif
                    @if ($menu->shows('sand-systems.index')) <a href="{{ route('sand-systems.index') }}" class="nav-link {{ request()->routeIs('sand-systems.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('mountain') !!}<span>Sand systems</span></a> @endif
                    @if ($menu->shows('mould-readiness.index')) <a href="{{ route('mould-readiness.index') }}" class="nav-link {{ request()->routeIs('mould-readiness.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('clipboard-check') !!}<span>Mould readiness</span>@if (($menuCounts['mould-readiness.index'] ?? 0) > 0)<span class="nav-count" title="Waiting for someone">{{ $menuCounts['mould-readiness.index'] }}</span>@endif</a> @endif
                </div>
            </div>
            @endif

            @if ($menu->showsAny(['heats.index', 'furnaces.index']) || collect(app(\App\Domains\Core\ModuleRegistry::class)->navItems('melting'))->contains(fn ($i) => $menu->shows($i['route'])))
            <div class="nav-group {{ request()->routeIs('heats.*', 'furnaces.*', ...app(\App\Domains\Core\ModuleRegistry::class)->navPatterns('melting')) ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('flame') !!}<span>Melting</span>@if (($n = app(\App\Domains\Core\ModuleRegistry::class)->groupCount(app(\App\Domains\Core\ModuleRegistry::class)->navItems('melting'), $menu) ) > 0)<span class="nav-count" title="Waiting for someone">{{ $n }}</span>@endif</span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    @if ($menu->shows('heats.index')) <a href="{{ route('heats.index') }}" class="nav-link {{ request()->routeIs('heats.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('crucible') !!}<span>Melting heats</span></a> @endif
                    @if ($menu->shows('furnaces.index')) <a href="{{ route('furnaces.index') }}" class="nav-link {{ request()->routeIs('furnaces.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('zap') !!}<span>Furnaces</span></a> @endif
                    @foreach (app(\App\Domains\Core\ModuleRegistry::class)->navItems('melting') as $item)
                        @continue(! $menu->shows($item['route']))
                        <a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs(...$item['active']) ? 'active' : '' }}">{!! \App\Support\NavIcons::get($item['icon']) !!}<span>{{ $item['label'] }}</span>@if (($n = app(\App\Domains\Core\ModuleRegistry::class)->navCount($item)) > 0)<span class="nav-count" title="Waiting for someone">{{ $n }}</span>@endif</a>
                    @endforeach
                </div>
            </div>
            @endif

            @if ($menu->showsAny(['materials.index', 'charge-batches.index']) || collect(app(\App\Domains\Core\ModuleRegistry::class)->navItems('purchases'))->contains(fn ($i) => $menu->shows($i['route'])))
            <div class="nav-group {{ request()->routeIs('materials.*', 'charge-batches.*', ...app(\App\Domains\Core\ModuleRegistry::class)->navPatterns('purchases')) ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('shopping-cart') !!}<span>Purchases</span>@if (($n = app(\App\Domains\Core\ModuleRegistry::class)->groupCount(app(\App\Domains\Core\ModuleRegistry::class)->navItems('purchases'), $menu) ) > 0)<span class="nav-count" title="Waiting for someone">{{ $n }}</span>@endif</span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    @if ($menu->shows('materials.index')) <a href="{{ route('materials.index') }}" class="nav-link {{ request()->routeIs('materials.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('package') !!}<span>Materials</span></a> @endif
                    @foreach (app(\App\Domains\Core\ModuleRegistry::class)->navItems('purchases') as $item)
                        @continue(! $menu->shows($item['route']))
                        <a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs(...$item['active']) ? 'active' : '' }}">{!! \App\Support\NavIcons::get($item['icon']) !!}<span>{{ $item['label'] }}</span>@if (($n = app(\App\Domains\Core\ModuleRegistry::class)->navCount($item)) > 0)<span class="nav-count" title="Waiting for someone">{{ $n }}</span>@endif</a>
                    @endforeach
                    @if ($menu->shows('charge-batches.index')) <a href="{{ route('charge-batches.index') }}" class="nav-link {{ request()->routeIs('charge-batches.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('list-check') !!}<span>Charge recipes</span></a> @endif
                </div>
            </div>
            @endif

            @if ($menu->showsAny(['customers.index', 'products.index', 'heat-plan.index']) || collect(app(\App\Domains\Core\ModuleRegistry::class)->navItems('sales'))->contains(fn ($i) => $menu->shows($i['route'])))
            <div class="nav-group {{ request()->routeIs('customers.*', 'products.*', 'heat-plan.*', ...app(\App\Domains\Core\ModuleRegistry::class)->navPatterns('sales')) ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('briefcase') !!}<span>Sales &amp; Planning</span>@if (($n = app(\App\Domains\Core\ModuleRegistry::class)->groupCount(app(\App\Domains\Core\ModuleRegistry::class)->navItems('sales'), $menu) ) > 0)<span class="nav-count" title="Waiting for someone">{{ $n }}</span>@endif</span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    @if ($menu->shows('customers.index')) <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('building') !!}<span>Customers</span></a> @endif
                    @if ($menu->shows('products.index')) <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('box') !!}<span>Products</span></a> @endif
                    @foreach (app(\App\Domains\Core\ModuleRegistry::class)->navItems('sales') as $item)
                        @continue(! $menu->shows($item['route']))
                        <a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs(...$item['active']) ? 'active' : '' }}">{!! \App\Support\NavIcons::get($item['icon']) !!}<span>{{ $item['label'] }}</span>@if (($n = app(\App\Domains\Core\ModuleRegistry::class)->navCount($item)) > 0)<span class="nav-count" title="Waiting for someone">{{ $n }}</span>@endif</a>
                    @endforeach
                    @if ($menu->shows('heat-plan.index')) <a href="{{ route('heat-plan.index') }}" class="nav-link {{ request()->routeIs('heat-plan.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('calendar') !!}<span>Melting plan</span></a> @endif
                </div>
            </div>
            @endif

            @if ($menu->showsAny(['quick-qc.index', 'lots.index', 'inspections.index', 'ncrs.index', 'reports.yield', 'defects.index']) || collect(app(\App\Domains\Core\ModuleRegistry::class)->navItems('quality'))->contains(fn ($i) => $menu->shows($i['route'])))
            <div class="nav-group {{ (request()->routeIs('lots.*') && ! request()->routeIs('lots.queue', 'lots.bulk-stage')) || request()->routeIs('quick-qc.*', 'inspections.*', 'ncrs.*', 'reports.*', 'defects.*', ...app(\App\Domains\Core\ModuleRegistry::class)->navPatterns('quality')) ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('check-circle') !!}<span>Quality</span>@if (($n = app(\App\Domains\Core\ModuleRegistry::class)->groupCount(app(\App\Domains\Core\ModuleRegistry::class)->navItems('quality'), $menu) + \App\Support\MenuCounts::sum($menuCounts, \App\Support\MenuCounts::QUALITY)) > 0)<span class="nav-count" title="Waiting for someone">{{ $n }}</span>@endif</span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    @if ($menu->shows('quick-qc.index')) <a href="{{ route('quick-qc.index') }}" class="nav-link {{ request()->routeIs('quick-qc.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('check-verified') !!}<span>Quick QC</span></a> @endif
                    @if ($menu->shows('lots.index')) <a href="{{ route('lots.index') }}" class="nav-link {{ request()->routeIs('lots.index', 'lots.show', 'lots.certificate') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('tag') !!}<span>Casting lots</span></a> @endif
                    @if ($menu->shows('inspections.index')) <a href="{{ route('inspections.index') }}" class="nav-link {{ request()->routeIs('inspections.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('search-check') !!}<span>Inspections</span>@if (($menuCounts['inspections.index'] ?? 0) > 0)<span class="nav-count" title="Lots waiting for inspection and inspections still open">{{ $menuCounts['inspections.index'] }}</span>@endif</a> @endif
                    @if ($menu->shows('ncrs.index')) <a href="{{ route('ncrs.index') }}" class="nav-link {{ request()->routeIs('ncrs.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('alert') !!}<span>Nonconformance</span>@if (($menuCounts['ncrs.index'] ?? 0) > 0)<span class="nav-count" title="Waiting for someone">{{ $menuCounts['ncrs.index'] }}</span>@endif</a> @endif
                    @foreach (app(\App\Domains\Core\ModuleRegistry::class)->navItems('quality') as $item)
                        @continue(! $menu->shows($item['route']))
                        <a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs(...$item['active']) ? 'active' : '' }}">{!! \App\Support\NavIcons::get($item['icon']) !!}<span>{{ $item['label'] }}</span>@if (($n = app(\App\Domains\Core\ModuleRegistry::class)->navCount($item)) > 0)<span class="nav-count" title="Waiting for someone">{{ $n }}</span>@endif</a>
                    @endforeach
                    @if ($menu->shows('reports.yield')) <a href="{{ route('reports.yield') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('pie-chart') !!}<span>Yield &amp; scrap</span></a> @endif
                    @if ($menu->shows('defects.index')) <a href="{{ route('defects.index') }}" class="nav-link {{ request()->routeIs('defects.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('bug') !!}<span>Defect list</span></a> @endif
                </div>
            </div>
            @endif

            @if ($menu->showsAny(['iron-grades.index', 'thermal.index', 'sand-tests.index']))
            <div class="nav-group {{ request()->routeIs('iron-grades.*', 'thermal.*', 'sand-tests.*') ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('flask') !!}<span>Metallurgy</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    @if ($menu->shows('iron-grades.index')) <a href="{{ route('iron-grades.index') }}" class="nav-link {{ request()->routeIs('iron-grades.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('hexagon') !!}<span>Iron grades</span></a> @endif
                    @if ($menu->shows('thermal.index')) <a href="{{ route('thermal.index') }}" class="nav-link {{ request()->routeIs('thermal.index', 'thermal.show') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('thermometer') !!}<span>Thermal tests</span></a> @endif
                    @if ($menu->shows('sand-tests.index')) <a href="{{ route('sand-tests.index') }}" class="nav-link {{ request()->routeIs('sand-tests.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('microscope') !!}<span>Sand lab</span></a> @endif
                </div>
            </div>
            @endif

            @if ($menu->showsAny(['flasks.index']) || collect(app(\App\Domains\Core\ModuleRegistry::class)->navItems('equipment'))->contains(fn ($i) => $menu->shows($i['route'])))
            <div class="nav-group {{ request()->routeIs('flasks.*', 'flask-sizes.*', ...app(\App\Domains\Core\ModuleRegistry::class)->navPatterns('equipment')) ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('wrench') !!}<span>Equipment &amp; Tooling</span>@if (($n = app(\App\Domains\Core\ModuleRegistry::class)->groupCount(app(\App\Domains\Core\ModuleRegistry::class)->navItems('equipment'), $menu) ) > 0)<span class="nav-count" title="Waiting for someone">{{ $n }}</span>@endif</span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    @if ($menu->shows('flasks.index')) <a href="{{ route('flasks.index') }}" class="nav-link {{ request()->routeIs('flasks.*', 'flask-sizes.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('cube') !!}<span>Flasks</span></a> @endif
                    @foreach (app(\App\Domains\Core\ModuleRegistry::class)->navItems('equipment') as $item)
                        @continue(! $menu->shows($item['route']))
                        <a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs(...$item['active']) ? 'active' : '' }}">{!! \App\Support\NavIcons::get($item['icon']) !!}<span>{{ $item['label'] }}</span>@if (($n = app(\App\Domains\Core\ModuleRegistry::class)->navCount($item)) > 0)<span class="nav-count" title="Waiting for someone">{{ $n }}</span>@endif</a>
                    @endforeach
                </div>
            </div>
            @endif
            @include('layouts._module-nav-groups')
        </nav>
        <script>
        (function () {
            var KEY = 'erp.menuHidden.{{ auth()->id() ?? 0 }}';
            window.erpMenu = {
                key: KEY,
                read: function () { try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; } },
                write: function (list) { try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) {} },
                path: function (a) { return new URL(a.href, location.href).pathname; },
                apply: function () {
                    var hidden = this.read(), self = this;
                    document.querySelectorAll('.sidebar-nav .nav-link').forEach(function (a) { a.classList.toggle('nav-user-hidden', hidden.indexOf(self.path(a)) > -1); });
                    document.querySelectorAll('.sidebar-nav .nav-group').forEach(function (g) { g.classList.toggle('nav-user-hidden', !g.querySelector('.nav-link:not(.nav-user-hidden)')); });
                    var n = document.getElementById('hidden-items-count');
                    if (n) { n.textContent = hidden.length; n.hidden = !hidden.length; }
                }
            };
            window.erpMenu.apply();
        })();
        </script>

        <div class="sidebar-bottom">
            <a href="{{ route('help.new-customer') }}" class="nav-link {{ request()->routeIs('help.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('help-circle') !!}<span>Help: new customer</span></a>
            @if (\App\Support\SettingsNav::canOpen())
                <a href="{{ route('settings.edit') }}" class="nav-link {{ \App\Support\SettingsNav::isSettingsPage() ? 'active' : '' }}">{!! \App\Support\NavIcons::get('settings') !!}<span>Settings</span></a>
            @endif
            <button type="button" class="nav-link nav-hidden-open" id="hidden-items-open" aria-haspopup="dialog">{!! \App\Support\NavIcons::get('eye-off') !!}<span>Hidden items</span><span class="nav-count" id="hidden-items-count" hidden></span></button>
        </div>

        <dialog id="hidden-items" class="qd menu-edit" aria-labelledby="hidden-items-title">
            <div class="qd-head"><h2 id="hidden-items-title">Menu</h2><button type="button" class="qd-x" data-menu-close aria-label="Close">×</button></div>
            <div class="qd-body"><p class="muted small" style="margin:0 0 10px">Choose which entries show in your menu.</p><div id="hidden-items-list"></div></div>
            <div class="qd-foot"><button type="button" class="btn" id="hidden-items-reset">Show all</button><button type="button" class="btn btn-primary" data-menu-close>Done</button></div>
        </dialog>

        @if ($brand['logo'] && $brand['position'] === 'BOTTOM_LEFT')
            <div style="margin-top:auto;padding:12px 12px 0"><img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" style="display:block;max-width:100%;height:{{ $brand['height'] }}px;object-fit:contain;object-position:left"></div>
        @endif
        @auth
            @if (\App\Support\SettingsNav::canOpen())
                @php($simple = \App\Support\ProcessDepth::current() === 'BASIC')
                <form method="POST" action="{{ route('settings.process-depth') }}" class="simple-mode">@csrf @method('PUT')
                    <input type="hidden" name="preset" value="{{ $simple ? 'FULL' : 'BASIC' }}">
                    <span id="simple-mode-label">Simple mode</span>
                    <button type="submit" class="simple-switch" role="switch" aria-checked="{{ $simple ? 'true' : 'false' }}" aria-labelledby="simple-mode-label" title="{{ $simple ? 'On: Basic process depth. Switch to Full.' : 'Off. Switch to Basic process depth.' }}"><span></span></button>
                </form>
            @endif
            @php($me = auth()->user())
            @php($roleNames = $me->roles->pluck('name')->implode(', ') ?: 'No role')
            <div class="sidebar-profile">
                <a href="{{ route('account.password') }}" class="sp-link" title="Account and password">
                    @include('partials.avatar', ['user' => $me])
                    <span class="sp-text"><strong>{{ $me->name }}</strong><small>{{ $roleNames }}</small></span>
                </a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="sp-out" title="Sign out">{!! \App\Support\NavIcons::get('log-out') !!}<span class="sr-only">Sign out</span></button></form>
            </div>
        @else
            <div class="sidebar-footer" @if ($brand['logo'] && $brand['position'] === 'BOTTOM_LEFT') style="margin-top:0" @endif>
                @unless ($brand['logo']){{ $brand['name'] }}<br>@endunless
                Production & shop-floor control
            </div>
        @endauth

    </aside>
    <div class="sidebar-resizer" id="sidebar-resizer" role="separator" aria-orientation="vertical" aria-label="Drag to resize the menu (double-click to reset)" tabindex="0"></div>
    <div class="menu-backdrop" id="menu-backdrop" aria-hidden="true"></div>

    <div class="main-area">
        <header class="topbar">
            <button type="button" class="icon-btn menu-toggle" id="menu-toggle" aria-controls="main-sidebar" aria-pressed="false" title="Shrink the menu to icons" aria-label="Shrink the menu to icons"><span class="ico-panel">{!! \App\Support\NavIcons::get('panel-left', 18) !!}</span><span class="ico-hamburger">{!! \App\Support\NavIcons::get('menu-two-line', 22) !!}</span></button>
            @php($crumb = \App\Support\Breadcrumb::build($__env->hasSection('breadcrumb') ? $__env->yieldContent('breadcrumb') : ''))
            <div class="breadcrumb">
                @if ($crumb['parent'])<a class="crumb-back" id="crumb-back" href="{{ $crumb['parent'] }}" title="Back" aria-label="Back">←</a>@endif
                <strong>@if ($crumb['html'] !== ''){!! $crumb['html'] !!}@else{{ \App\Support\SettingsNav::isSettingsPage() ? 'Settings / '.\App\Support\SettingsNav::current() : 'Dashboard' }}@endif</strong>
            </div>
            <script>
            (function () {
                var back = document.getElementById('crumb-back');
                var trail = document.querySelector('.breadcrumb strong');
                var key = function (url) { return 'crumb:' + url.split('#')[0]; };
                try { if (trail) sessionStorage.setItem(key(location.href), trail.textContent.trim()); } catch (e) {}
                if (!back || !document.referrer) return;
                try {
                    var from = new URL(document.referrer);
                    if (from.origin !== location.origin || from.href === location.href || /\/(edit|create|new)$/.test(from.pathname)) return;
                    if (new URL(back.href, location.href).href === from.href) return;
                    back.href = from.href;
                    var title = sessionStorage.getItem(key(from.href));
                    if (!title || !trail) return;
                    back.title = back.ariaLabel = 'Back to ' + title;
                    var prev = document.createElement('a');
                    prev.className = 'crumb-prev';
                    prev.href = from.href;
                    prev.textContent = title.split(' / ').pop();
                    prev.title = 'Back to ' + title;
                    var sep = document.createElement('span');
                    sep.className = 'crumb-sep';
                    sep.textContent = ' ‹ ';
                    trail.parentNode.insertBefore(prev, trail);
                    trail.parentNode.insertBefore(sep, trail);
                } catch (e) {}
            })();
            </script>
            <div class="save-bar" id="save-bar" hidden role="status" aria-live="polite">
                <span class="save-bar-note">{!! \App\Support\NavIcons::get('alert', 15) !!}<span>Unsaved changes</span></span>
                <button type="button" class="btn" data-save-bar="discard">Discard</button>
                <button type="button" class="btn btn-primary" data-save-bar="save">Save</button>
            </div>
            <form method="GET" action="{{ route('scan') }}" class="scan-box" id="scan-box" data-suggest="{{ route('scan.suggest') }}" role="search"><button type="button" class="icon-btn scan-open" id="scan-open" title="Search" aria-label="Search">{!! \App\Support\NavIcons::get('search', 20) !!}</button><span class="scan-type" id="scan-type" hidden></span><input type="hidden" name="type" id="scan-type-input"><input id="scan-input" name="code" placeholder="Search or scan a code…" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="scan-results" aria-autocomplete="list"><kbd id="scan-hint" class="scan-hint" title="Press to jump to the search box">Ctrl K</kbd><div class="scan-results" id="scan-results" role="listbox" hidden></div></form>
            <style>
                .scan-box{position:relative;display:inline-block}
                .scan-box input:not(#_){width:230px;min-height:30px;padding:3px 52px 3px 8px}
                .scan-hint{position:absolute;right:6px;top:50%;transform:translateY(-50%);font:600 10px/1 system-ui,sans-serif;color:#6b7280;background:#fff;border:1px solid #e5e7eb;border-radius:4px;padding:3px 5px;pointer-events:none}
                .scan-box input:focus + .scan-hint{opacity:0}
            </style>
            <script>
                (function () {
                    var i = document.getElementById('scan-input'), h = document.getElementById('scan-hint');
                    if (!i) return;
                    var box = document.getElementById('scan-box'), opener = document.getElementById('scan-open');
                    var chip = document.getElementById('scan-type'), typeInput = document.getElementById('scan-type-input'), list = document.getElementById('scan-results');
                    var type = null, offer = null, rows = [], active = -1, timer = null, request = null;

                    function open(show) { list.hidden = !show; i.setAttribute('aria-expanded', String(show)); }
                    function setType(key, label) {
                        type = key; typeInput.value = key || ''; chip.hidden = !key; chip.textContent = label || '';
                        i.style.paddingLeft = key ? (chip.offsetWidth + 12) + 'px' : '';
                        i.placeholder = key ? 'Search ' + label.toLowerCase() + 's…' : 'Search or scan a code…';
                    }
                    function highlight(n) {
                        active = n;
                        rows.forEach(function (r, k) { r.el.classList.toggle('is-active', k === n); r.el.setAttribute('aria-selected', String(k === n)); });
                    }
                    function row(html, onPick) {
                        var el = document.createElement('a'); el.className = 'scan-row'; el.setAttribute('role', 'option'); el.href = onPick.url || '#';
                        el.append.apply(el, html);
                        el.addEventListener('mousedown', function (e) { e.preventDefault(); });
                        el.addEventListener('click', function (e) { if (!onPick.url) { e.preventDefault(); onPick.run(); } });
                        rows.push({ el: el, pick: onPick }); list.appendChild(el);
                    }
                    function span(cls, text) { var s = document.createElement('span'); s.className = cls; s.textContent = text; return s; }
                    function menuMatches(q) {
                        if (type) return [];
                        var words = q.toLowerCase().split(/\s+/), seen = {}, found = [];
                        document.querySelectorAll('#main-sidebar a.nav-link[href]').forEach(function (a) {
                            var badge = a.querySelector('.nav-count'), clone = a.cloneNode(true);
                            clone.querySelectorAll('.nav-count').forEach(function (n) { n.remove(); });
                            var label = (clone.textContent || '').trim(), count = badge ? (badge.textContent || '').trim() : '';
                            var hay = label.toLowerCase();
                            if (!label || seen[a.href] || a.getAttribute('href') === '#' || !words.every(function (w) { return hay.indexOf(w) !== -1; })) return;
                            seen[a.href] = true; found.push({ code: label, name: '', count: count, label: 'Menu', url: a.href });
                        });
                        return found.slice(0, 6);
                    }
                    function render(data) {
                        var menu = menuMatches(i.value.trim());
                        data = { offer: data.offer, results: menu.concat(data.results || []) };
                        list.innerHTML = ''; rows = []; active = -1; offer = data.offer;
                        if (offer) {
                            row([span('scan-row-main', 'Search ' + offer.label.toLowerCase() + 's only'), span('scan-row-kind', 'Tab')], { run: pickOffer });
                        }
                        data.results.forEach(function (r) {
                            var shown = span('scan-row-name', r.name || '');
                            if (r.count) { shown.appendChild(span('nav-count', r.count)); shown.title = 'Waiting for someone'; }
                            row([span('scan-row-code', r.code), shown, span('scan-row-kind', r.label)], { url: r.url });
                        });
                        if (!data.results.length && i.value.trim()) {
                            var none = document.createElement('div'); none.className = 'scan-empty'; none.textContent = 'No direct matches. Press Enter to search.'; list.appendChild(none);
                        }
                        open(list.childNodes.length > 0);
                    }
                    function pickOffer() { setType(offer.type, offer.label); i.value = ''; offer = null; fetchNow(); i.focus(); }
                    function fetchNow() {
                        var q = i.value.trim();
                        if (!q) { list.innerHTML = ''; rows = []; open(false); return; }
                        if (request) request.abort();
                        request = new AbortController();
                        fetch(box.dataset.suggest + '?' + new URLSearchParams({ q: q, type: type || '' }), { headers: { Accept: 'application/json' }, signal: request.signal })
                            .then(function (r) { return r.ok ? r.json() : { offer: null, results: [] }; }).then(function (d) { render(d); }).catch(function (e) { if (e.name !== 'AbortError') render({ offer: null, results: [] }); });
                    }
                    i.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(fetchNow, 150); });
                    i.addEventListener('focus', function () { if (rows.length) open(true); });
                    i.addEventListener('keydown', function (e) {
                        if (e.key === 'Tab' && !e.shiftKey && offer && !type) { e.preventDefault(); pickOffer(); }
                        else if (e.key === 'Backspace' && type && i.value === '') { setType(null); fetchNow(); }
                        else if (e.key === 'ArrowDown' && rows.length) { e.preventDefault(); open(true); highlight((active + 1) % rows.length); }
                        else if (e.key === 'ArrowUp' && rows.length) { e.preventDefault(); highlight(active <= 0 ? rows.length - 1 : active - 1); }
                        else if (e.key === 'Escape') { open(false); }
                        else if (e.key === 'Enter' && active >= 0 && !list.hidden) {
                            e.preventDefault(); var p = rows[active].pick; if (p.url) { location.href = p.url; } else { p.run(); }
                        }
                    });
                    i.addEventListener('blur', function () { open(false); });
                    opener.addEventListener('click', function () { box.classList.add('is-open'); i.focus(); });
                    i.addEventListener('blur', function () { box.classList.remove('is-open'); });
                    var mac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
                    h.textContent = mac ? '⌘ K' : 'Ctrl K';
                    document.addEventListener('keydown', function (e) {
                        var t = e.target, typing = t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable);
                        var combo = (e.key || '').toLowerCase() === 'k' && (mac ? e.metaKey : e.ctrlKey);
                        if (combo || (e.key === '/' && !typing && !e.metaKey && !e.ctrlKey && !e.altKey)) {
                            e.preventDefault(); i.focus(); i.select();
                        }
                    });
                })();
            </script>
            @auth
                <livewire:notification-center />
            @endauth
            <div class="topbar-date">@include('layouts._heat-lights'){{ now()->format('D, d M Y') }}
            </div>
        </header>

        <main class="page-content">
            @if (session('success'))
                <div class="alert alert-success" role="status">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('warning'))
                <div class="alert alert-warning" role="status">{{ session('warning') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <strong>Please correct the following:</strong>
                    <ul style="margin:7px 0 0;padding-left:20px">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (\App\Support\SettingsNav::isSettingsPage())
                <div class="settings-shell">
                    <nav class="settings-nav" aria-label="Settings">
                        <div class="settings-nav-title">{!! \App\Support\NavIcons::get('settings', 15) !!}<span>Settings</span></div>
                        @php($settingsCurrent = \App\Support\SettingsNav::current())
                        @foreach (\App\Support\SettingsNav::sections() as $section => $links)
                            <div class="settings-nav-section">{{ $section }}</div>
                            @foreach ($links as $label => [$routeName])
                                <a href="{{ route($routeName) }}" @class(['active' => $settingsCurrent === $label]) @if ($settingsCurrent === $label) aria-current="page" @endif><span>{{ $label }}</span>@if ($settingsCurrent === $label)<span aria-hidden="true">›</span>@endif</a>
                            @endforeach
                        @endforeach
                    </nav>
                    <div class="settings-main">@yield('content')</div>
                </div>
            @else
                @if (request()->routeIs('dashboard') && ! \App\Support\ProcessSetup::answered() && (! auth()->user() || auth()->user()->hasPermission('settings.manage')))
                    <div class="alert alert-success" style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">
                        <span>Tell the system how your foundry works, and it sets the steps and checks to match.</span>
                        <a class="btn btn-small btn-primary" href="{{ route('settings.process-setup.edit') }}">How you work</a>
                    </div>
                @endif
                @yield('content')
            @endif
        </main>
    </div>
</div>

<script>
// Number boxes take any value within their limits; places beyond the site's decimal setting are rounded off when a box is left.
(() => {
    const places = {{ \App\Support\Formats::decimals() }};
    window.decimalPlaces = places;
    document.addEventListener('change', (e) => {
        const box = e.target;
        if (!(box instanceof HTMLInputElement) || box.type !== 'number' || box.value === '' || (box.step && box.step !== 'any')) return;
        const typed = Number(box.value);
        const kept = Number(typed.toFixed(places));
        if (Number.isFinite(typed) && kept !== typed) {
            box.value = String(kept);
            box.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }, true);
})();
</script>

<script data-erp-form-behavior>
(() => {
    'use strict';

    function initialiseForm(form) {
        if (form.dataset.erpInitialised === 'true') return;
        form.dataset.erpInitialised = 'true';

        // Default only explicitly marked transaction dates to today's local date.
        form.querySelectorAll('input[type="date"][data-default-today]').forEach(input => {
            if (!input.value) {
                const now = new Date();
                const localDate = [
                    now.getFullYear(),
                    String(now.getMonth() + 1).padStart(2, '0'),
                    String(now.getDate()).padStart(2, '0')
                ].join('-');

                input.value = localDate;
            }
        });

        // Restore old() values after validation; autofocus the first invalid field.
        const invalid = form.querySelector(
            'input:invalid:not([type="hidden"]):not([disabled]), ' +
            'select:invalid:not([disabled]), textarea:invalid:not([disabled])'
        );

        const explicit = form.querySelector(
            '[data-autofocus]:not([disabled]), [autofocus]:not([disabled])'
        );

        const firstUsable = form.querySelector(
            'input:not([type="hidden"]):not([disabled]):not([readonly]), ' +
            'select:not([disabled]), textarea:not([disabled])'
        );

        const target = invalid || explicit || firstUsable;

        if (target) {
            // Defer until the page is rendered; don't steal focus from a dialog.
            requestAnimationFrame(() => {
                if (!document.querySelector('dialog[open], [role="dialog"]')) {
                    target.focus({ preventScroll: true });
                    if (target.select && target.tagName === 'INPUT' &&
                        ['text', 'search', 'tel', 'url'].includes(target.type)) {
                        target.select();
                    }
                }
            });
        }
    }

    function initialise() {
        document.querySelectorAll('form').forEach(initialiseForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
})();
</script>


<script>
/* Hidden items: a dialog listing every menu entry with a switch, kept per person in this browser. */
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('hidden-items'), open = document.getElementById('hidden-items-open'), list = document.getElementById('hidden-items-list');
    if (!dialog || !open || !window.erpMenu) return;
    const menu = window.erpMenu;
    function build() {
        const hidden = menu.read();
        list.innerHTML = '';
        document.querySelectorAll('.sidebar-nav .nav-group').forEach(group => {
            const links = [...group.querySelectorAll('.nav-link')];
            if (!links.length) return;
            const title = document.createElement('div');
            title.className = 'menu-edit-group';
            title.textContent = (group.querySelector('.nav-lab > span:not(.nav-ico):not(.nav-count)') || group.querySelector('.nav-lab')).textContent.trim();
            list.appendChild(title);
            links.forEach(link => {
                const path = menu.path(link), row = document.createElement('label'), name = document.createElement('span'), box = document.createElement('input');
                row.className = 'menu-edit-row';
                name.textContent = (link.querySelector('span:not(.nav-count)') || link).textContent.trim();
                box.type = 'checkbox'; box.className = 'simple-check'; box.checked = hidden.indexOf(path) === -1;
                box.setAttribute('role', 'switch');
                box.addEventListener('change', () => {
                    const now = menu.read().filter(p => p !== path);
                    if (!box.checked) now.push(path);
                    menu.write(now); menu.apply();
                });
                row.append(name, box);
                list.appendChild(row);
            });
        });
    }
    open.addEventListener('click', () => { build(); dialog.showModal(); });
    dialog.querySelectorAll('[data-menu-close]').forEach(b => b.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
    document.getElementById('hidden-items-reset').addEventListener('click', () => { menu.write([]); menu.apply(); build(); });
});

/* Foundry ERP sidebar submenu behavior */
document.addEventListener('DOMContentLoaded', () => {
    const groups = [...document.querySelectorAll('.sidebar-nav .nav-group')];

    function setOpen(group, open) {
        const button = group.querySelector('.nav-group-toggle');
        const submenu = group.querySelector('.nav-submenu');
        if (!button || !submenu) return;

        group.classList.toggle('is-open', open);
        button.setAttribute('aria-expanded', String(open));
        submenu.hidden = !open;
    }

    const labelsOnly = document.body.classList.contains('menu-labels');
    groups.forEach(group => {
        const button = group.querySelector('.nav-group-toggle');
        if (!button) return;
        // "Every link under section labels": headings are labels, not buttons.
        if (labelsOnly) { setOpen(group, true); button.tabIndex = -1; button.setAttribute('aria-hidden', 'true'); return; }

        setOpen(group, group.classList.contains('is-active'));

        button.addEventListener('click', () => {
            setOpen(group, !group.classList.contains('is-open'));
        });

        // group.addEventListener('mouseenter', event => {
        //     if (event.pointerType === 'touch') return;
        //     setOpen(group, true);
        // });

        // group.addEventListener('mouseleave', event => {
        //     if (event.pointerType === 'touch') return;
        //     if (group.classList.contains('is-active')) {
        //         setOpen(group, true);
        //     } else {
        //         setOpen(group, false);
        //     }
        // });

        // group.addEventListener('focusout', event => {
        //     if (!group.contains(event.relatedTarget) &&
        //         !group.classList.contains('is-active')) {
        //         setOpen(group, false);
        //     }
        // });
    });
});
</script>

<script>
(function () {
    var h = document.getElementById('sidebar-resizer'); if (!h) return;
    var root = document.documentElement, MIN = 200, MAX = 420;
    function cur() { return Math.round(document.querySelector('.sidebar').getBoundingClientRect().width); }
    function set(w) { w = Math.max(MIN, Math.min(MAX, Math.round(w))); root.style.setProperty('--side-w', w + 'px'); return w; }
    function save(w) { try { localStorage.setItem('erp.menuWidth', String(w)); } catch (e) {} }
    h.addEventListener('pointerdown', function (e) {
        e.preventDefault(); h.setPointerCapture(e.pointerId); h.classList.add('dragging'); document.body.classList.add('resizing-menu');
    });
    h.addEventListener('pointermove', function (e) { if (h.classList.contains('dragging')) set(e.clientX); });
    function end(e) {
        if (!h.classList.contains('dragging')) return;
        h.classList.remove('dragging'); document.body.classList.remove('resizing-menu');
        try { h.releasePointerCapture(e.pointerId); } catch (x) {}
        save(cur());
    }
    h.addEventListener('pointerup', end); h.addEventListener('pointercancel', end);
    h.addEventListener('dblclick', function () { root.style.removeProperty('--side-w'); try { localStorage.removeItem('erp.menuWidth'); } catch (e) {} });
    h.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
        e.preventDefault(); save(set(cur() + (e.key === 'ArrowRight' ? 16 : -16)));
    });
})();
</script>

<script>
/* Menu button. Desktop: shrinks the menu to icons and back (kept in this browser only). Tablet: the menu is always an icon
   rail and the button opens the full menu over the page. Mobile: the menu is off-canvas and the button slides it in. */
(function () {
    var btn = document.getElementById('menu-toggle'), expand = document.getElementById('menu-expand'), backdrop = document.getElementById('menu-backdrop'), root = document.documentElement; if (!btn) return;
    var mobile = matchMedia('(max-width: 768px)'), tablet = matchMedia('(min-width: 769px) and (max-width: 1024px)');
    function mode() { return mobile.matches ? 'mobile' : (tablet.matches ? 'tablet' : 'desktop'); }
    function names(slim) {
        document.querySelectorAll('.sidebar .nav-link:not(.menu-expand), .sidebar .nav-group-toggle').forEach(function (el) {
            var text = (el.querySelector('span:not(.nav-chevron)') || el).textContent.trim();
            if (slim) { el.setAttribute('title', text); } else if (el.getAttribute('title') === text) { el.removeAttribute('title'); }
        });
    }
    function label(text, expanded) {
        [btn, expand].forEach(function (b) {
            if (!b) return;
            b.title = text; b.setAttribute('aria-label', text);
            if (expanded === null) { b.removeAttribute('aria-expanded'); } else { b.setAttribute('aria-expanded', String(expanded)); b.removeAttribute('aria-pressed'); }
        });
    }
    function sync() {
        var m = mode(), open = root.classList.contains('menu-open') || root.classList.contains('menu-peek');
        if (m === 'desktop') {
            root.classList.remove('menu-open', 'menu-peek', 'menu-hover');
            var slim = false; try { slim = localStorage.getItem('erp.menuSlim') === '1'; } catch (e) {}
            root.classList.toggle('menu-slim', slim);
            btn.setAttribute('aria-pressed', String(slim)); btn.removeAttribute('aria-expanded');
            label(slim ? 'Show the full menu' : 'Shrink the menu to icons', null);
            names(slim);
        } else if (m === 'tablet') {
            root.classList.remove('menu-open');
            root.classList.toggle('menu-slim', !root.classList.contains('menu-peek'));
            label(open ? 'Close the menu' : 'Show the full menu', open);
            names(!root.classList.contains('menu-peek'));
        } else {
            root.classList.remove('menu-peek', 'menu-slim');
            label(open ? 'Close the menu' : 'Open the menu', open);
            names(false);
        }
    }
    function close() {
        if (!root.classList.contains('menu-open') && !root.classList.contains('menu-peek')) return;
        root.classList.remove('menu-open', 'menu-peek'); sync(); btn.focus();
    }
    function toggle() {
        var m = mode();
        if (m === 'desktop') {
            if (root.classList.contains('menu-hover')) { root.classList.remove('menu-hover'); root.classList.add('menu-slim'); }
            var slim = !root.classList.contains('menu-slim');
            try { localStorage.setItem('erp.menuSlim', slim ? '1' : '0'); } catch (e) {}
        } else {
            root.classList.toggle(m === 'mobile' ? 'menu-open' : 'menu-peek');
            if (m === 'mobile' && root.classList.contains('menu-open')) { var first = document.querySelector('#main-sidebar a, #main-sidebar button'); if (first) first.focus(); }
        }
        sync();
    }
    btn.addEventListener('click', toggle); if (expand) expand.addEventListener('click', toggle);
    if (backdrop) backdrop.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    document.getElementById('main-sidebar').addEventListener('click', function (e) { if (e.target.closest('a[href]') && mode() !== 'desktop') { root.classList.remove('menu-open', 'menu-peek'); sync(); } });
    mobile.addEventListener('change', sync); tablet.addEventListener('change', sync);
    sync();

    /* Slim menu on a wide screen: pointing at it widens it over the page to show the names, and it shrinks again when the pointer leaves. */
    var side = document.getElementById('main-sidebar'), leaving = null;
    side.addEventListener('mouseenter', function () {
        clearTimeout(leaving);
        if (mode() === 'desktop' && root.classList.contains('menu-slim')) {
            root.classList.remove('menu-slim');
            root.style.setProperty('--side-open-w', getComputedStyle(root).getPropertyValue('--side-w').trim());
            root.classList.add('menu-hover'); names(false);
        }
    });
    side.addEventListener('mouseleave', function () {
        leaving = setTimeout(function () {
            if (!root.classList.contains('menu-hover')) return;
            root.classList.remove('menu-hover'); root.classList.add('menu-slim'); names(true);
        }, 150);
    });
})();
</script>

<script>
/* "Unsaved changes" bar: once an edit form on the page is changed, the top bar offers Discard and Save for it.
   Edit forms are POST forms with at least three fields, outside dialogs; filters, row tools and quick-add dialogs don't count.
   A form can opt out with data-no-save-bar. */
(function () {
    var bar = document.getElementById('save-bar'); if (!bar) return;
    var active = null;
    function fields(form) { return form.querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea'); }
    function isEditForm(form) {
        return form && (form.getAttribute('method') || '').toLowerCase() === 'post' && !form.hasAttribute('data-no-save-bar')
            && !form.closest('dialog, [role=dialog], .sidebar, .topbar') && fields(form).length >= 3;
    }
    function show(form) { active = form; bar.hidden = false; }
    function hide() { active = null; bar.hidden = true; }
    function onEdit(e) {
        var form = e.target.form || (e.target.closest && e.target.closest('form'));
        if (!e.target.name || (e.target.closest && e.target.closest('[data-no-save-bar]'))) return;   // search boxes inside pickers have no name
        if (isEditForm(form)) show(form);
    }
    document.addEventListener('input', onEdit, true);
    document.addEventListener('change', onEdit, true);
    document.addEventListener('submit', function (e) { if (e.target === active) hide(); }, true);
    bar.addEventListener('click', function (e) {
        var b = e.target.closest('[data-save-bar]'); if (!b || !active) return;
        if (b.dataset.saveBar === 'discard') {
            active.reset();
            active.querySelectorAll('select').forEach(function (s) { s.dispatchEvent(new Event('change', { bubbles: true })); });
            hide();
        } else {
            var submit = active.querySelector('button[type=submit].btn-primary, button:not([type]).btn-primary, input[type=submit].btn-primary')
                || active.querySelector('button[type=submit], button:not([type]), input[type=submit]');
            var form = active;
            if (form.requestSubmit) { submit ? form.requestSubmit(submit) : form.requestSubmit(); } else { form.submit(); }
        }
    });
    document.addEventListener('keydown', function (e) {
        if (active && (e.key || '').toLowerCase() === 's' && (e.metaKey || e.ctrlKey) && !document.querySelector('dialog[open]')) {
            if (e.defaultPrevented) return;
            e.preventDefault(); bar.querySelector('[data-save-bar=save]').click();
        }
    });
})();
</script>

@include("partials.tabs")
@include("partials.table-ui")
@include("partials.form-ui")
@include("partials.lightbox")
@stack('dialogs')
@include("partials.rowlink")
<script>
// Refuse an oversized file on the spot, with the real limit, instead of after a long upload that the server would drop.
document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || input.type !== 'file' || !input.dataset.maxKb || !input.files) return;
    var max = +input.dataset.maxKb * 1024, big = [];
    Array.prototype.forEach.call(input.files, function (f) { if (f.size > max) big.push(f.name + ' (' + (f.size / 1048576).toFixed(1) + ' MB)'); });
    if (!big.length) return;
    e.stopImmediatePropagation();
    input.value = '';
    alert('Too large: ' + big.join(', ') + '.\nEach file can be at most ' + (max / 1048576).toFixed(max % 1048576 ? 1 : 0) + ' MB.');
}, true);
</script>
</body>
</html>