@php $current = $pattern->revisions->firstWhere('is_current', true); @endphp
<div class="panel" id="cavity-map">
    <div class="panel-header"><h2>Cavity map: which product each cavity makes</h2></div>
    <div class="panel-body">
        <p class="muted small">
            Each cavity of a pattern revision makes one product revision, either the whole casting or one
            component of it. Once a map exists, moulds and moulding runs using this revision are checked against it.
        </p>

        @if ($errors->has('pattern_revision_id') || $errors->has('cavity') || $errors->has('cavity_number') || $errors->has('product_revision_id') || $errors->has('component_label') || $errors->has('poured_weight_kg'))
            <div class="alert alert-error">
                <ul>
                    @foreach (['pattern_revision_id', 'cavity', 'cavity_number', 'product_revision_id', 'component_label', 'poured_weight_kg'] as $f)
                        @error($f)<li>{{ $message }}</li>@enderror
                    @endforeach
                </ul>
            </div>
        @endif

        @foreach ($pattern->revisions as $revision)
            <h3 style="margin-top:22px" id="cavities-{{ $revision->id }}">{{ $revision->label() }}
                <span class="muted small">
                    ({{ $revision->cavityMap->count() }} of {{ $revision->cavities_per_mould ?? '?' }} cavities mapped)
                </span>
            </h3>
            @if ($revision->isSuperseded() && $revision->cavityMap->isNotEmpty())
                <div class="alert alert-warning">{{ \App\Http\Controllers\PatternCavityController::supersededWarning($revision) }}</div>
            @endif

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
                                    <td><strong>{{ $cav->cavity_number }}</strong>@if ($cav->needs_review) <span class="status status-on-hold" title="Copied from {{ $cav->copiedFrom?->code }}; check it still holds on this revision">Needs review</span>@endif</td>
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
                                    <td style="white-space:nowrap">
                                        @if ($cav->needs_review)<form method="POST" action="{{ route('patterns.cavities.review', [$pattern, $revision, $cav]) }}" style="display:inline">@csrf @method('PUT')<button class="btn btn-small" type="submit">Mark reviewed</button></form>@endif
                                        <form method="POST" action="{{ route('patterns.cavities.destroy', [$pattern, $revision, $cav]) }}"
                                              onsubmit="return confirm('Remove this cavity link?')" style="display:inline">
                                            @csrf @method('DELETE')
                                            @include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove'])
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        @endforeach

        @if ($pattern->revisions->isNotEmpty())
            <h3 style="margin-top:22px">Link a cavity to its product</h3>
            <form method="POST" action="{{ route('patterns.cavities.add', $pattern) }}" style="margin-top:8px">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label for="rev">Pattern revision *</label>
                        <select id="rev" name="pattern_revision_id" required>
                            @foreach ($pattern->revisions as $r)
                                <option value="{{ $r->id }}" @selected(old('pattern_revision_id', $current?->id) === $r->id)>{{ $r->label() }}</option>
                            @endforeach
                        </select>
                        <div class="help">Defaults to the current revision. A superseded one is allowed, with a warning.</div>
                    </div>
                    <div class="field">
                        <label for="cn">Cavity number *</label>
                        <input id="cn" name="cavity_number" type="number" min="1" required>
                    </div>
                    <div class="field">
                        <label for="pr">Product revision *</label>
                        <select id="pr" name="product_revision_id" required>
                            <option value="">Select product</option>
                            @foreach ($productRevisions as $pr)
                                <option value="{{ $pr->id }}">{{ $pr->product?->code }} rev {{ $pr->revision }} — {{ $pr->product?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="role">Cavity makes *</label>
                        <select id="role" name="cavity_role">
                            <option value="WHOLE">The whole casting</option>
                            <option value="PART">One component of the product</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="cl">Component name (if a part)</label>
                        <input id="cl" name="component_label" maxlength="100" placeholder="e.g. cover half">
                    </div>
                    <div class="field">
                        <label for="pp">Pieces per product</label>
                        <input id="pp" name="pieces_per_product" type="number" min="1" value="1">
                    </div>
                    <div class="field">
                        <label for="cw">Casting weight (kg)</label>
                        <input id="cw" name="casting_weight_kg" type="number" step="0.0001" min="0.0001">
                    </div>
                    <div class="field">
                        <label for="pw">Poured weight incl. gating (kg)</label>
                        <input id="pw" name="poured_weight_kg" type="number" step="0.0001" min="0.0001">
                    </div>
                    <div class="field field-full">
                        <label for="nt">Notes</label>
                        <input id="nt" name="notes" maxlength="2000">
                    </div>
                </div>
                <div class="actions" style="margin-top:10px">
                    <button class="btn btn-primary" type="submit">Link cavity to product</button>
                </div>
            </form>
        @endif
    </div>
</div>
