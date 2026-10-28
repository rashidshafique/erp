{{-- The books-closed date, shown on Finance > Tax & year end. --}}
@php
    $closedTo = \App\Domains\Ledger\Services\LedgerSettings::closedTo();
    $last = \App\Support\FinancialYear::previous();
@endphp
<div class="panel"><div class="panel-header"><h2>Closing the books</h2></div>
<form method="POST" action="{{ route('ledger.settings.closing') }}">@csrf @method('PUT')<div class="panel-body form-grid">
    <label>Books closed to<input type="date" name="closed_to" value="{{ $closedTo }}"><span class="muted small">Nothing can be posted on or before this date, by hand or by a document. Leave empty while the period is open.</span></label>
</div><div class="panel-body"><button class="btn btn-primary">Save date</button></div></form>
<form method="POST" action="{{ route('ledger.settings.closing') }}" class="panel-body">@csrf @method('PUT')<input type="hidden" name="close_year" value="1">
    <button class="btn" onclick="return confirm('Close the books to {{ $last['end']->toDateString() }}? Nothing can then be posted on or before that date until it is reopened here.')">Close the year {{ \App\Support\FinancialYear::label($last) }}</button>
    <span class="muted small">Sets the date above to {{ $last['end']->displayDate() }}, the last day of the year that has just ended.</span></form></div>
