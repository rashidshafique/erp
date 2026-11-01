@extends('layouts.app')
@section('title', 'Sales documents')
@section('breadcrumb', 'Sales documents')
@section('content')
@include('ledger::_styles')
@php $cb = \App\Domains\Finance\Services\ChargeBoxes::class; $plural = fn (int $n) => $n.' '.\Illuminate\Support\Str::plural('box', $n); @endphp
<form method="POST" action="{{ route('ledger.settings.sales-documents.update') }}" class="ac-card">@csrf @method('PUT')
    <div class="ac-bar">
        <div class="ac-crumb"><span>Accounts</span><span class="sep">/</span><span>Settings</span><span class="sep">/</span><span>Sales documents</span></div>
        <div class="ac-actions"><a class="ac-btn" href="{{ route('ledger.settings.sales-documents') }}">Cancel</a><button class="ac-btn primary">Save</button></div>
    </div>
    <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:40px;padding:24px 28px 28px;font-size:13px" class="sd-grid">
        <div style="display:flex;flex-direction:column;gap:18px">
            @if (session('success'))<div class="ac-toast" role="status"><span>{{ session('success') }}</span></div>@endif
            @if ($errors->any())<div class="ac-toast bad" role="alert"><span>{{ $errors->first() }}</span></div>@endif
            <div class="ac-sec">Charge and deduction boxes</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div class="ac-field"><label class="lab" for="sd-c">Additional charges</label><select id="sd-c" name="charges">@for ($i = 0; $i <= $cb::MAX; $i++)<option value="{{ $i }}" @selected($charges === $i)>{{ $plural($i) }}</option>@endfor</select><span class="hint">Added after tax, before Net. E.g. freight, packing.</span></div>
                <div class="ac-field"><label class="lab" for="sd-d">Additional deductions</label><select id="sd-d" name="deductions">@for ($i = 0; $i <= $cb::MAX; $i++)<option value="{{ $i }}" @selected($deductions === $i)>{{ $plural($i) }}</option>@endfor</select><span class="hint">Taken off after tax, before Net. E.g. discount, offer.</span></div>
            </div>
            <div class="ac-field"><span class="lab">Show on</span>
                <div style="display:flex;gap:18px;flex-wrap:wrap">@foreach ($cb::DOCUMENT_LABELS as $k => $l)<label class="ac-check" style="color:{{ in_array($k, $documents, true) ? '#1c1d1f' : '#6b6e73' }};margin:0"><input type="checkbox" name="documents[]" value="{{ $k }}" @checked(in_array($k, $documents, true)) style="width:15px;height:15px;margin:0">{{ $l }}</label>@endforeach</div>
                <span class="hint">Sales invoices and credit notes show the boxes today; sales orders and quotes keep the choice for when their forms take them.</span>
            </div>
            <div style="padding:10px 12px;border-radius:7px;background:#f8f8f9;font-size:12.5px;color:#6b6e73;line-height:1.5;text-wrap:pretty">Each box offers only accounts ticked <strong style="font-weight:600;color:#1c1d1f">Deduction and charge</strong> in the <a href="{{ route('ledger.accounts.index') }}">chart of accounts</a>. Charges credit the picked account; deductions debit it. Receivables take the Net.</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px">
            <div class="ac-sec">Accounts available · {{ $accounts->count() }}</div>
            <div class="ac-table"><table><thead><tr><th>Code</th><th>Account</th><th>Used as</th><th>Posts</th><th></th></tr></thead><tbody>
                @forelse ($accounts as $a)
                    @php $as = $cb::usedAs($a); @endphp
                    <tr><td><code>{{ $a->code }}</code></td><td>{{ $a->name }}</td><td>{{ $as }}</td><td class="ac-muted">{{ $as === 'Charge' ? 'Cr' : 'Dr' }}</td><td class="r"><a href="{{ route('ledger.accounts.index', ['q' => $a->code]) }}">Edit</a></td></tr>
                @empty
                    <tr><td colspan="5" class="ac-muted">No account is ticked Deduction and charge yet. Tick one in the chart of accounts.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="ac-note">"Used as" follows the account: revenue and other income accounts suit charges, sales deductions suit deductions. Any ticked account can go in either box.</div>
        </div>
    </div>
</form>
<style>@media (max-width: 900px) { .sd-grid { grid-template-columns: minmax(0,1fr) !important; } }</style>
@endsection
