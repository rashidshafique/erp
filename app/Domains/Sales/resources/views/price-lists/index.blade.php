@extends('layouts.app')
@section('title', 'Price lists')
@section('content')
<div class="page-heading"><div><h1>Price lists</h1><p>Product prices per piece or per kg, with quantity breaks. A customer given its own list is priced from it first; the default list prices everyone else and fills the gaps. Order lines pick up the price when the product is chosen.</p></div></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Name</th><th>Valid</th><th class="num">Prices</th><th class="num">Customers</th><th></th></tr></thead><tbody>
@forelse ($lists as $l)<tr><td><a class="mono" href="{{ route('price-lists.show', $l) }}">{{ $l->code }}</a></td><td>{{ $l->name }}</td>
<td class="small">{{ $l->valid_from?->displayShortDate() ?? 'Always' }}{{ $l->valid_to ? ' to '.$l->valid_to->displayShortDate() : '' }}</td><td class="num">{{ $l->items_count }}</td><td class="num">{{ $l->is_default ? 'Everyone else' : $l->customers_count }}</td>
<td>@if ($l->is_default)<span class="status status-completed">Default</span>@endif @unless ($l->is_active)<span class="status status-cancelled">Off</span>@endunless</td></tr>
@empty<tr><td colspan="6" class="muted">No price list yet. Order lines take the last price the product was sold at.</td></tr>@endforelse</tbody></table></div></div>
<div class="panel"><div class="panel-header"><h2>New price list</h2></div><form method="POST" action="{{ route('price-lists.store') }}">@csrf<div class="panel-body form-grid">
    <label>Code<input name="code" maxlength="40" required value="{{ old('code', $lists->isEmpty() ? 'STANDARD' : '') }}"></label>
    <label>Name<input name="name" maxlength="120" required value="{{ old('name', $lists->isEmpty() ? 'Standard prices' : '') }}"></label>
    <label>Currency<input name="currency" maxlength="10" value="{{ old('currency') }}"></label>
    <label>Valid from<input type="date" name="valid_from" value="{{ old('valid_from') }}"></label>
    <label>Valid to<input type="date" name="valid_to" value="{{ old('valid_to') }}"></label>
    <label><span><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $lists->isEmpty()))> Default list</span><span class="muted small">Prices every customer without a list of its own.</span></label>
</div><div class="panel-body"><button class="btn btn-primary">Add</button></div></form></div>
<div class="panel"><div class="panel-header"><h2>Check</h2></div><form method="POST" action="{{ route('price-lists.settings') }}">@csrf @method('PUT')<div class="panel-body form-grid">
    <label>An order line priced under its list price<select name="check">@foreach (\App\Domains\Sales\Services\PriceLists::CHECK_OPTIONS as $k => $v)<option value="{{ $k }}" @selected($check === $k)>{{ $v }}</option>@endforeach</select></label>
</div><div class="panel-body"><button class="btn">Save</button></div></form></div>
@endsection
