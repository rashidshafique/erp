@extends('layouts.app')

@section('title', 'Floor components')
@section('breadcrumb', 'Settings / Floor components')

@push('styles')
<style>
    .kit-sec { margin: 0 0 34px; }
    .kit-sec > h2 { font-size: 18px; margin: 0 0 4px; }
    .kit-sec > p { margin: 0 0 14px; color: #55585d; max-width: 820px; }
    .kit-state { font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #55585d; margin: 14px 0 6px; }
    .kit-frame { background: #fff; border: 1px solid #e6e7e9; border-radius: 10px; padding: 16px; }
    .kit-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 16px; }
    .kit-tabs .fl-tabs { position: static; border: 1px solid #e6e7e9; border-radius: 10px; overflow: hidden; }
    .kit-tabs .fl-tabs { background: repeating-linear-gradient(135deg, #fff, #fff 6px, #f6f6f7 6px, #f6f6f7 12px); }
    .kit-tabs nav { background: #fff; }
    .kit-chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px; }
</style>
@endpush

@section('content')
<div class="page-heading">
    <div><h1>Floor components</h1><p>The round 4 parts for the due-now homes and tablet screens (design R4 Components), each in every state. Nothing here saves.</p></div>
</div>

{{-- C8 --}}
<section class="kit-sec fl-gloves" id="c8">
    <h2>C8 Big next-step button</h2>
    <p>Whatever the running heat needs next. The largest thing on a furnace screen, exactly one per screen; every other button there is 56 px.</p>
    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(420px,1fr))">
        <div><div class="kit-state">Default</div><x-floor.big-button type="button" label="Charged #7" sub="as recipe · 50 pig · 40 steel · 50 returns · 16 coke" /></div>
        <div><div class="kit-state">Disabled · reason in the button</div><x-floor.big-button label="Pour moulds" disabled="Waiting: no moulds counted ready for EN-GJL-200" /></div>
        <div><div class="kit-state">Warning</div><x-floor.big-button type="button" tone="warn" label="Close HT-312 first" sub="open 25 h · no bottom drop · then start HT-314" /></div>
        <div><div class="kit-state">Danger · lateness in words</div><x-floor.big-button type="button" tone="bad" label="Charged #7" sub="Charge #7 is 4 min late · due 10:31" /></div>
        <div><div class="kit-state">Empty · not a button</div><x-floor.big-button empty label="Nothing to do on this heat" sub="Next charge #8 due 11:05" /></div>
    </div>
</section>

{{-- C2 --}}
<section class="kit-sec fl-tablet" id="c2">
    <h2>C2 Count field and keypad</h2>
    <p>Every count on a tablet is typed on this keypad, never the system keyboard. The field carries the state; the keys never change meaning. Next moves on, and on the last field presses Save.</p>
    <div class="kit-grid">
        <div><div class="kit-state">Empty</div><x-floor.count-field name="k_empty" label="Moulds counted" /></div>
        <div><div class="kit-state">Default</div><x-floor.count-field name="k_default" label="Moulds counted" value="96" :records="96" /></div>
        <div><div class="kit-state">Warning · allowed, explains what follows</div><x-floor.count-field name="k_warn" label="Moulds counted" value="93" :records="96" below="Less than records say (96). Next asks for the reason." /></div>
        <div><div class="kit-state">Danger · blocks Save</div><x-floor.count-field name="k_bad" label="Moulds poured" value="120" :max="93" above="More than were counted ready (93). Lower it to save." /></div>
        <div><div class="kit-state">Disabled · locked</div><x-floor.count-field name="k_locked" label="Moulds counted" value="93" locked="Locked: the pour gate for HT-313 is open." /></div>
    </div>

    <div class="kit-state">Together, as on a 1024 tablet: fields left, keypad right, Save under the keypad</div>
    <form class="kit-frame" method="GET" action="{{ route('settings.floor-kit') }}#c2">
        <div class="fl-block-head" style="margin-top:0">Count moulds before pouring</div>
        <p class="fl-row-meta" style="margin:0 0 14px">PH-622 · CUP-02 · 11:30 · <span class="fl-mono">PAT-0270</span> Golden Bend 6pc</p>
        <div class="fl-count-pad">
            <div>
                <x-floor.count-field name="counted" label="Moulds counted" :value="request('counted', 93)" :records="96" :max="120" below="Less than records say (96). Next asks for the reason." above="More than the 120 on the list. Lower it to save." />
                <x-floor.count-field name="damaged" label="Damaged" :value="request('damaged', 0)" />
            </div>
            <x-floor.keypad save="Save {total} moulds" />
        </div>
    </form>
    <div class="kit-state">Keypad disabled · while saving or when the field is locked</div>
    <form class="kit-frame" onsubmit="return false" style="width:max-content"><x-floor.keypad disabled save="Save 93 moulds" /></form>
</section>

{{-- C1 --}}
<section class="kit-sec kit-tabs" id="c1">
    <h2>C1 Tablet tab bar</h2>
    <p>Home, the role's tabs in flow order, Scan last. Fixed to the bottom on a screen, shown in place here. Tabs without permission are left out, never greyed.</p>
    @php($here = route('settings.floor-kit').'#c1')
    <div class="kit-state">Default · moulding supervisor, Home active</div>
    <x-floor.tab-bar :scan="$here" :tabs="[['label' => 'Home', 'href' => $here, 'active' => true], ['label' => 'List', 'href' => $here], ['label' => 'Count', 'href' => $here]]" />
    <div class="kit-state">To do · count badge</div>
    <x-floor.tab-bar :scan="$here" :tabs="[['label' => 'Home', 'href' => $here, 'active' => true], ['label' => 'List', 'href' => $here], ['label' => 'Count', 'href' => $here, 'count' => 3]]" />
    <div class="kit-state">Warning · a count is due on the next heat</div>
    <x-floor.tab-bar :scan="$here" :tabs="[['label' => 'Home', 'href' => $here, 'active' => true], ['label' => 'List', 'href' => $here], ['label' => 'Count', 'href' => $here, 'count' => 1, 'tone' => 'warn']]" />
    <div class="kit-state">Danger · furnace, a charge is overdue while the operator is on Heat</div>
    <x-floor.tab-bar :scan="$here" :tabs="[['label' => 'Home', 'href' => $here, 'tone' => 'bad'], ['label' => 'Heat', 'href' => $here, 'active' => true], ['label' => 'Heats', 'href' => $here]]" />
    <div class="kit-state">Disabled · Scan greyed only when the camera is blocked</div>
    <x-floor.tab-bar :scan="$here" scan-blocked :tabs="[['label' => 'Home', 'href' => $here, 'active' => true], ['label' => 'Ready', 'href' => $here], ['label' => 'Dispatches', 'href' => $here]]" />
    <div class="kit-state">Empty · quality, no badge</div>
    <x-floor.tab-bar :scan="$here" :tabs="[['label' => 'Home', 'href' => $here, 'active' => true], ['label' => 'Lots', 'href' => $here], ['label' => 'NCRs', 'href' => $here]]" />
</section>

{{-- C3 --}}
<section class="kit-sec" id="c3">
    <h2>C3 Home row</h2>
    <p>Text, age, one button. The whole row is the target. There is no done button: the row goes when the work is done elsewhere.</p>
    <div class="kit-state">One row, four densities</div>
    <div class="kit-frame">
        <div class="fl-desktop fl-rows"><x-floor.row title="Tomorrow's moulding list (Sat Morning) not issued" meta="7 orders · 380 moulds" age="due 20:00" action="Prepare list" href="#c3" /></div>
        <div class="fl-tablet fl-rows" style="margin-top:12px"><x-floor.row title="PAT-0239 Golden Bracket" meta="PRD-1038 · 0 / 50 moulds" age="since 07:10" action="Enter moulds" href="#c3" /></div>
        <div class="fl-gloves fl-rows" style="margin-top:12px"><x-floor.row title="HT-312 · CUP-01 · open 25 h" meta="no bottom drop recorded" age="25 h" tone="warn" action="Close heat" href="#c3" /></div>
        <div class="fl-phone fl-rows" style="margin-top:12px;max-width:390px"><x-floor.row title="SO-1038 Zeeshan Sb" meta="40 castings waiting for QC" age="8 days" tone="warn" action="Why" link href="#c3" /></div>
    </div>
    <div class="kit-state">States (tablet)</div>
    <div class="kit-frame fl-tablet">
        <div class="fl-rows">
            <x-floor.row title="Aamir Sb · 1 lot cleared, not packed" meta="LOT-5540 · 196 pcs · 882.0 kg · SO-1046 due 13 Nov" age="2 h" action="Pack" href="#c3" />
            <x-floor.row title="Sat Morning list · 2 rows changed since issue" meta="Waiting for Rashid to reissue" age="10 min" action="Count moulds" disabled />
            <x-floor.row title="PK-44 · Omar · packed, not dispatched" meta="300 pcs · 1,350.0 kg" age="2 days" tone="warn" action="Dispatch" href="#c3" />
            <x-floor.row title="HT-312 · charge 3 is 6 min overdue" meta="CUP-01 · 50 pig · 40 steel · 50 returns" age="6 min" tone="bad" action="Melt shop" href="#c3" />
            <x-floor.row title="Golden Pumps · LOT-5531 · Not ready, waiting for rework" meta="3 pcs in rework · full lots only · need 103" age="1 day" href="#c3" />
        </div>
        <div class="fl-block-head">Empty · Waiting for your decision</div>
        <div class="fl-rows"><div class="fl-row-empty">Nothing waiting for your decision.</div></div>
    </div>
</section>

{{-- C4 --}}
<section class="kit-sec fl-tablet" id="c4">
    <h2>C4 Blocker line</h2>
    <p>✓ or ✗, the reason, and the one button that clears it. Failed lines first, then warnings, then passed.</p>
    <div class="fl-block-head">! What blocks dispatch</div>
    <div class="fl-blockers">
        <x-floor.blocker state="fail" title="Open NCR NCR-62 · 3 pcs" detail="Rework not recorded yet" action="Record rework" href="#c4" />
        <x-floor.blocker state="fail" title="Thermal test 52 min old at pour" detail="Needs a quality manager override" who="Quality manager only" />
        <x-floor.blocker state="warn" title="Customer takes full lots only" detail="Golden Pumps · 100 of 103 · minimum not met" action="Hold lot" href="#c4" />
        <x-floor.blocker state="pass" title="Final inspection passed" detail="INS-88 · 100 of 103 accepted · Sat 09:40" />
        <x-floor.blocker state="loading" title="Checking chemistry for HT-314…" />
    </div>
    <div class="kit-state">Empty · nothing blocks</div>
    <div class="fl-blockers"><x-floor.blocker state="clear" title="Nothing blocks dispatch." action="Release lot" href="#c4" /></div>
</section>

{{-- C6 --}}
<section class="kit-sec" id="c6">
    <h2>C6 Status chips</h2>
    <p>Each status with its symbol, so it reads without colour. Labels, not buttons; a chip never carries an age.</p>
    @foreach (['fl-desktop' => 'Desktop', 'fl-tablet' => 'Tablet', 'fl-gloves' => 'Furnace'] as $density => $name)
        <div class="kit-state">{{ $name }}</div>
        <div class="kit-chips {{ $density }}">
            @foreach (['IN_PRODUCTION', 'ON_HOLD', 'CLEAR', 'READY', 'PACKED', 'DISPATCHED', 'OPEN', 'CLOSED'] as $status)<x-floor.chip :status="$status" />@endforeach
        </div>
    @endforeach
    <div class="kit-state">Empty or unknown</div>
    <div class="kit-chips fl-desktop"><x-floor.chip /></div>
</section>

{{-- C9 --}}
<section class="kit-sec fl-desktop" id="c9">
    <h2>C9 NEW tag</h2>
    <p>An annotation for the build, never a status. Removed when the feature ships.</p>
    <div class="kit-chips"><x-floor.new-tag /> <span class="fl-btn">Pass to…</span> <x-floor.new-tag /></div>
</section>
@endsection
