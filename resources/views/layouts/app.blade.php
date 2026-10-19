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
<body class="menu-{{ strtolower(\App\Support\UiSettings::all()['menu']) }}" data-row-click="{{ \App\Models\AppSetting::getValue('ui.row_click', '1') === '1' ? '1' : '0' }}" data-toolbar="{{ \App\Support\UiSettings::all()['toolbar'] ? '1' : '0' }}" data-tidy-forms="{{ \App\Support\UiSettings::all()['tidy_forms'] ? '1' : '0' }}">
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

        <nav class="sidebar-nav" aria-label="Main navigation">
            <a href="{{ route('dashboard') }}" class="nav-link nav-top {{ request()->routeIs('dashboard') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('dashboard') !!}<span>Dashboard</span></a>

            <div class="nav-group {{ request()->routeIs('products.*', 'patterns.*', 'pattern-ownership.*', 'casting-analyses.*', 'production-orders.*', 'moulding-runs.*', 'pours.*', 'work-lists.*', 'moulding-teams.*', 'sand-systems.*', 'mould-readiness.*') ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('factory') !!}<span>Production</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('box') !!}<span>Products</span></a>
                    <a href="{{ route('patterns.index') }}" class="nav-link {{ request()->routeIs('patterns.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('layers') !!}<span>Patterns</span></a>
                    <a href="{{ route('pattern-ownership.index') }}" class="nav-link {{ request()->routeIs('pattern-ownership.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('users') !!}<span>Pattern ownership</span></a>
                    <a href="{{ route('casting-analyses.index') }}" class="nav-link {{ request()->routeIs('casting-analyses.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('chart-line') !!}<span>Casting analysis</span></a>
                    <a href="{{ route('production-orders.index') }}" class="nav-link {{ request()->routeIs('production-orders.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('factory') !!}<span>Production orders</span></a>
                    <a href="{{ route('moulding-runs.index') }}" class="nav-link {{ request()->routeIs('moulding-runs.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('grid') !!}<span>Moulding runs</span></a>
                    <a href="{{ route('pours.index') }}" class="nav-link {{ request()->routeIs('pours.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('flame') !!}<span>Pour register</span></a>
                    <a href="{{ route('work-lists.index') }}" class="nav-link {{ request()->routeIs('work-lists.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('clipboard') !!}<span>Moulding work lists</span></a>
                    <a href="{{ route('moulding-teams.index') }}" class="nav-link {{ request()->routeIs('moulding-teams.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('users') !!}<span>Teams &amp; stations</span></a>
                    <a href="{{ route('sand-systems.index') }}" class="nav-link {{ request()->routeIs('sand-systems.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('mountain') !!}<span>Sand systems</span></a>
                    <a href="{{ route('mould-readiness.index') }}" class="nav-link {{ request()->routeIs('mould-readiness.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('check-circle') !!}<span>Mould readiness</span></a>
                </div>
            </div>

            <div class="nav-group {{ request()->routeIs('heats.*', 'furnaces.*', 'materials.*', 'charge-batches.*') ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('flame') !!}<span>Melting &amp; Materials</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    <a href="{{ route('heats.index') }}" class="nav-link {{ request()->routeIs('heats.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('flask') !!}<span>Melting heats</span></a>
                    <a href="{{ route('furnaces.index') }}" class="nav-link {{ request()->routeIs('furnaces.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('zap') !!}<span>Furnaces</span></a>
                    <a href="{{ route('materials.index') }}" class="nav-link {{ request()->routeIs('materials.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('package') !!}<span>Materials</span></a>
                    <a href="{{ route('charge-batches.index') }}" class="nav-link {{ request()->routeIs('charge-batches.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('list-check') !!}<span>Charge recipes</span></a>
                </div>
            </div>

            <div class="nav-group {{ request()->routeIs('customers.*', 'enquiries.*', 'quotations.*', 'sales-orders.*', 'heat-plan.*') ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('briefcase') !!}<span>Sales &amp; Planning</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('building') !!}<span>Customers</span></a>
                    <a href="{{ route('enquiries.index') }}" class="nav-link {{ request()->routeIs('enquiries.*', 'quotations.*') && ! request()->routeIs('enquiries.wizard') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('file-text') !!}<span>Enquiries &amp; quotes</span></a>
                    <a href="{{ route('sales-orders.index') }}" class="nav-link {{ request()->routeIs('sales-orders.index', 'sales-orders.show', 'sales-orders.trace') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('receipt') !!}<span>Sales orders</span></a>
                    <a href="{{ route('heat-plan.index') }}" class="nav-link {{ request()->routeIs('heat-plan.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('calendar') !!}<span>Melting plan</span></a>
                </div>
            </div>

            <div class="nav-group {{ request()->routeIs('lots.*', 'inspections.*', 'ncrs.*', 'capa.*', 'packing.*', 'dispatches.*', 'finished-goods.*', 'reports.*', 'defects.*') ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('check-circle') !!}<span>Quality &amp; Dispatch</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    <a href="{{ route('lots.queue') }}" class="nav-link {{ request()->routeIs('lots.queue') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('list') !!}<span>Production queue</span></a>
                    <a href="{{ route('lots.index') }}" class="nav-link {{ request()->routeIs('lots.index', 'lots.show', 'lots.certificate') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('tag') !!}<span>Casting lots</span></a>
                    <a href="{{ route('finished-goods.index') }}" class="nav-link {{ request()->routeIs('finished-goods.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('archive') !!}<span>Finished goods</span></a>
                    <a href="{{ route('inspections.index') }}" class="nav-link {{ request()->routeIs('inspections.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('search-check') !!}<span>Inspections</span></a>
                    <a href="{{ route('ncrs.index') }}" class="nav-link {{ request()->routeIs('ncrs.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('alert') !!}<span>Nonconformance</span></a>
                    <a href="{{ route('capa.index') }}" class="nav-link {{ request()->routeIs('capa.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('check-circle') !!}<span>Corrective actions</span></a>
                    <a href="{{ route('packing.index') }}" class="nav-link {{ request()->routeIs('packing.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('package') !!}<span>Packing</span></a>
                    <a href="{{ route('dispatches.index') }}" class="nav-link {{ request()->routeIs('dispatches.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('truck') !!}<span>Dispatch</span></a>
                    <a href="{{ route('reports.yield') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('bars') !!}<span>Yield &amp; scrap</span></a>
                    <a href="{{ route('defects.index') }}" class="nav-link {{ request()->routeIs('defects.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('bug') !!}<span>Defect list</span></a>
                </div>
            </div>

            <div class="nav-group {{ request()->routeIs('iron-grades.*', 'thermal.*', 'sand-tests.*') ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('flask') !!}<span>Metallurgy</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    <a href="{{ route('iron-grades.index') }}" class="nav-link {{ request()->routeIs('iron-grades.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('hexagon') !!}<span>Iron grades</span></a>
                    <a href="{{ route('thermal.index') }}" class="nav-link {{ request()->routeIs('thermal.index', 'thermal.show') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('thermometer') !!}<span>Thermal tests</span></a>
                    <a href="{{ route('sand-tests.index') }}" class="nav-link {{ request()->routeIs('sand-tests.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('mountain') !!}<span>Sand lab</span></a>
                </div>
            </div>

            <div class="nav-group {{ request()->routeIs('tooling-maintenance.*', 'tooling-life.*', 'flasks.*', 'flask-sizes.*', ...app(\App\Domains\Core\ModuleRegistry::class)->navPatterns('equipment')) ? 'is-active' : '' }}">
                <button type="button" class="nav-group-toggle" aria-expanded="false">
                    <span class="nav-lab">{!! \App\Support\NavIcons::get('wrench') !!}<span>Equipment &amp; Tooling</span></span><span class="nav-chevron">›</span>
                </button>
                <div class="nav-submenu" hidden>
                    <a href="{{ route('flasks.index') }}" class="nav-link {{ request()->routeIs('flasks.*', 'flask-sizes.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('grid') !!}<span>Flasks</span></a>
                    <a href="{{ route('tooling-maintenance.dashboard') }}" class="nav-link {{ request()->routeIs('tooling-maintenance.dashboard') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('dashboard') !!}<span>Maintenance dashboard</span></a>
                    <a href="{{ route('tooling-maintenance.index') }}" class="nav-link {{ request()->routeIs('tooling-maintenance.index', 'tooling-maintenance.show', 'tooling-maintenance.edit') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('wrench') !!}<span>Maintenance records</span></a>
                    <a href="{{ route('tooling-life.dashboard') }}" class="nav-link {{ request()->routeIs('tooling-life.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('gauge') !!}<span>Tooling life</span></a>
                    @foreach (app(\App\Domains\Core\ModuleRegistry::class)->navItems('equipment') as $item)
                        <a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs(...$item['active']) ? 'active' : '' }}">{!! \App\Support\NavIcons::get($item['icon']) !!}<span>{{ $item['label'] }}</span></a>
                    @endforeach
                </div>
            </div>
            <div class="nav-group {{ request()->routeIs('hr.*', 'directory.*', 'leave.*') ? 'is-active' : '' }}">
    <button type="button" class="nav-group-toggle" aria-expanded="false">
        <span class="nav-lab">{!! \App\Support\NavIcons::get('users') !!}<span>People (HR)</span></span><span class="nav-chevron">›</span>
    </button>
    <div class="nav-submenu" hidden>
        <a href="{{ route('hr.dashboard') }}" class="nav-link {{ request()->routeIs('hr.dashboard') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('users') !!}<span>People overview</span></a>
        <a href="{{ route('hr.employees.index') }}" class="nav-link {{ request()->routeIs('hr.employees.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('id-card') !!}<span>Employees</span></a>
        <a href="{{ route('hr.attendance') }}" class="nav-link {{ request()->routeIs('hr.attendance*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('clock') !!}<span>Attendance &amp; overtime</span></a>
        <a href="{{ route('hr.roster') }}" class="nav-link {{ request()->routeIs('hr.roster*', 'hr.shifts.*', 'hr.holidays.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('calendar') !!}<span>Roster, shifts &amp; holidays</span></a>
        <a href="{{ route('hr.leave') }}" class="nav-link {{ request()->routeIs('hr.leave*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('calendar-minus') !!}<span>Leave (HR)</span></a>
        @if (\App\Support\HrAccess::isPayroll())<a href="{{ route('hr.payroll.index') }}" class="nav-link {{ request()->routeIs('hr.payroll.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('wallet') !!}<span>Payroll</span></a>@endif
        <a href="{{ route('leave.my') }}" class="nav-link {{ request()->routeIs('leave.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('calendar-minus') !!}<span>My leave &amp; approvals</span></a>
        <a href="{{ route('payslips.my') }}" class="nav-link {{ request()->routeIs('payslips.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('receipt') !!}<span>My payslips</span></a>
        <a href="{{ route('hr.orgchart') }}" class="nav-link {{ request()->routeIs('hr.orgchart*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('network') !!}<span>Org chart</span></a>
        <a href="{{ route('directory.index') }}" class="nav-link {{ request()->routeIs('directory.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('network') !!}<span>Directory &amp; reporting lines</span></a>
        <a href="{{ route('hr.exits.index') }}" class="nav-link {{ request()->routeIs('hr.exits.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('log-out') !!}<span>Exits &amp; clearance</span></a>
        <a href="{{ route('hr.policies.index') }}" class="nav-link {{ request()->routeIs('hr.policies.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('book') !!}<span>HR policies</span></a>
        <a href="{{ route('hr.organisation') }}" class="nav-link {{ request()->routeIs('hr.organisation*', 'hr.checklist.*') ? 'active' : '' }}">{!! \App\Support\NavIcons::get('building') !!}<span>Organisation setup</span></a>
    </div>
</div>
        </nav>

        @if (\App\Support\SettingsNav::canOpen())
            <div class="sidebar-bottom">
                <a href="{{ route('settings.edit') }}" class="nav-link {{ \App\Support\SettingsNav::isSettingsPage() ? 'active' : '' }}">{!! \App\Support\NavIcons::get('settings') !!}<span>Settings</span></a>
            </div>
        @endif

        @if ($brand['logo'] && $brand['position'] === 'BOTTOM_LEFT')
            <div style="margin-top:auto;padding:12px 12px 0"><img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" style="display:block;max-width:100%;height:{{ $brand['height'] }}px;object-fit:contain;object-position:left"></div>
        @endif
        @auth
            @php($me = auth()->user())
            @php($roleNames = $me->roles->pluck('name')->implode(', ') ?: 'No role')
            <div class="sidebar-profile">
                <a href="{{ route('account.password') }}" class="sp-link" title="Account and password">
                    <span class="sp-avatar" aria-hidden="true">{{ strtoupper(mb_substr(collect(preg_split('/\s+/', trim($me->name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''), 0, 2)) }}</span>
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
                if (!back || !document.referrer) return;
                try {
                    var from = new URL(document.referrer);
                    if (from.origin !== location.origin || from.href === location.href || /\/(edit|create|new)$/.test(from.pathname)) return;
                    back.href = from.href;
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
                .scan-box input:not(#_){width:230px;min-height:24px;padding:2px 52px 2px 8px}
                .scan-hint{position:absolute;right:6px;top:50%;transform:translateY(-50%);font:600 10px/1 system-ui,sans-serif;color:#6b7280;background:#f3f4f6;border:1px solid #d1d5db;border-radius:4px;padding:3px 5px;pointer-events:none}
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
                    function render(data) {
                        list.innerHTML = ''; rows = []; active = -1; offer = data.offer;
                        if (offer) {
                            row([span('scan-row-main', 'Search ' + offer.label.toLowerCase() + 's only'), span('scan-row-kind', 'Tab')], { run: pickOffer });
                        }
                        data.results.forEach(function (r) {
                            row([span('scan-row-code', r.code), span('scan-row-name', r.name || ''), span('scan-row-kind', r.label)], { url: r.url });
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
                            .then(function (r) { return r.ok ? r.json() : null; }).then(function (d) { if (d) render(d); }).catch(function () {});
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
            <div class="topbar-date">{{ now()->format('D, d M Y') }}
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
                @yield('content')
            @endif
        </main>
    </div>
</div>

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
            root.classList.remove('menu-open', 'menu-peek');
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