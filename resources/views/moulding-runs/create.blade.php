@extends('layouts.app')

@section('title', 'Record moulding run')
@section('breadcrumb', 'Moulding runs / New run')

@section('content')

<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('production_order_id');
    const summary = document.getElementById('production-order-summary');

    if (!select || !summary) return;

    const orders = {
        @foreach ($productionOrders as $order)
            @json($order->id): {
                number: @json($order->production_order_number),
                status: @json($order->status),
                code: @json($order->product?->code ?? '—'),
                product: @json($order->product?->name ?? 'Product unavailable'),
                revision: @json($order->productRevision?->revision ?? '—'),
                qty: @json(number_format((float) $order->planned_qty, 3) . ' ' . $order->uom)
            },
        @endforeach
    };

    function updateSummary() {
        const order = orders[select.value];

        if (!order) {
            summary.style.display = 'none';
            return;
        }

        document.getElementById('po-summary-number').textContent = order.number;
        document.getElementById('po-summary-status').textContent = order.status;
        document.getElementById('po-summary-product').textContent =
            order.code + ' — ' + order.product;
        document.getElementById('po-summary-revision').textContent = order.revision;
        document.getElementById('po-summary-qty').textContent = order.qty;
        summary.style.display = 'block';
    }

    select.addEventListener('change', updateSummary);
    updateSummary();
});
</script>
    <div class="page-heading">
        <div>
            <h1>Record moulding run</h1>
            <p>Record a production activity against an existing production order.</p>
        </div>
        <a href="{{ route('moulding-runs.index') }}" class="btn">Back to runs</a>
    </div>

    <form method="POST" action="{{ route('moulding-runs.store') }}">
        @csrf

        <section class="panel">
            <div class="panel-header">
                <h2>Production and tooling</h2>
            </div>
            <div class="panel-body">
                <div class="form-grid" data-progressive="3">
                    <div class="field field-full">
                        <label for="production_order_id">
                            Production order <span class="required">*</span>
                        </label>
                        <select id="production_order_id" name="production_order_id" required>
                            <option value="">Select production order</option>
                            @foreach ($productionOrders as $order)
                                <option value="{{ $order->id }}"
                                    @selected((string) $selectedProductionOrderId === (string) $order->id)>
                                    {{ $order->production_order_number }}
                                    — {{ $order->product?->name ?? 'Product not set' }}
                                    ({{ $order->status }})
                                </option>
                            @endforeach
                        </select>
                        <div class="help">Only open production orders are listed.</div>

                        <div id="production-order-summary"
                             class="panel"
                             style="margin-top:12px; padding:14px; display:none">
                            <strong id="po-summary-number"></strong>
                            <span id="po-summary-status" class="status" style="margin-left:8px"></span>
                            <div style="margin-top:8px">
                                <span id="po-summary-product"></span>
                            </div>
                            <div class="muted" style="margin-top:4px">
                                Revision: <span id="po-summary-revision">—</span>
                                &nbsp; | &nbsp; Planned order quantity:
                                <strong id="po-summary-qty">—</strong>
                            </div>
                        </div>
                        @error('production_order_id')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="pattern_revision_id">Pattern revision</label>
                        <select id="pattern_revision_id" name="pattern_revision_id">
                            <option value="">Not specified</option>
                            @foreach ($patternRevisions as $revision)
                                <option value="{{ $revision->id }}"
                                    @selected(old('pattern_revision_id') === $revision->id)>
                                    {{ $revision->pattern?->name ?? 'Pattern' }}
                                    — Rev {{ $revision->revision }}
                                </option>
                            @endforeach
                        </select>
                        @error('pattern_revision_id')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="mould_configuration_id">Mould configuration</label>
                        <select id="mould_configuration_id" name="mould_configuration_id">
                            <option value="">Not specified</option>
                            @foreach ($mouldConfigurations as $configuration)
                                <option value="{{ $configuration->id }}"
                                    @selected(old('mould_configuration_id') === $configuration->id)>
                                    {{ $configuration->name }} ({{ $configuration->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('mould_configuration_id')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Run details</h2>
            </div>
            <div class="panel-body">
                <div class="form-grid">
                    <div class="field">
                        <label for="run_date">Run date <span class="required">*</span></label>
                        <input id="run_date" type="date" name="run_date"
                               value="{{ old('run_date', $defaults['run_date']) }}" required>
                        @error('run_date')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="shift">Shift</label>
                        <select id="shift" name="shift">
                            <option value="">Select shift</option>
                            @foreach (['Morning', 'Evening', 'Night', 'General'] as $shift)
                                <option value="{{ $shift }}" @selected(old('shift', $defaults['shift']) === $shift)>
                                    {{ $shift }}
                                </option>
                            @endforeach
                        </select>
                        @error('shift')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="started_at">Started at</label>
                        <input id="started_at" type="datetime-local" name="started_at"
                               value="{{ old('started_at') }}">
                        @error('started_at')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="completed_at">Completed at</label>
                        <input id="completed_at" type="datetime-local" name="completed_at"
                               value="{{ old('completed_at') }}">
                        @error('completed_at')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="moulding_method">Moulding method</label>
                        <select id="moulding_method" name="moulding_method">
                            <option value="">Select method</option>
                            @foreach (['Manual', 'Semi-automatic', 'Automatic'] as $method)
                                <option value="{{ $method }}" @selected(old('moulding_method', $defaults['moulding_method']) === $method)>
                                    {{ $method }}
                                </option>
                            @endforeach
                        </select>
                        @error('moulding_method')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="sand_system">Sand system</label>
                        <select id="sand_system" name="sand_system">
                            <option value="">Select sand system</option>
                            @foreach (['Greensand', 'Alphaset', 'Resin-coated sand', 'Sheera', 'Other'] as $system)
                                <option value="{{ $system }}" @selected(old('sand_system', $defaults['sand_system']) === $system)>
                                    {{ $system }}
                                </option>
                            @endforeach
                        </select>
                        @error('sand_system')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="operator_name">Operator / moulder</label>
                        <input id="operator_name" name="operator_name"
                               value="{{ old('operator_name') }}" maxlength="150">
                        @error('operator_name')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="team_name">Team</label>
                        <input id="team_name" name="team_name"
                               value="{{ old('team_name') }}" maxlength="150">
                        @error('team_name')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Output and downtime</h2>
            </div>
            <div class="panel-body">
                <div class="form-grid">
                    <div class="field">
                        <label for="planned_mould_qty">Planned moulds</label>
                        <input id="planned_mould_qty" type="number" min="0"
                               name="planned_mould_qty" value="{{ old('planned_mould_qty', $defaults['planned_mould_qty']) }}">
                        @error('planned_mould_qty')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="moulds_made_qty">Total moulds made <span class="required">*</span></label>
                        <input id="moulds_made_qty" type="number" min="0"
                               name="moulds_made_qty" value="{{ old('moulds_made_qty', $defaults['moulds_made_qty']) }}" required>
                        @error('moulds_made_qty')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="accepted_mould_qty">Accepted moulds <span class="required">*</span></label>
                        <input id="accepted_mould_qty" type="number" min="0"
                               name="accepted_mould_qty" value="{{ old('accepted_mould_qty', $defaults['accepted_mould_qty']) }}" required>
                        @error('accepted_mould_qty')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="damaged_mould_qty">Damaged moulds <span class="required">*</span></label>
                        <input id="damaged_mould_qty" type="number" min="0"
                               name="damaged_mould_qty" value="{{ old('damaged_mould_qty', $defaults['damaged_mould_qty']) }}" required>
                        @error('damaged_mould_qty')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="downtime_minutes">Downtime (minutes)</label>
                        <input id="downtime_minutes" type="number" min="0"
                               name="downtime_minutes" value="{{ old('downtime_minutes', $defaults['downtime_minutes']) }}">
                        @error('downtime_minutes')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="status">Run status <span class="required">*</span></label>
                        <select id="status" name="status" required>
                            @foreach (['IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED'] as $status)
                                <option value="{{ $status }}"
                                    @selected(old('status', $defaults['status']) === $status)>
                                    {{ str_replace('_', ' ', $status) }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field field-full">
                        <label for="downtime_reason">Downtime reason</label>
                        <textarea id="downtime_reason" name="downtime_reason"
                                  maxlength="2000">{{ old('downtime_reason') }}</textarea>
                        @error('downtime_reason')<div class="error-text">{{ $message }}</div>@enderror
                    </div>

                    <div class="field field-full">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes"
                                  maxlength="5000">{{ old('notes') }}</textarea>
                        @error('notes')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </section>

        <div class="actions" style="justify-content:flex-end;margin-bottom:25px">
            <a href="{{ route('moulding-runs.index') }}" class="btn">Cancel</a>
            <button type="submit" class="btn btn-primary">Save moulding run</button>
        </div>

        <section class="panel" id="mould-yield-panel" style="margin-top:16px">
            <div class="panel-header"><h2>Live mould yield</h2></div>
            <div class="panel-body">
                <div class="form-grid">
                    <div><div class="muted">Accepted yield</div><strong id="mould-yield">—</strong></div>
                    <div><div class="muted">Unaccounted moulds</div><strong id="mould-unaccounted">0</strong></div>
                    <div><div class="muted">Accepted + damaged</div><strong id="mould-accounted">0</strong></div>
                </div>
                <p id="mould-validation" role="status" aria-live="polite" style="margin-top:12px"></p>
            </div>
        </section>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form[action="{{ route('moulding-runs.store') }}"]');
            if (!form) return;

            const field = name => form.querySelector('[name="' + name + '"]');
            const made = field('moulds_made_qty');
            const accepted = field('accepted_mould_qty');
            const damaged = field('damaged_mould_qty');
            const yieldOut = document.getElementById('mould-yield');
            const unaccountedOut = document.getElementById('mould-unaccounted');
            const accountedOut = document.getElementById('mould-accounted');
            const message = document.getElementById('mould-validation');

            function update() {
                const numericValue = input => {
                    const raw = input.value.trim();
                    if (raw === '') return 0;
                    const parsed = Number(raw);
                    return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
                };
                const m = numericValue(made);
                const a = numericValue(accepted);
                const d = numericValue(damaged);
                const accounted = a + d;
                const valid = accounted <= m;

                yieldOut.textContent = m > 0 ? (a / m * 100).toFixed(1) + '%' : '—';
                unaccountedOut.textContent = Math.max(0, m - accounted);
                accountedOut.textContent = accounted;
                message.textContent = valid
                    ? (m === 0 ? 'Enter output quantities to calculate yield.' :
                       'Quantities valid. Unaccounted moulds have not been classified as accepted or damaged.')
                    : 'Error: accepted moulds plus damaged moulds exceed total moulds made.';
                message.style.color = valid ? '' : '#b42318';

                [accepted, damaged].forEach(input => {
                    input.setCustomValidity(valid ? '' :
                        'Accepted moulds plus damaged moulds cannot exceed total moulds made.');
                });
                return valid;
            }

            [made, accepted, damaged].forEach(input => input.addEventListener('input', update));
            form.addEventListener('submit', function (event) {
                if (!update()) {
                    event.preventDefault();
                    message.scrollIntoView({behavior: 'smooth', block: 'center'});
                }
            });
            update();
        });
        </script>

    </form>
@endsection