{{-- The cavity map of one revision as a grid: the cavity count, then one editable row per cavity. A row with a product is
     linked; an empty row is not. Changing the count adds or removes rows as you type. $revision, $pattern, $productRevisions,
     $assembled (moulds already made with this layout: a count change then needs a reason). --}}
@php
    $mine = old('_map') === $revision->id;
    $mapped = $revision->cavityMap->keyBy('cavity_number');
    $count = (int) ($mine ? old('cavities_per_mould') : max((int) $revision->cavities_per_mould, (int) $mapped->keys()->max()));
    $rowVal = fn (int $n, string $field) => $mine ? old("rows.{$n}.{$field}") : $mapped->get($n)?->{$field};
    $err = fn (int $n, string $field) => $mine ? $errors->first("rows.{$n}.{$field}") : null;
    // Products offered with more than one revision: the closed dropdown shows only the name, so those need the revision on it too.
    $manyRevisions = $productRevisions->groupBy('product_id')->filter(fn ($g) => $g->count() > 1)->keys()->flip();
@endphp
<form method="POST" action="{{ $action ?? route('patterns.cavities.map', [$pattern, $revision]) }}" class="cavity-grid" data-cavity-grid>
    @csrf @method('PUT')<input type="hidden" name="_map" value="{{ $revision->id }}">
    <div class="cg-head">
        @if ($revision->exists)
            {{-- The layout form above has the one "Cavities on the layout" box; the grid follows it and saves the same figure. --}}
            <input type="hidden" name="cavities_per_mould" value="{{ $count ?: '' }}" data-cavity-count data-cavity-source="cv-{{ $revision->id }}">
            @if ($mine)@error('cavities_per_mould')<div class="error-text">{{ $message }}</div>@enderror @endif
            <p class="muted small">One row per cavity, following the cavities on the layout above. Choose the product each cavity makes; leave a row empty if the cavity is not used.</p>
        @else
            {{-- A pattern with no revision has no layout form yet, so the count is asked here and its first revision is recorded with the map. --}}
            <div class="field"><label for="cg-count-{{ $revision->id }}">Cavities on the layout</label>
                <input id="cg-count-{{ $revision->id }}" type="number" name="cavities_per_mould" min="1" max="200" value="{{ $count ?: '' }}" required data-cavity-count placeholder="e.g. 4">
                @if ($mine)@error('cavities_per_mould')<div class="error-text">{{ $message }}</div>@enderror @endif</div>
            <p class="muted small">One row per cavity. Choose the product each cavity makes; leave a row empty if the cavity is not used. Rows follow the count.</p>
        @endif
    </div>
    <div class="cg-tools">
        <button type="button" class="btn btn-small" data-cavity-repeat title="Puts the product chosen on a cavity onto every cavity that has none. Works when one product is chosen.">Repeat the product on every cavity</button>
        <span class="muted small" data-cavity-repeat-msg role="status" aria-live="polite"></span>
    </div>
    <div class="table-wrap">
        <table class="cg-table">
            <thead><tr><th>Cavity</th><th title="Number or code engraved or embossed on the cavity">Mark</th><th>Product</th><th>Makes</th><th>Component</th><th>Pieces / product</th><th>Casting kg</th><th>Poured kg, own gating</th><th>Notes</th></tr></thead>
            <tbody data-cavity-rows>
                @for ($n = 1; $n <= $count; $n++)
                    @include('patterns._cavity-grid-row', ['n' => $n, 'value' => fn ($field) => $rowVal($n, $field), 'error' => fn ($field) => $err($n, $field), 'copied' => ! $mine && $mapped->get($n)?->copied_from_revision_id ? $mapped->get($n)->copiedFrom?->code : null])
                @endfor
            </tbody>
        </table>
    </div>
    <template data-cavity-template>
        @include('patterns._cavity-grid-row', ['n' => '__n__', 'value' => fn ($field) => null, 'error' => fn ($field) => null, 'copied' => null])
    </template>
    @if ($assembled)
        <div class="field" style="margin-top:10px"><label for="cg-reason-{{ $revision->id }}">Reason, if the cavity count changes</label>
            <input id="cg-reason-{{ $revision->id }}" name="correction_reason" maxlength="500" value="{{ $mine ? old('correction_reason') : '' }}" placeholder="Moulds were assembled with this layout">
            @if ($mine)@error('correction_reason')<div class="error-text">{{ $message }}</div>@enderror @endif</div>
    @endif
    <div class="actions" style="margin-top:10px"><button class="btn btn-primary" type="submit">Save cavity map</button></div>
</form>
