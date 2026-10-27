{{-- The Sales module's panel on a business partner page that is a customer: which price list it buys from. $party. --}}
@if (($customer = $party->customer) && \App\Http\Middleware\EnforceAccess::allows(auth()->user(), 'price-lists.assign'))
@php $lists = \App\Domains\Sales\Models\PriceList::orderBy('code')->get(); $default = $lists->firstWhere('is_default', true); @endphp
<div class="panel"><div class="panel-header"><h2>Prices</h2><span class="muted small">New order lines take this list's price.</span></div>
<div class="panel-body"><form method="POST" action="{{ route('price-lists.assign', $customer) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">@csrf @method('PUT')
    <label for="price_list_id" class="muted small">Price list</label>
    <select id="price_list_id" name="price_list_id"><option value="">{{ $default ? 'Default: '.$default->code : 'None (last price sold)' }}</option>@foreach ($lists->where('is_default', false) as $l)<option value="{{ $l->id }}" @selected($customer->price_list_id === $l->id)>{{ $l->code }} · {{ $l->name }}</option>@endforeach</select>
    <button class="btn">Save</button>
    @if (Route::has('price-lists.index'))<a class="small" href="{{ route('price-lists.index') }}">Price lists</a>@endif
</form></div></div>
@endif
