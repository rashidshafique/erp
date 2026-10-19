
@extends('layouts.app')

@section('title', 'Create Pour')

@section('content')
<div class="page-heading">
    <div>
        <h1>Create pour</h1>
        <p>Register a pouring operation against an existing heat.</p>
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
                A heat must exist and must not be closed or cancelled before you can
                create a pour against it.
            </p>
        @else
            <form method="POST" action="{{ route('pours.store') }}">
                @csrf

                <div class="field" style="margin-bottom:22px">
                    <label for="heat_id">Heat <span style="color:var(--danger)">*</span></label>
                    <select id="heat_id" name="heat_id" required
                            style="display:block;width:100%;max-width:700px;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                        <option value="">Select heat</option>
                        @foreach($heats as $heat)
                            <option value="{{ $heat->id }}"
                                @selected(old('heat_id') === $heat->id)>
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

                <div class="field" style="margin-bottom:22px">
                    <label for="planned_at">Planned pour date and time</label>
                    <input id="planned_at" name="planned_at" type="datetime-local"
                           value="{{ old('planned_at', $defaults['planned_at']) }}"
                           style="display:block;width:100%;max-width:400px;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>

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
                    <button class="btn btn-primary" type="submit">Create planned pour</button>
                    <a class="btn" href="{{ route('pours.index') }}">Cancel</a>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
