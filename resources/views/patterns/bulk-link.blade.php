@extends('layouts.app')

@section('title', 'Link patterns to products')
@section('breadcrumb', 'Patterns')

@section('content')
<div class="page-heading">
    <div>
        <h1>Link patterns to products</h1>
        <p>One row per pattern with no cavity linked to a product. Choose the product it makes and how many cavities it has; every cavity is linked to that product. Rows left without a product are skipped.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('patterns.index', ['link' => 'unlinked']) }}">List them one by one</a>
        <a class="btn" href="{{ route('patterns.index') }}">Back to patterns</a>
    </div>
</div>

<div class="panel">
    @if ($rows->isEmpty())
        <div class="empty-state">
            <strong>Every pattern is linked</strong>
            <p>Each pattern's current revision has at least one cavity linked to a product.</p>
        </div>
    @else
        <form method="POST" action="{{ route('patterns.bulk-link.store') }}">
            @csrf
            <div class="panel-header">
                <h2>{{ $rows->count() }} unlinked {{ $rows->count() === 1 ? 'pattern' : 'patterns' }}</h2>
                <span class="muted small">Products with the same name as the pattern are preselected; a name like "Outlet 6pc" suggests 6 cavities.@if ($remaining) {{ $remaining }} more come up once these are linked.@endif</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Pattern</th>
                            <th>Product</th>
                            <th style="width:110px">Cavities</th>
                            <th>Product revision</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $row)
                            <tr data-bulk-row>
                                <td>
                                    <select name="rows[{{ $i }}][pattern_id]" data-search-select aria-label="Pattern">
                                        <option value="">Skip</option>
                                        @foreach ($patterns as $p)
                                            <option value="{{ $p->id }}" data-sub="{{ $p->code }}" @selected(old("rows.$i.pattern_id", $row['pattern']->id) === $p->id)>{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                    @error("rows.$i.pattern_id")<div class="error-text">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <select name="rows[{{ $i }}][product_id]" data-search-select data-product data-value="{{ old("rows.$i.product_id", $row['product_id']) }}" aria-label="Product">
                                        <option value="">Choose a product…</option>
                                    </select>
                                    @if ($row['alternatives'])<div class="muted small">{{ $row['alternatives'] }} products share this name; pick one.</div>@endif
                                    @error("rows.$i.product_id")<div class="error-text">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="number" name="rows[{{ $i }}][cavities]" min="1" max="200" step="1" value="{{ old("rows.$i.cavities", $row['cavities']) }}" aria-label="Cavities" style="width:90px">
                                    @error("rows.$i.cavities")<div class="error-text">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <select name="rows[{{ $i }}][product_revision_id]" data-revision data-value="{{ old("rows.$i.product_revision_id") }}" aria-label="Product revision"></select>
                                    @error("rows.$i.product_revision_id")<div class="error-text">{{ $message }}</div>@enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="panel-body" style="display:flex;justify-content:flex-end">
                <button class="btn btn-primary" type="submit">Link the chosen patterns</button>
            </div>
        </form>

        <template id="bulk-link-products">
            <option value="">Choose a product…</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" data-sub="{{ $product->code }}">{{ $product->name }}</option>
            @endforeach
        </template>
        @include('partials.search-select')
        <script>
        (function () {
            var products = document.getElementById('bulk-link-products').innerHTML, revisions = @json($revisions);
            function fillRevisions(row, keep) {
                var product = row.querySelector('[data-product]').value, select = row.querySelector('[data-revision]'), choice = revisions[product];
                select.innerHTML = '';
                if (!product) { select.add(new Option('Choose a product first', '')); select.disabled = true; return; }
                select.disabled = false;
                if (!choice || !choice.list.length) { select.add(new Option('First revision (Rev 0), created', '')); return; }
                choice.list.forEach(function (r) { var o = new Option(r.label, r.id); o.disabled = r.obsolete; select.add(o); });
                select.value = keep || choice.preselected || '';
            }
            document.querySelectorAll('[data-bulk-row]').forEach(function (row) {
                var product = row.querySelector('[data-product]'), revision = row.querySelector('[data-revision]');
                product.innerHTML = products;
                product.value = product.dataset.value || '';
                fillRevisions(row, revision.dataset.value);
                product.addEventListener('change', function () { fillRevisions(row, null); });
            });
        })();
        </script>
    @endif
</div>
@endsection
