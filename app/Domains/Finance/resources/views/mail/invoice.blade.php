@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<p>Dear {{ $invoice->customer?->name }},</p>
<p>{{ $credit ? 'Credit note' : 'Invoice' }} <strong>{{ $invoice->invoice_number }}</strong> dated {{ $invoice->invoice_date->displayShortDate() }}@if ($invoice->customer_reference) for your order {{ $invoice->customer_reference }}@endif.</p>
<p>Total {{ $m($invoice->total) }}@if (! $credit && $invoice->due_date), due {{ $invoice->due_date->displayShortDate() }}@endif.</p>
<p>Thank you,<br>{{ config('app.name') }}</p>
