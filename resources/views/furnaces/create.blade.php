
@extends('layouts.app')

@section('title', 'Add Furnace')

@section('content')
<div class="page-heading">
    <div>
        <h1>Add furnace</h1>
        <p>Register a melting unit and its rated capacity.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('furnaces.index') }}">Back to furnaces</a>
    </div>
</div>


<div class="panel">
    <div class="panel-header"><h2>Furnace details</h2></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('furnaces.store') }}">
            @csrf

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:18px">
                <div>
                    <label for="code">Furnace code *</label>
                    <input id="code" name="code" required maxlength="40"
                           value="{{ old('code') }}" placeholder="e.g. CUP-01"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>
                <div>
                    <label for="name">Furnace name *</label>
                    <input id="name" name="name" required maxlength="255"
                           value="{{ old('name') }}" placeholder="e.g. Main cupola"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>
                <div>
                    <label for="furnace_type">Type *</label>
                    <select id="furnace_type" name="furnace_type" required
                            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                        @foreach(['CUPOLA' => 'Cupola', 'INDUCTION' => 'Induction', 'OTHER' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('furnace_type', 'CUPOLA') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status">Status *</label>
                    <select id="status" name="status" required
                            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                        @foreach(['ACTIVE' => 'Active', 'MAINTENANCE' => 'Maintenance', 'INACTIVE' => 'Inactive'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'ACTIVE') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rated_capacity_kg">Rated capacity (kg)</label>
                    <input id="rated_capacity_kg" name="rated_capacity_kg" type="number"
                           min="0.001" step="any" value="{{ old('rated_capacity_kg') }}"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>
                <div>
                    <label for="nominal_melting_rate_kg_per_hour">Nominal melting rate (kg/h)</label>
                    <input id="nominal_melting_rate_kg_per_hour" name="nominal_melting_rate_kg_per_hour"
                           type="number" min="0.001" step="any"
                           value="{{ old('nominal_melting_rate_kg_per_hour') }}"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>
                <div>
                    <label for="rated_power_kw">Rated power (kW)</label>
                    <input id="rated_power_kw" name="rated_power_kw" type="number"
                           min="0.001" step="any" value="{{ old('rated_power_kw') }}"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>
                <div>
                    <label for="equipment_id">Linked equipment</label>
                    <select id="equipment_id" name="equipment_id"
                            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                        <option value="">No equipment link</option>
                        @foreach($equipment as $item)
                            <option value="{{ $item->id }}" @selected(old('equipment_id') === $item->id)>
                                {{ $item->code }} — {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="margin-top:20px">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="10000"
                          style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">{{ old('description') }}</textarea>
            </div>
            <div style="margin-top:18px;margin-bottom:24px">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3" maxlength="10000"
                          style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">{{ old('notes') }}</textarea>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Save furnace</button>
                <a class="btn" href="{{ route('furnaces.index') }}">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
