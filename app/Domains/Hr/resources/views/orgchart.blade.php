@extends('layouts.app')
@section('title', 'Org chart')
@section('breadcrumb', 'Org chart')
@section('content')
@php $types = config('hr.employment_types'); $focus = request('focus'); @endphp
<div class="page-heading"><div><h1>Org chart</h1><p>{{ $total }} {{ $total === 1 ? 'person' : 'people' }} &middot; {{ $levels->count() }} {{ $levels->count() === 1 ? 'level' : 'levels' }}. Press + under anyone to add a person who reports to them.</p></div>
<div class="actions"><button type="button" class="btn btn-primary" data-add="" data-who="">{!! \App\Support\NavIcons::get('user-plus', 15) !!} Add person</button></div></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif

<div class="oc-wrap">
    <aside class="oc-side">
        <div class="oc-search"><input id="oc-q" type="search" placeholder="Search people, roles…" autocomplete="off" aria-label="Search the org chart"></div>
        <div class="oc-groups">
        @forelse ($levels as $lv => $list)
            <details open class="oc-grp">
                <summary><span>{{ $lv === 1 ? 'Top level' : 'Level '.$lv }}</span><em>{{ $list->count() }}</em></summary>
                @foreach ($list as $p)
                    <a href="#n-{{ $p->id }}" class="oc-row" data-go="{{ $p->id }}" data-name="{{ mb_strtolower($p->full_name.' '.$p->designation?->name.' '.$p->department?->name) }}">
                        <span class="oc-av oc-ini sm">{{ strtoupper(mb_substr($p->first_name, 0, 1).mb_substr((string) $p->last_name, 0, 1)) }}</span>
                        <span class="oc-rt"><strong>{{ $p->full_name }}</strong><small>{{ $p->designation?->name ?? '—' }}</small></span>
                    </a>
                @endforeach
            </details>
        @empty
            <p class="muted small" style="padding:12px">Nobody yet.</p>
        @endforelse
        </div>
    </aside>

    <section class="oc-stage" id="oc-stage">
        @if ($roots->isEmpty())
            <div class="empty-state" style="margin:60px auto">No one has been added yet. <button type="button" class="btn btn-primary" data-add="" data-who="">Add the first person</button></div>
        @else
            <div class="oc-canvas" id="oc-canvas"><ul class="oc-tree">@foreach ($roots as $r)@include('hr::_orgnode', ['node' => $r, 'byManager' => $byManager, 'depth' => 0])@endforeach</ul></div>
        @endif
        <div class="oc-zoom"><button type="button" id="oc-in" title="Zoom in" aria-label="Zoom in">+</button><button type="button" id="oc-out" title="Zoom out" aria-label="Zoom out">&minus;</button><button type="button" id="oc-fit" title="Reset view" aria-label="Reset view">&#8634;</button></div>
    </section>
</div>

<dialog id="oc-dialog" class="oc-dialog">
    <form method="POST" action="{{ route('hr.orgchart.store') }}">@csrf
        <div class="oc-dh"><h2 id="oc-title">Add a person</h2><button type="button" class="oc-x" data-close aria-label="Close">&times;</button></div>
        <div class="oc-db">
            <div class="field"><label>Reports to</label>
                <select name="manager_id" id="oc-mgr" style="max-width:320px"><option value="">Nobody (top level)</option>@foreach ($people as $p)<option value="{{ $p->id }}">{{ $p->full_name }}{{ $p->designation ? ' — '.$p->designation->name : '' }}</option>@endforeach</select></div>
            <div class="oc-two">
                <div class="field"><label for="oc-fn">First name</label><input id="oc-fn" name="first_name" required maxlength="80" value="{{ old('first_name') }}"></div>
                <div class="field"><label for="oc-ln">Last name</label><input id="oc-ln" name="last_name" maxlength="80" value="{{ old('last_name') }}"></div>
                <div class="field"><label for="oc-des">Job title</label><select id="oc-des" name="designation_id"><option value="">—</option>@foreach ($designations as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
                <div class="field"><label for="oc-dep">Department</label><select id="oc-dep" name="department_id"><option value="">—</option>@foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
                <div class="field"><label for="oc-et">Employment</label><select id="oc-et" name="employment_type">@foreach ($types as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="oc-jn">Joining date</label><input id="oc-jn" type="date" name="joined_on" value="{{ now()->toDateString() }}" required></div>
            </div>
            <details class="oc-more"><summary>Contact details (optional)</summary>
                <div class="oc-two" style="margin-top:10px">
                    <div class="field"><label for="oc-em">Work email</label><input id="oc-em" type="email" name="work_email" maxlength="160"></div>
                    <div class="field"><label for="oc-ph">Phone</label><input id="oc-ph" name="phone" maxlength="40"></div>
                </div></details>
            <p class="muted small" style="margin:12px 0 0">The rest (CNIC, pay, bank, documents) is filled in later on the employee's own page.</p>
        </div>
        <div class="oc-df"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">Add person</button></div>
    </form>
</dialog>

<style>
.oc-wrap { display: grid; grid-template-columns: 270px 1fr; gap: 0; height: calc(100vh - 190px); min-height: 460px; background: var(--panel, #fff); border: 1px solid var(--border, #e2e8f0); border-radius: 10px; overflow: hidden; }
.oc-side { display: flex; flex-direction: column; min-height: 0; border-right: 1px solid var(--border, #e2e8f0); }
.oc-search { padding: 12px; }
.oc-search input { width: 100%; max-width: none; }
.oc-groups { flex: 1; overflow-y: auto; padding: 0 8px 12px; }
.oc-grp summary { display: flex; justify-content: space-between; align-items: center; padding: 8px 6px; cursor: pointer; font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: #64748b; list-style: none; }
.oc-grp summary::-webkit-details-marker { display: none; }
.oc-grp summary em { font-style: normal; font-weight: 600; font-size: 11px; background: #f1f5f9; color: #475569; border-radius: 10px; padding: 1px 8px; }
.oc-row { display: flex; align-items: center; gap: 9px; padding: 6px; border-radius: 8px; color: inherit; text-decoration: none !important; }
.oc-row:hover { background: #f1f5f9; }
.oc-row.hide { display: none; }
.oc-rt { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
.oc-rt strong { font-size: 13px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.oc-rt small { font-size: 11px; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.oc-av { flex: none; width: 38px; height: 38px; border-radius: 50%; object-fit: cover; padding: 0; min-height: 0; }
.oc-ini { display: inline-flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--accent) 14%, #fff); color: var(--accent-dark); font-weight: 700; font-size: 12px; }
.oc-ini.sm { width: 28px; height: 28px; font-size: 10.5px; }
.oc-stage { position: relative; overflow: auto; background-color: #f8fafc; background-image: radial-gradient(#dbe2ea 1px, transparent 1px); background-size: 18px 18px; }
.oc-canvas { display: inline-block; min-width: 100%; padding: 36px 40px 80px; transform-origin: 0 0; text-align: center; }
.oc-tree, .oc-tree ul { list-style: none; margin: 0; padding: 0; display: flex; justify-content: center; position: relative; }
.oc-tree ul { padding-top: 22px; }
.oc-tree li { position: relative; padding: 22px 9px 0; text-align: center; display: flex; flex-direction: column; align-items: center; }
.oc-tree > li { padding-top: 0; }
.oc-tree li::before, .oc-tree li::after { content: ""; position: absolute; top: 0; right: 50%; width: 50%; height: 22px; border-top: 1px solid #cbd5e1; }
.oc-tree li::after { right: auto; left: 50%; border-left: 1px solid #cbd5e1; }
.oc-tree > li::before, .oc-tree > li::after { display: none; }
.oc-tree li:only-child::before, .oc-tree li:only-child::after { display: none; }
.oc-tree li:only-child { padding-top: 22px; }
.oc-tree li:first-child::before, .oc-tree li:last-child::after { border: 0 none; }
.oc-tree li:last-child::before { border-right: 1px solid #cbd5e1; border-radius: 0 6px 0 0; }
.oc-tree li:first-child::after { border-radius: 6px 0 0 0; }
.oc-tree ul::before { content: ""; position: absolute; top: 0; left: 50%; height: 22px; border-left: 1px solid #cbd5e1; }
.oc-tree li:only-child > ul, .oc-tree li:only-child::before { }
.oc-card { width: 214px; display: grid; grid-template-columns: 38px 1fr; gap: 4px 10px; align-items: center; text-align: left; padding: 10px 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 2px rgb(15 23 42 / 6%); transition: box-shadow .15s, border-color .15s, opacity .15s; }
.oc-card .oc-t { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
.oc-card .oc-t a { color: #0f172a; text-decoration: none; }
.oc-card .oc-t strong { font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.oc-card small { font-size: 10.5px; letter-spacing: .3px; text-transform: uppercase; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.oc-card .oc-dep { text-transform: none; letter-spacing: 0; color: #94a3b8; }
.oc-acts { grid-column: 1 / -1; display: flex; align-items: center; gap: 12px; margin-top: 4px; padding-top: 7px; border-top: 1px solid #f1f5f9; color: #94a3b8; }
.oc-acts a { display: inline-flex; color: #94a3b8; } .oc-acts a:hover { color: var(--accent-dark); }
.oc-count { margin-left: auto; font-size: 10.5px; color: #94a3b8; }
.oc-card.dim { opacity: .35; }
.oc-card.hit, .oc-card.flash { border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent); }
.oc-add { width: 22px; height: 22px; margin-top: 8px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 6px; background: #1e293b; color: #fff; font-size: 16px; line-height: 1; cursor: pointer; }
.oc-add:hover { background: var(--accent); }
.oc-zoom { position: sticky; left: 14px; bottom: 14px; display: inline-flex; gap: 6px; margin: 0 0 14px 14px; float: left; }
.oc-zoom button { width: 30px; height: 30px; padding: 0; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; color: #334155; font-size: 16px; cursor: pointer; }
.oc-zoom button:hover { background: #f1f5f9; }
.oc-dialog { width: min(560px, calc(100vw - 32px)); padding: 0; border: 0; border-radius: 14px; box-shadow: 0 24px 60px rgb(15 23 42 / 28%); }
.oc-dialog::backdrop { background: rgb(15 23 42 / 45%); }
.oc-dh { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; }
.oc-dh h2 { margin: 0; font-size: 16px; }
.oc-x { border: 0; background: transparent; font-size: 22px; line-height: 1; color: #64748b; cursor: pointer; }
.oc-db { padding: 16px 20px 6px; }
.oc-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; margin-top: 12px; }
.oc-two .field select, .oc-two .field input { max-width: 100%; }
.oc-more summary { margin-top: 12px; cursor: pointer; font-size: 12.5px; color: var(--accent-dark); }
.oc-df { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 20px 18px; }
@media (max-width: 1024px) { .oc-wrap { grid-template-columns: 1fr; height: auto; } .oc-side { max-height: 240px; border-right: 0; border-bottom: 1px solid #e2e8f0; } .oc-stage { min-height: 420px; } .oc-two { grid-template-columns: 1fr; } }
</style>
<script>
(function () {
    var dlg = document.getElementById('oc-dialog'), mgr = document.getElementById('oc-mgr'), title = document.getElementById('oc-title');
    function openAdd(id, who) {
        mgr.value = id || ''; title.textContent = who ? 'Add a person under ' + who : 'Add a person';
        if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', '');
        setTimeout(function () { document.getElementById('oc-fn').focus(); }, 30);
    }
    document.addEventListener('click', function (e) {
        var a = e.target.closest('[data-add]'); if (a) { openAdd(a.getAttribute('data-add'), a.getAttribute('data-who')); return; }
        if (e.target.closest('[data-close]')) dlg.close();
        if (e.target === dlg) dlg.close();
    });
    @if ($errors->any()) openAdd(@json(old('manager_id')), ''); @endif

    var q = document.getElementById('oc-q');
    q.addEventListener('input', function () {
        var t = q.value.trim().toLowerCase();
        document.querySelectorAll('.oc-row').forEach(function (r) { r.classList.toggle('hide', t !== '' && r.dataset.name.indexOf(t) < 0); });
        document.querySelectorAll('.oc-card').forEach(function (c) { var m = t !== '' && c.dataset.name.indexOf(t) >= 0; c.classList.toggle('hit', m); c.classList.toggle('dim', t !== '' && !m); });
    });
    function reveal(id) {
        var c = document.getElementById('n-' + id); if (!c) return;
        c.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        c.classList.add('flash'); setTimeout(function () { c.classList.remove('flash'); }, 2200);
    }
    document.querySelectorAll('.oc-row').forEach(function (r) { r.addEventListener('click', function (e) { e.preventDefault(); reveal(r.dataset.go); }); });

    var cv = document.getElementById('oc-canvas'), z = 1;
    function zoom(v) { if (!cv) return; z = Math.max(.4, Math.min(1.4, v)); cv.style.transform = 'scale(' + z + ')'; cv.style.width = z < 1 ? (100 / z) + '%' : ''; }
    document.getElementById('oc-in').onclick = function () { zoom(z + .1); };
    document.getElementById('oc-out').onclick = function () { zoom(z - .1); };
    document.getElementById('oc-fit').onclick = function () { zoom(1); document.getElementById('oc-stage').scrollTo(0, 0); };
    @if ($focus) setTimeout(function () { reveal(@json($focus)); }, 250); @endif
})();
</script>
@endsection
