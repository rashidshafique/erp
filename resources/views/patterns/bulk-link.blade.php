@extends('layouts.app')

@section('title', 'Link patterns to products')
@section('breadcrumb', 'Patterns')

@section('content')
<div class="page-heading">
    <div>
        <h1>Link patterns to products</h1>
        <p>One row per pattern with no cavity linked to a product. Choose the products it makes and how many cavities each has; + Add product puts another product in the cavities after. Rows left without a product are skipped.</p>
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
                    @foreach ($rows as $i => $row)
                        @php($lines = old("rows.$i.products", $row['lines']))
                        <tbody data-bulk-row data-row="{{ $i }}" data-next="{{ count($lines) ? max(array_keys($lines)) + 1 : 1 }}">
                            @foreach ($lines as $j => $line)
                                <tr data-line>
                                    @if ($loop->first)
                                        <td rowspan="{{ count($lines) + 1 }}" data-pattern-cell style="vertical-align:top">
                                            <select name="rows[{{ $i }}][pattern_id]" data-search-select aria-label="Pattern">
                                                <option value="">Skip</option>
                                                @foreach ($patterns as $p)
                                                    <option value="{{ $p->id }}" data-sub="{{ $p->code }}" data-type="{{ \App\Models\Pattern::typeName($p->pattern_type, true) }}" @selected(old("rows.$i.pattern_id", $row['pattern']->id) === $p->id)>{{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="muted small" data-pattern-type>{{ \App\Models\Pattern::typeName($row['pattern']->pattern_type, true) }}</div>
                                            @error("rows.$i.pattern_id")<div class="error-text">{{ $message }}</div>@enderror
                                        </td>
                                    @endif
                                    <td>
                                        <select name="rows[{{ $i }}][products][{{ $j }}][product_id]" data-search-select data-product data-value="{{ $line['product_id'] ?? '' }}" aria-label="Product">
                                            <option value="">Choose a product…</option>
                                        </select>
                                        @if ($loop->first && $row['alternatives'] && ! old("rows.$i.products"))<div class="muted small">{{ $row['alternatives'] }} products share this name; pick one.</div>@endif
                                        @error("rows.$i.products.$j.product_id")<div class="error-text">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="number" name="rows[{{ $i }}][products][{{ $j }}][cavities]" min="1" max="200" step="1" value="{{ $line['cavities'] ?? 1 }}" aria-label="Cavities" style="width:90px">
                                        @error("rows.$i.products.$j.cavities")<div class="error-text">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px;align-items:center">
                                            <select name="rows[{{ $i }}][products][{{ $j }}][product_revision_id]" data-revision data-value="{{ $line['product_revision_id'] ?? '' }}" aria-label="Product revision"></select>
                                            <button class="btn btn-small" type="button" data-remove-line title="Remove this product" aria-label="Remove this product" @if ($loop->first) hidden @endif>×</button>
                                        </div>
                                        @error("rows.$i.products.$j.product_revision_id")<div class="error-text">{{ $message }}</div>@enderror
                                    </td>
                                </tr>
                            @endforeach
                            <tr data-add-row>
                                <td colspan="3"><button class="btn btn-small" type="button" data-add-line>+ Add product</button> <span class="muted small">Another product made on this pattern, in the cavities after the ones above.</span></td>
                            </tr>
                        </tbody>
                    @endforeach
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
            function fillRevisions(line, keep) {
                var product = line.querySelector('[data-product]').value, select = line.querySelector('[data-revision]'), choice = revisions[product];
                select.innerHTML = '';
                if (!product) { select.add(new Option('Choose a product first', '')); select.disabled = true; return; }
                select.disabled = false;
                if (!choice || !choice.list.length) { select.add(new Option('First revision (Rev 0), created', '')); return; }
                choice.list.forEach(function (r) { var o = new Option(r.label, r.id); o.disabled = r.obsolete; select.add(o); });
                select.value = keep || choice.preselected || '';
            }
            function setUp(line, productId, revisionId) {
                var product = line.querySelector('[data-product]');
                product.innerHTML = products;
                product.value = productId || '';
                fillRevisions(line, revisionId);
                product.addEventListener('change', function () { fillRevisions(line, null); });
                line.querySelector('[data-remove-line]').addEventListener('click', function () {
                    var group = line.parentNode;
                    line.remove();
                    group.querySelector('[data-pattern-cell]').rowSpan = group.querySelectorAll('tr').length;
                });
            }
            document.querySelectorAll('[data-bulk-row]').forEach(function (group) {
                var pattern = group.querySelector('select[name$="[pattern_id]"]');
                function showType() { var o = pattern.options[pattern.selectedIndex]; group.querySelector('[data-pattern-type]').textContent = o && o.dataset.type || ''; }
                showType();
                pattern.addEventListener('change', showType);
                group.querySelectorAll('[data-line]').forEach(function (line) {
                    setUp(line, line.querySelector('[data-product]').dataset.value, line.querySelector('[data-revision]').dataset.value);
                });
                group.querySelector('[data-add-line]').addEventListener('click', function () {
                    var first = group.querySelector('[data-line]'), line = first.cloneNode(true), j = group.dataset.next++;
                    var cell = line.querySelector('[data-pattern-cell]');
                    if (cell) { cell.remove(); }
                    line.querySelectorAll('.error-text, .muted').forEach(function (el) { el.remove(); });
                    line.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/\[products\]\[\d+\]/, '[products][' + j + ']'); });
                    line.querySelector('input[type=number]').value = 1;
                    line.querySelector('[data-remove-line]').hidden = false;
                    group.querySelector('[data-add-row]').before(line);
                    group.querySelector('[data-pattern-cell]').rowSpan = group.querySelectorAll('tr').length;
                    setUp(line, '', '');
                    line.querySelector('[data-product]').focus();
                });
            });
        })();
        </script>
    @endif
</div>
@endsection
