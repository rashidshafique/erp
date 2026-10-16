<div class="panel" id="cavity-map">
    <div class="panel-header"><h2>Cavity map: which product each cavity makes</h2></div>
    <div class="panel-body">
        <p class="muted small">
            Each cavity of a pattern revision makes one product revision, either the whole casting or one
            component of it. Once a map exists, moulds and moulding runs using this revision are checked against it.
        </p>

        @if ($errors->has('cavity') || $errors->has('cavity_number') || $errors->has('product_revision_id') || $errors->has('component_label') || $errors->has('poured_weight_kg'))
            <div class="alert alert-error">
                <ul>
                    @foreach (['cavity', 'cavity_number', 'product_revision_id', 'component_label', 'poured_weight_kg'] as $f)
                        @error($f)<li>{{ $message }}</li>@enderror
                    @endforeach
                </ul>
            </div>
        @endif

        @foreach ($pattern->revisions as $revision)
            <h3 style="margin-top:22px">Revision {{ $revision->revision }}
                <span class="muted small">
                    ({{ $revision->cavityMap->count() }} of {{ $revision->cavities_per_mould ?? '?' }} cavities mapped)
                </span>
            </h3>

            @if ($revision->cavityMap->isEmpty())
                <div class="empty-state">No cavities mapped for this revision.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Cavity</th><th>Product</th><th>Rev</th><th>Makes</th>
                                <th>Pieces / product</th><th>Casting kg</th><th>Poured kg</th><th>Yield</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($revision->cavityMap as $cav)
                                <tr>
                                    <td><strong>{{ $cav->cavity_number }}</strong></td>
                                    <td>
                                        <a href="{{ route('products.show', $cav->productRevision->product) }}">
                                            {{ $cav->productRevision->product->code }}
                                        </a>
                                    </td>
                                    <td>{{ $cav->productRevision->revision }}</td>
                                    <td>{{ $cav->label() }}</td>
                                    <td>{{ $cav->pieces_per_product }}</td>
                                    <td>{{ $cav->casting_weight_kg !== null ? number_format((float) $cav->casting_weight_kg, 3) : '—' }}</td>
                                    <td>{{ $cav->poured_weight_kg !== null ? number_format((float) $cav->poured_weight_kg, 3) : '—' }}</td>
                                    <td>{{ $cav->yieldPercent() !== null ? $cav->yieldPercent().' %' : '—' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('patterns.cavities.destroy', [$pattern, $revision, $cav]) }}"
                                              onsubmit="return confirm('Remove this cavity link?')">
                                            @csrf @method('DELETE')
                                            <button class="btn" type="submit">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <form method="POST" action="{{ route('patterns.cavities.store', [$pattern, $revision]) }}" style="margin-top:12px">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label for="cn_{{ $revision->id }}">Cavity number *</label>
                        <input id="cn_{{ $revision->id }}" name="cavity_number" type="number" min="1" required>
                    </div>
                    <div class="field">
                        <label for="pr_{{ $revision->id }}">Product revision *</label>
                        <select id="pr_{{ $revision->id }}" name="product_revision_id" required>
                            <option value="">Select product</option>
                            @foreach ($productRevisions as $pr)
                                <option value="{{ $pr->id }}">{{ $pr->product?->code }} rev {{ $pr->revision }} — {{ $pr->product?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="role_{{ $revision->id }}">Cavity makes *</label>
                        <select id="role_{{ $revision->id }}" name="cavity_role">
                            <option value="WHOLE">The whole casting</option>
                            <option value="PART">One component of the product</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="cl_{{ $revision->id }}">Component name (if a part)</label>
                        <input id="cl_{{ $revision->id }}" name="component_label" maxlength="100" placeholder="e.g. cover half">
                    </div>
                    <div class="field">
                        <label for="pp_{{ $revision->id }}">Pieces per product</label>
                        <input id="pp_{{ $revision->id }}" name="pieces_per_product" type="number" min="1" value="1">
                    </div>
                    <div class="field">
                        <label for="cw_{{ $revision->id }}">Casting weight (kg)</label>
                        <input id="cw_{{ $revision->id }}" name="casting_weight_kg" type="number" step="0.0001" min="0.0001">
                    </div>
                    <div class="field">
                        <label for="pw_{{ $revision->id }}">Poured weight incl. gating (kg)</label>
                        <input id="pw_{{ $revision->id }}" name="poured_weight_kg" type="number" step="0.0001" min="0.0001">
                    </div>
                    <div class="field field-full">
                        <label for="nt_{{ $revision->id }}">Notes</label>
                        <input id="nt_{{ $revision->id }}" name="notes" maxlength="2000">
                    </div>
                </div>
                <div class="actions" style="margin-top:10px">
                    <button class="btn btn-primary" type="submit">Link cavity to product</button>
                </div>
            </form>
        @endforeach
    </div>
</div>
