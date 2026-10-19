@extends('layouts.app')

@section('title', 'New order')
@section('breadcrumb', 'Quick flow / New order')

@section('content')
<div class="page-heading">
    <div><h1>New order</h1><p>Customer, products, quantities and due date. The order goes straight into the next heat plan: no contract review, no separate production order.</p></div>
    <div class="actions"><a class="btn" href="{{ route('quick.heat.index') }}">Next heat</a></div>
</div>
<form method="POST" action="{{ route('quick.orders.store') }}">@csrf
    <div class="panel"><div class="panel-body form-grid">
        <div class="field"><label for="customer_id">Customer</label>
            <select id="customer_id" name="customer_id"><option value="">— new customer below —</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id') === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="field"><label for="new_customer">New customer name</label><input id="new_customer" name="new_customer" maxlength="150" value="{{ old('new_customer') }}"></div>
        <div class="field"><label for="due_date">Due date</label><input id="due_date" type="date" name="due_date" min="{{ today()->toDateString() }}" value="{{ old('due_date') }}"></div>
        <div class="field"><label for="customer_reference">Customer PO</label><input id="customer_reference" name="customer_reference" maxlength="100" value="{{ old('customer_reference') }}"></div>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Product</th><th>Quantity</th><th>Due (if different)</th><th>Iron grade</th><th>Casting weight (kg)</th></tr></thead>
        <tbody>
        @for ($i = 0; $i < 5; $i++)
            <tr>
                <td><select name="lines[{{ $i }}][product_id]" aria-label="Product"><option value="">—</option>@foreach ($products as $p)<option value="{{ $p['product']->id }}" data-grade="{{ $p['hasGrade'] ? 1 : 0 }}" data-weight="{{ $p['hasWeight'] ? 1 : 0 }}" @selected(old("lines.$i.product_id") === $p['product']->id)>{{ $p['product']->code }} — {{ $p['product']->name }}</option>@endforeach</select></td>
                <td><input type="number" min="1" name="lines[{{ $i }}][qty]" value="{{ old("lines.$i.qty") }}" aria-label="Quantity" style="max-width:110px"></td>
                <td><input type="date" name="lines[{{ $i }}][due_date]" min="{{ today()->toDateString() }}" value="{{ old("lines.$i.due_date") }}" aria-label="Due date"></td>
                <td><select name="lines[{{ $i }}][iron_grade_id]" class="needs-grade" aria-label="Iron grade"><option value="">known</option>@foreach ($grades as $g)<option value="{{ $g->id }}" @selected(old("lines.$i.iron_grade_id") === $g->id)>{{ $g->code }}</option>@endforeach</select></td>
                <td><input type="number" step="any" min="0" name="lines[{{ $i }}][casting_weight_kg]" class="needs-weight" value="{{ old("lines.$i.casting_weight_kg") }}" placeholder="known" aria-label="Casting weight" style="max-width:130px"></td>
            </tr>
        @endfor
        </tbody>
    </table></div>
    </div>
    <p class="muted small">Iron grade and casting weight are only asked for when the product does not have them yet. They are saved on the product.</p>
    <div class="actions"><button class="btn btn-primary" type="submit">Enter order</button></div>
</form>
@endsection
