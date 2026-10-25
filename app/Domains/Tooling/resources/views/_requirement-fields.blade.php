{{-- Fields of the "Core needed" dialog (partials.quick-dialog). On a product page $product is set and the core is chosen; on a core page $core is set and the product revision is chosen. --}}
@if ($part === 'required')
@if (isset($product))
    <div class="field field-full"><label for="cr-core">Core <span class="required">*</span> <a href="{{ route('cores.create') }}" class="qd-add">+ New core</a></label>
        <select id="cr-core" name="core_definition_id" required data-search-select><option value="">Choose a core…</option>
            @foreach (\App\Models\CoreDefinition::where('status', 'ACTIVE')->orderBy('code')->get() as $c)<option value="{{ $c->id }}" data-sub="{{ $c->code }}" @selected($old('core_definition_id') === $c->id)>{{ $c->name }}</option>@endforeach
        </select></div>
    <div class="field field-full"><label for="cr-revision">Product revision <span class="required">*</span></label>
        <select id="cr-revision" name="product_revision_id" required data-search-select>
            @foreach ($product->revisions->where('status', '!=', 'OBSOLETE')->sortByDesc('created_at') as $r)<option value="{{ $r->id }}" @selected($old('product_revision_id', $loop->first ? $r->id : null) === $r->id)>Rev {{ $r->revision }} · {{ $r->status }}</option>@endforeach
        </select>@if ($product->revisions->isEmpty())<div class="help">Add a revision to this product first.</div>@endif</div>
@else
    <input type="hidden" name="core_definition_id" value="{{ $core->id }}">
    <div class="field field-full"><label for="cr-revision">Product revision <span class="required">*</span></label>
        <select id="cr-revision" name="product_revision_id" required data-search-select><option value="">Choose a product…</option>
            @foreach ($productRevisions as $r)<option value="{{ $r->id }}" data-sub="{{ $r->product->code }}" @selected($old('product_revision_id') === $r->id)>{{ $r->product->name }} · Rev {{ $r->revision }}</option>@endforeach
        </select></div>
@endif
    <div class="field"><label for="cr-qty">Needed per casting <span class="required">*</span></label><input id="cr-qty" type="number" name="quantity_per_casting" min="1" max="99" required value="{{ $old('quantity_per_casting', 1) }}"></div>
@else
    <div class="field field-full"><label for="cr-notes">Notes</label><input id="cr-notes" name="notes" maxlength="255" value="{{ $old('notes') }}"></div>
@endif
