{{--
    Charge and deduction boxes after tax, the net, and the voucher they post (design 19o). Sits inside a form whose Alpine data has
    `subtotal`, `tax` and `money()`. Expects $document ('invoice' or 'credit_note') and $adjustments (saved boxes).
--}}
@php
    $credit = $document === 'credit_note';
    $accounts = \App\Domains\Finance\Services\ChargeBoxes::accounts();
    $saved = collect($adjustments ?? []);
    $boxes = [];
    foreach (['charge' => \App\Domains\Finance\Services\ChargeBoxes::charges(), 'deduction' => \App\Domains\Finance\Services\ChargeBoxes::deductions()] as $kind => $count) {
        $mine = $saved->where('kind', $kind)->values();
        for ($i = 0; $i < max($count, $mine->count()); $i++) {
            $boxes[] = ['kind' => $kind, 'label' => ucfirst($kind).' '.($i + 1), 'account_id' => $mine[$i]['account_id'] ?? '', 'amount' => isset($mine[$i]) ? (float) $mine[$i]['amount'] : ''];
        }
    }
    $roles = [];
    if (\App\Domains\Finance\Services\ChargeBoxes::ledgerOn()) {
        foreach (['receivables', $credit ? 'sales_returns' : 'sales', 'output_tax'] as $role) {
            try {
                $a = \App\Domains\Ledger\Services\LedgerSettings::account($role);
                $roles[$role] = ['code' => $a->code, 'name' => $a->name];
            } catch (\InvalidArgumentException) {
                $roles[$role] = ['code' => '', 'name' => $role];
            }
        }
    }
    $names = $accounts->mapWithKeys(fn ($a) => [$a->id => ['code' => $a->code, 'name' => $a->name]])->all();
@endphp
@if (\App\Domains\Finance\Services\ChargeBoxes::showsOn($document))
<div class="panel" x-data="{
        boxes: @js($boxes), names: @js($names), roles: @js($roles), credit: @js($credit),
        amt(b) { return Math.round((Number(b.amount) || 0) * 100) / 100; },
        get adj() { return this.boxes.reduce((n, b) => n + (b.account_id ? (b.kind === 'deduction' ? -this.amt(b) : this.amt(b)) : 0), 0); },
        get gross() { return Math.round((subtotal + tax) * 100) / 100; },
        get net() { return Math.round((this.gross + this.adj) * 100) / 100; },
        get preview() {
            const dr = this.credit ? 'credit' : 'debit', cr = this.credit ? 'debit' : 'credit', rows = [];
            const row = (a, side, v) => { if (v > 0) rows.push({ code: a.code, name: a.name, debit: side === 'debit' ? v : 0, credit: side === 'credit' ? v : 0 }); };
            const rec = this.credit ? 'receivables' : 'receivables', income = this.credit ? 'sales_returns' : 'sales';
            row(this.roles[rec], dr, this.net);
            this.boxes.filter(b => b.kind === 'deduction' && b.account_id).forEach(b => row(this.names[b.account_id], dr, this.amt(b)));
            row(this.roles[income], cr, Math.round(subtotal * 100) / 100);
            row(this.roles.output_tax, cr, Math.round(tax * 100) / 100);
            this.boxes.filter(b => b.kind === 'charge' && b.account_id).forEach(b => row(this.names[b.account_id], cr, this.amt(b)));
            return rows;
        },
    }" style="display:grid;grid-template-columns:minmax(0,1fr) 380px;font-size:13px;padding:0;overflow:hidden">
    <div style="padding:20px 28px;display:flex;flex-direction:column;gap:16px;min-width:0">
        <div style="display:flex;flex-direction:column;align-self:flex-end;width:620px;max-width:100%">
            <div style="display:grid;grid-template-columns:minmax(0,1fr) 150px;gap:12px;padding:6px 0"><span style="text-align:right;color:#6b6e73">Sub total</span><span style="text-align:right" x-text="money(subtotal)"></span></div>
            <div style="display:grid;grid-template-columns:minmax(0,1fr) 150px;gap:12px;padding:6px 0"><span style="text-align:right;color:#6b6e73">Sales tax</span><span style="text-align:right" x-text="money(tax)"></span></div>
            <div style="display:grid;grid-template-columns:minmax(0,1fr) 150px;gap:12px;padding:6px 0"><span style="text-align:right;color:#6b6e73">Total</span><span style="text-align:right" x-text="money(gross)"></span></div>
            <div style="margin:8px 0;padding:10px 12px;border:1px solid #e6e7e9;border-radius:8px;display:flex;flex-direction:column;gap:2px">
                <template x-for="(b, i) in boxes" :key="i">
                    <div style="display:grid;grid-template-columns:100px minmax(0,1fr) 150px;gap:12px;align-items:center;padding:4px 0">
                        <span style="text-align:right;color:#6b6e73" x-text="b.label"></span>
                        <span><input type="hidden" :name="`adjustments[${i}][kind]`" :value="b.kind">
                            <select :name="`adjustments[${i}][account_id]`" x-model="b.account_id" :aria-label="b.label" style="width:100%"><option value="">—</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }}  {{ $a->name }}</option>@endforeach</select></span>
                        <input type="number" step="0.01" min="0" :name="`adjustments[${i}][amount]`" x-model="b.amount" :aria-label="b.label + ' amount'" style="text-align:right;width:100%" :style="b.kind === 'deduction' && amt(b) ? 'color:#b3352c' : ''">
                    </div>
                </template>
            </div>
            <div style="display:grid;grid-template-columns:minmax(0,1fr) 150px;gap:12px;font-weight:600;font-size:15px;border-top:2px solid #1c1d1f;padding-top:10px;margin-top:4px"><span style="text-align:right">Net</span><span style="text-align:right" x-text="'{{ trim(\App\Support\Formats::withCurrency('')) }} ' + money(net)"></span></div>
        </div>
        <div style="font-size:12px;color:#6b6e73;text-wrap:pretty">Boxes appear because the settings ask for {{ \App\Domains\Finance\Services\ChargeBoxes::charges() }} {{ \Illuminate\Support\Str::plural('charge', \App\Domains\Finance\Services\ChargeBoxes::charges()) }} and {{ \App\Domains\Finance\Services\ChargeBoxes::deductions() }} {{ \Illuminate\Support\Str::plural('deduction', \App\Domains\Finance\Services\ChargeBoxes::deductions()) }}@if (Route::has('ledger.settings.sales-documents')) (<a href="{{ route('ledger.settings.sales-documents') }}">settings</a>)@endif. The pickers list only Deduction and charge accounts. Empty boxes are left off the printed {{ $credit ? 'credit note' : 'invoice' }}.</div>
    </div>
    <div style="border-left:1px solid #e6e7e9;padding:20px 24px;background:#f8f8f9;display:flex;flex-direction:column;gap:12px">
        <div style="font-size:12px;font-weight:600;color:#6b6e73;text-transform:uppercase;letter-spacing:.06em">Posting preview</div>
        <div style="display:grid;grid-template-columns:80px minmax(0,1fr) 90px 90px;gap:8px;font-size:11.5px;color:#6b6e73;padding-bottom:4px;border-bottom:1px solid #e6e7e9"><span>Code</span><span>Account</span><span style="text-align:right">Debit</span><span style="text-align:right">Credit</span></div>
        <template x-for="(r, i) in preview" :key="i"><div style="display:grid;grid-template-columns:80px minmax(0,1fr) 90px 90px;gap:8px;padding:6px 0;border-bottom:1px solid #e6e7e9;font-size:12.5px"><code style="color:#6b6e73" x-text="r.code"></code><span x-text="r.name"></span><span style="text-align:right" x-text="r.debit ? money(r.debit) : ''"></span><span style="text-align:right" x-text="r.credit ? money(r.credit) : ''"></span></div></template>
        <div style="display:grid;grid-template-columns:80px minmax(0,1fr) 90px 90px;gap:8px;padding:6px 0;font-size:12.5px;font-weight:600"><span></span><span>Total</span><span style="text-align:right" x-text="money(preview.reduce((n, r) => n + r.debit, 0))"></span><span style="text-align:right" x-text="money(preview.reduce((n, r) => n + r.credit, 0))"></span></div>
        <div style="font-size:12px;color:#6b6e73">Tax is final once the draft is saved; withheld tax is settled by the customer's certificate.</div>
    </div>
</div>
@endif
