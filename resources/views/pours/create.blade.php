
@extends('layouts.app')

@section('title', 'Create Pour')

@section('content')
<div class="page-heading">
    <div>
        <h1>{{ $oneStep ? 'Record a pour' : 'Create pour' }}</h1>
        <p>{{ $oneStep ? 'Pick the heat and tick the moulds poured. The pour is checked, completed and its casting lots made in one step.' : 'Register a pouring operation against an existing heat.' }}</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('pours.index') }}">Back to pours</a>
    </div>
</div>


<div class="panel">
    <div class="panel-header">
        <h2>Pour details</h2>
    </div>

    <div class="panel-body">
        @if($heats->isEmpty())
            <p>No eligible heats were found.</p>
            <p class="muted">
                A heat must exist and must not be cancelled before you can
                create a pour against it.
            </p>
        @else
            <form method="POST" action="{{ $oneStep ? route('pours.one-step') : route('pours.store') }}">
                @csrf
                @if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

                <div class="field" style="margin-bottom:22px">
                    <label for="heat_id">Heat <span style="color:var(--danger)">*</span></label>
                    <select id="heat_id" name="heat_id" required
                            style="display:block;width:100%;max-width:700px;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                        <option value="">Select heat</option>
                        @foreach($heats as $heat)
                            <option value="{{ $heat->id }}"
                                @selected(old('heat_id', $defaults['heat_id']) === $heat->id)>
                                {{ $heat->heat_number }}
                                — {{ $heat->furnace?->code ?? 'No furnace code' }}
                                — {{ str_replace('_', ' ', $heat->status) }}
                                @if($heat->actual_tapped_weight_kg !== null)
                                    — {{ \App\Support\Formats::number((float) $heat->actual_tapped_weight_kg, 1) }} kg tapped
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <p class="muted small">
                        Select the heat that supplied the metal for this pour.
                    </p>
                </div>

                @if ($oneStep)
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-bottom:22px">
                    <div class="field"><label for="pour_temperature_c">Pour temperature (°C)</label><input id="pour_temperature_c" name="pour_temperature_c" type="number" step="1" min="1000" max="1700" value="{{ old('pour_temperature_c') }}"></div>
                    <div class="field"><label for="total_poured_weight_kg">Metal poured (kg)</label><input id="total_poured_weight_kg" name="total_poured_weight_kg" type="number" step="0.1" min="0" value="{{ old('total_poured_weight_kg') }}"><p class="muted small">Leave empty to share out by the moulds' casting weights.</p></div>
                    <div class="field"><label for="out_of_window_reason">Reason if outside the grade's temperature window</label><input id="out_of_window_reason" name="out_of_window_reason" maxlength="500" value="{{ old('out_of_window_reason') }}"></div>
                </div>
                <div class="field" style="margin-bottom:22px">
                    <label>Moulds poured <span style="color:var(--danger)">*</span></label>
                    @if ($waiting->isEmpty())
                        <p class="muted">No moulds are waiting to be poured. Record moulds made first.</p>
                    @else
                        <div class="table-wrap"><table>
                            <thead><tr><th><input type="checkbox" aria-label="Tick every mould" onclick="this.closest('table').querySelectorAll('[name=&quot;assembly_ids[]&quot;]').forEach(c => c.checked = this.checked)"></th><th>Mould</th><th>Pattern</th><th>Order</th><th>Product</th></tr></thead>
                            <tbody>@foreach ($waiting as $m)
                                <tr><td><input type="checkbox" name="assembly_ids[]" value="{{ $m->id }}" @checked(in_array($m->id, old('assembly_ids', []), true)) aria-label="{{ $m->assembly_number }}"></td>
                                    <td>{{ $m->assembly_number }}</td><td>{{ $m->mouldingRun?->patternRevision?->pattern?->name ?? '—' }}</td>
                                    <td>{{ $m->mouldingRun?->productionOrder?->production_order_number }}</td><td>{{ $m->mouldingRun?->productionOrder?->product?->code }}</td></tr>
                            @endforeach</tbody>
                        </table></div>
                    @endif
                </div>
                @else
                <div class="field" style="margin-bottom:22px">
                    <label for="planned_at">Planned pour date and time</label>
                    <input id="planned_at" name="planned_at" type="datetime-local"
                           value="{{ old('planned_at', $defaults['planned_at']) }}"
                           style="display:block;width:100%;max-width:400px;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>

                @endif

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-bottom:22px">
                    <div class="field">
                        <label for="operator_name">Pouring operator</label>
                        <input id="operator_name" name="operator_name"
                               value="{{ old('operator_name') }}" maxlength="100"
                               placeholder="Operator name"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>

                    <div class="field">
                        <label for="ladle_reference">Ladle reference</label>
                        <input id="ladle_reference" name="ladle_reference"
                               value="{{ old('ladle_reference') }}" maxlength="80"
                               placeholder="e.g. Ladle 1"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                </div>

                <div class="field" style="margin-bottom:24px">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="4" maxlength="5000"
                              placeholder="Pouring instructions or observations"
                              style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">{{ old('notes') }}</textarea>
                </div>

                <div class="actions">
                    <button class="btn btn-primary" type="submit">{{ $oneStep ? 'Record pour and make lots' : 'Create planned pour' }}</button>
                    <a class="btn" href="{{ route('pours.index') }}">Cancel</a>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
