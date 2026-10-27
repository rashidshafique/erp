{{-- The Finance module's panel on a business partner page that is a customer: credit limit, what they owe against it, hold and release. $party. --}}
@php
    $customer = $party->customer;
    $canEdit = \App\Http\Middleware\EnforceAccess::allows(auth()->user(), 'finance.credit.update');
    $canSee = \App\Http\Middleware\EnforceAccess::allows(auth()->user(), 'receivables.index');
@endphp
@if ($customer && $canSee)
@php
    $m = fn ($v) => \App\Support\Formats::number($v, 2);
    $limits = app(\App\Domains\Finance\Services\CreditLimits::class);
    $exposure = $limits->exposure($customer->id);
    $days = \App\Domains\Finance\Services\FinanceRules::creditOverdueDays();
    $status = $limits->check($customer->id);
@endphp
<div class="panel"><div class="panel-header"><h2>Credit</h2><span class="muted small">Checked at contract review and dispatch: {{ strtolower(\App\Domains\Finance\Services\FinanceRules::CREDIT_OPTIONS[\App\Domains\Finance\Services\FinanceRules::creditCheck()]) }}.</span></div>
<div class="panel-body">
    @if ($status['messages'])<div class="alert {{ $status['severity'] === 'BLOCK' ? 'alert-error' : ($status['severity'] === 'WARN' ? 'alert-warning' : 'alert-success') }}">{{ implode(' ', $status['messages']) }}</div>@endif
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px">
        <div><div class="muted small">Credit limit</div><strong>{{ $customer->credit_limit !== null ? $m($customer->credit_limit) : 'No limit' }}</strong></div>
        <div><div class="muted small">Owed on invoices</div><a href="{{ route('receivables.statement', $customer) }}">{{ $m($exposure['invoices']) }}</a>@if ($exposure['on_account'] > 0)<div class="muted small">less {{ $m($exposure['on_account']) }} on account</div>@endif</div>
        @if (\App\Domains\Finance\Services\FinanceRules::creditCountsOrders())<div><div class="muted small">Open orders not invoiced</div>{{ $m($exposure['orders']) }}<div class="muted small">before tax</div></div>@endif
        @if ($customer->credit_limit !== null)<div><div class="muted small">Still available</div><strong @if ($customer->credit_limit - $exposure['total'] < 0) style="color:var(--danger)" @endif>{{ $m($customer->credit_limit - $exposure['total']) }}</strong></div>@endif
        @if ($days > 0)<div><div class="muted small">Overdue over {{ $days }} days</div>{{ $m($limits->overdue($customer->id, $days)) }}</div>@endif
        <div><div class="muted small">Hold</div><strong>{{ $customer->credit_hold ? 'On hold' : 'No' }}</strong>@if ($customer->credit_release_until)<div class="muted small">released until {{ $customer->credit_release_until->displayDate() }}</div>@endif</div>
    </div>
    @if ($canEdit)
        <details style="margin-top:14px"><summary class="btn">Edit credit terms</summary>
        <form method="POST" action="{{ route('finance.credit.update', $customer) }}" style="margin-top:12px">@csrf @method('PUT')
            <div class="form-grid">
                <div class="field"><label for="credit_limit">Credit limit</label><input type="number" step="any" min="0" id="credit_limit" name="credit_limit" value="{{ $customer->credit_limit }}"><div class="help">Empty: no limit.</div></div>
                <div class="field"><label><input type="hidden" name="credit_hold" value="0"><input type="checkbox" name="credit_hold" value="1" @checked($customer->credit_hold)> On credit hold</label><div class="help">Stops new orders at contract review and every dispatch, whatever the limit.</div></div>
                <div class="field"><label for="credit_hold_reason">Hold reason</label><input id="credit_hold_reason" name="credit_hold_reason" maxlength="300" value="{{ $customer->credit_hold_reason }}"></div>
                <div class="field"><label for="credit_release_until">Released until</label><input type="date" id="credit_release_until" name="credit_release_until" value="{{ $customer->credit_release_until?->toDateString() }}"><div class="help">Lets orders and dispatches through despite the limit or hold, up to this date.</div></div>
            </div>
            <button class="btn btn-primary">Save credit terms</button>
        </form></details>
    @endif
</div></div>
@endif
