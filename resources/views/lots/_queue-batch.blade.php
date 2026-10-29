{{-- Castings between the pour and inspection, by step and product, moved in batches: some moulds or pieces, or everything waiting.
     Each row is its own form, so this sits outside the form that moves ticked lots. --}}
<datalist id="defect-names">@foreach ($defects as $name)<option value="{{ $name }}">@endforeach</datalist>
<details class="panel" open>
    <summary class="panel-header" style="cursor:pointer"><h2>Move a batch</h2><span class="muted small">Record a step for some moulds or pieces of a product, or leave the quantity empty for all of it. Taken from the oldest lots first.</span></summary>
    @error('batch')<div class="alert alert-error">{{ $message }}</div>@enderror
    <div class="table-wrap"><table><thead><tr><th>Step</th><th>Product</th><th>Waiting</th><th>Record</th></tr></thead><tbody>
    @foreach ($batches as $stepRows)
        @foreach ($stepRows as $r)
            @php $step = $r['step']; $cooling = $r['ready_at'] && $r['ready_at']->isFuture(); @endphp
            <tr>
                <td>{{ $step->name }}</td>
                <td><strong>{{ $r['product']?->code }}</strong> {{ $r['product']?->name }}<div class="muted small">{{ $r['lots']->count() }} {{ \Illuminate\Support\Str::plural('lot', $r['lots']->count()) }}@if ($r['on_hold']) · {{ $r['on_hold'] }} pieces on hold @endif</div></td>
                <td>{{ $r['pieces'] }} pcs @if ($r['moulds'] !== null)<div class="muted small">≈ {{ $r['moulds'] }} moulds</div>@endif
                    @if ($cooling)<div><span class="status status-in-progress">cooling until {{ $r['ready_at']->displayShortDateTime() }}</span></div>@endif</td>
                <td>@if ($r['pieces'] > 0)
                    <form method="POST" action="{{ route('lots.batch-stage') }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">@csrf
                        <input type="hidden" name="stage_definition_id" value="{{ $step->id }}"><input type="hidden" name="product_revision_id" value="{{ $r['product_revision_id'] }}">
                        <input type="number" name="quantity" min="1" placeholder="all" style="width:80px" aria-label="How many">
                        <select name="unit" aria-label="Unit"><option value="PIECES">pieces</option>@if ($r['moulds'] !== null)<option value="MOULDS">moulds</option>@endif</select>
                        <input type="number" name="rejected" min="0" placeholder="rejects" style="width:80px" aria-label="Rejected pieces">
                        <input name="rejection_reason" list="defect-names" maxlength="100" placeholder="why rejected" style="width:140px" aria-label="Why rejected">
                        @if ($cooling)<input name="cooling_override_reason" maxlength="300" placeholder="reason to knock out early" style="width:180px" aria-label="Reason to knock out early">@endif
                        <button class="btn btn-primary btn-small">{{ $step->name }} done</button>
                    </form>
                @endif</td>
            </tr>
        @endforeach
    @endforeach
    </tbody></table></div>
</details>
