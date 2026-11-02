@extends('layouts.app')

@section('title', 'New customer checklist')
@section('breadcrumb', 'Help / New customer')

@php
    // A link to a screen, left out when the module that owns it is switched off.
    $go = fn (string $route, string $label, array $params = []) => \Illuminate\Support\Facades\Route::has($route) ? '<a class="btn btn-small" href="'.e(route($route, $params)).'">'.e($label).' →</a>' : '';
    $tick = fn ($ok, $text = null) => $ok ? '<span class="status status-completed">'.e($text ?? 'Done').'</span>' : '<span class="status status-cancelled">Missing</span>';
@endphp

@section('content')
<div class="page-heading"><div><h1>New customer checklist</h1><p>From first call to first pour, in the order the system needs it. Pick a customer to see what is done.</p></div></div>

<div class="panel"><div class="panel-body">
    <form method="GET" action="{{ route('help.new-customer') }}" data-no-toolbar style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        <div class="field"><label for="customer">Check a customer</label>
            <select id="customer" name="customer" data-search-select><option value="">Choose a customer…</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected($customer?->id === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <button class="btn btn-primary" type="submit">Show their progress</button>
    </form>
    @include('partials.search-select')

    @if ($customer)
        @if ($rows->isEmpty())
            <p class="muted" style="margin-bottom:0">{{ $customer->name }} has no orders yet, so there are no products to check. Start at step 2 below.</p>
        @else
            <div class="table-wrap" style="margin-top:12px"><table>
                <thead><tr><th>Product ordered by {{ $customer->name }}</th><th>Revision</th><th>Iron grade</th><th title="Metal to melt for one piece, as the melting plan counts it">Metal per piece</th><th>Pattern with a cavity</th><th>Pattern owner</th><th>Open order</th></tr></thead>
                <tbody>@foreach ($rows as $r)
                    <tr>
                        <td><a href="{{ route('products.show', $r['product']) }}"><strong>{{ $r['product']->name }}</strong></a><div class="muted small mono">{{ $r['product']->code }}</div></td>
                        <td>{!! $tick($r['revision']) !!}</td>
                        <td>{!! $tick($r['grade'], $r['grade']) !!}</td>
                        <td>{!! $tick($r['weight'] !== null, $r['weight'] !== null ? \App\Support\Formats::number($r['weight'], 2).' kg' : null) !!}</td>
                        <td>{!! $tick($r['mapped'], $r['patterns']->pluck('name')->implode(', ')) !!}</td>
                        <td>{!! $r['mapped'] ? ($r['owned'] ? '<span class="status status-completed">Customer\'s</span>' : '<span class="status">Foundry-owned</span>') : '<span class="muted">—</span>' !!}</td>
                        <td>{!! $tick($r['order'], 'Released') !!}</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            <p class="muted small" style="margin-bottom:0">"Foundry-owned" is fine for patterns the foundry made; mark customer patterns on the pattern's Ownership tab.</p>
        @endif
    @endif
</div></div>

<div class="panel"><div class="panel-header"><h2>Steps</h2></div><div class="panel-body">
    <ol class="help-steps">
        <li><strong>The customer.</strong> Sales → Customers, or just type the name on the quick order screen. Add address and terms later.
            <div class="help-go">{!! $go('customers.create', 'New customer') !!}</div></li>
        <li><strong>The product.</strong> Products → New product: code, name, type, weight. Revision 0 is made when needed.
            <div class="help-go">{!! $go('products.create', 'New product') !!}</div></li>
        <li><strong>The metal.</strong> Product page, Iron grade tab: pick revision 0 and the grade, then Set grade. Heats cannot be sized without it.</li>
        <li><strong>The patterns.</strong> Patterns → New pattern, one per plate. The type sets the mould arrangement. Mark customer-owned patterns on the Ownership tab.
            <div class="help-go">{!! $go('patterns.create', 'New pattern') !!}</div></li>
        <li><strong>Link patterns to the product.</strong> On the product page, "Link a pattern": set cavities and casting weight. This lets moulds be counted against orders.</li>
        <li><strong>The order.</strong> Quick flow → New order: customer, product, quantity, due date. It releases the production order at once.
            @if ($meltingSet)<div class="help-note">Next melting day: <strong>{{ $nextMeltingDay?->isToday() ? 'today' : ($nextMeltingDay?->format('D j M') ?? 'none soon') }}</strong>. {{ $meltingRule }} A due date with no melting day in time is flagged.</div>
            @else<div class="help-note">Set the furnace days under Settings → Melting days; orders are checked against them.</div>@endif
            <div class="help-go">{!! $go('quick.orders.create', 'New order') !!} {!! $go('settings.melting-days.edit', 'Melting days') !!}</div></li>
        <li><strong>Plan the heat.</strong> Quick flow → Next heat shows what to melt. Start the heat on the campaign day.
            <div class="help-go">{!! $go('quick.heat.index', 'Next heat') !!}</div></li>
        <li><strong>Plan the moulding.</strong> Work lists: one row per plate (pattern, station, moulds), issue it, then moulders start and complete runs. A flaskless line takes one pattern at a time.
            <div class="help-go">{!! $go('work-lists.index', 'Work lists') !!}</div></li>
        <li><strong>Pour.</strong> Record customer pours on the pour register: attaching moulds creates the lots for inspection and dispatch.
            <div class="help-go">{!! $go('pours.index', 'Pour register') !!}</div></li>
        <li><strong>After the pour.</strong> Thermal and chemistry tests, cooling before shakeout, then lots, inspection and dispatch.
            <div class="help-go">{!! $go('lots.index', 'Casting lots') !!} {!! $go('dispatches.index', 'Dispatch') !!}</div></li>
    </ol>
</div></div>

<div class="panel"><div class="panel-header"><h2>Which way to do it</h2></div><div class="panel-body">
    <p class="muted small" style="margin-top:0">Some jobs can be done more than one way, with different records. Choose with care.</p>
    <div class="table-wrap"><table class="help-table">
        <thead><tr><th>Job</th><th>Ways</th><th>Use</th></tr></thead>
        <tbody>
            <tr><td>Enter an order</td><td>Quick order · Sales order with contract review · Enquiry and quotation</td>
                <td>Quick order for repeat or urgent work. Full sales order when quality must sign off first.</td></tr>
            <tr><td>Record moulds made</td><td>Work list · Moulding run page · Bulk run grid · Moulds made grid</td>
                <td>Plan on a work list. The others record afterwards; never record the same plates twice.</td></tr>
            <tr><td>Record a pour</td><td>Pour register · Moulds poured grid</td>
                <td><strong>Pour register for customer orders.</strong> The grid records the pour but attaches no moulds, so it creates no casting lots.</td></tr>
            <tr><td>Start a heat</td><td>Next heat · Melting plan · New heat by hand</td>
                <td>Next heat for quick-flow work; the Melting plan when heats are planned days ahead.</td></tr>
            <tr><td>Record charges</td><td>Charge form · One-click confirm · Fill in past charges · Add missing from the total</td>
                <td>Confirm as scheduled, or "add missing from the total" if charges were not recorded live.</td></tr>
        </tbody>
    </table></div>
</div></div>
<style>
    .help-table td, .help-table th { white-space: normal; vertical-align: top; }
    .help-table td:nth-child(1) { width: 170px; font-weight: 600; }
    .help-steps { margin: 0; padding-left: 22px; display: grid; gap: 12px; }
    .help-steps li { line-height: 1.5; }
    .help-go { margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap; }
    .help-note { margin-top: 6px; padding: 8px 12px; border-radius: 8px; background: color-mix(in srgb, var(--accent) 8%, transparent); font-size: 13px; }
</style>
@endsection
