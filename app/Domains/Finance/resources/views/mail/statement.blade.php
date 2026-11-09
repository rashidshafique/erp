@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<p>Dear {{ $customer->name }},</p>
<p>Your account with us stands at <strong>{{ $m($balance) }}</strong>. These invoices are open:</p>
<table cellpadding="4" style="border-collapse:collapse">
<tr><th align="left">Invoice</th><th align="left">Date</th><th align="left">Due</th><th align="right">Balance</th></tr>
@foreach ($open as $i)
<tr><td>{{ $i->invoice_number }}</td><td>{{ $i->invoice_date->displayShortDate() }}</td><td>{{ $i->due_date?->displayShortDate() }}</td><td align="right">{{ $m($i->balance()) }}</td></tr>
@endforeach
</table>
<p>Thank you,<br>{{ config('app.name') }}</p>
