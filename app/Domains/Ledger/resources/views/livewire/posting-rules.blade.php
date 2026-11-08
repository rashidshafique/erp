<?php

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Finance\Models\WithholdingCertificate;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\DayBook;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Domains\Ledger\Services\PostingRules;
use App\Models\AppSetting;
use Livewire\Component;

/**
 * Posting rules (design 19g): which account each document posts to, one row per document. Picking a row opens its accounts beside
 * the table with a live preview of its latest document, posted the way the rules now stand. Save applies to documents posted from
 * today; vouchers already posted are not re-posted. Documents the ledger does not post yet are listed so the gap is visible.
 */
new class extends Component
{
    /** @var array<string, array{name: string, when: string, roles: list<string>, model: ?class-string, debit: string, credit: string}> */
    private const DOCS = [
        'invoice' => ['name' => 'Sales invoice', 'when' => 'Issued', 'roles' => ['receivables', 'sales', 'output_tax'], 'model' => Invoice::class, 'debit' => 'receivables (gross)', 'credit' => 'sales · output_tax'],
        'credit_note' => ['name' => 'Credit note', 'when' => 'Issued', 'roles' => ['sales_returns', 'output_tax', 'receivables'], 'model' => Invoice::class, 'debit' => 'sales_returns · output_tax (reverse of the invoice lines)', 'credit' => 'receivables'],
        'receipt' => ['name' => 'Customer receipt', 'when' => 'Saved', 'roles' => ['bank', 'cash', 'receivables', 'customer_advances', 'cheques_received'], 'model' => Receipt::class, 'debit' => 'bank or cash picked (cheques in hand for a post-dated cheque settled when received)', 'credit' => 'receivables · customer_advances (not on an invoice)'],
        'certificate' => ['name' => 'Tax certificate', 'when' => 'Recorded', 'roles' => ['tax_withheld', 'receivables'], 'model' => WithholdingCertificate::class, 'debit' => 'tax_withheld', 'credit' => 'receivables'],
        'bill' => ['name' => 'Purchase invoice', 'when' => 'Posted', 'roles' => ['purchases', 'expense', 'input_tax', 'payables', 'withheld_from_suppliers'], 'model' => SupplierBill::class, 'debit' => 'line account, else purchases (PO lines) or expense · input_tax', 'credit' => 'payables · withheld_from_suppliers'],
        'payment' => ['name' => 'Supplier payment', 'when' => 'Saved', 'roles' => ['payables', 'supplier_advances', 'bank', 'cash', 'cheques_issued'], 'model' => SupplierPayment::class, 'debit' => 'payables · supplier_advances (not on a bill)', 'credit' => 'bank or cash picked (cheques issued for a post-dated cheque settled when given)'],
        'opening' => ['name' => 'Opening balances', 'when' => 'Locked', 'roles' => ['opening_equity'], 'model' => null, 'debit' => 'typed accounts · receivables from open invoices', 'credit' => 'payables from open bills · opening_equity (difference)'],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> Documents the ledger does not post yet: [name, when, debit, credit] as designed. */
    private const LATER = [
        ['Goods receipt', 'Received', '22-06 to 22-10 by material group', '12-01-02 Goods received not invoiced'],
        ['Heat close', 'Heat closed', '51-01-01 Metal consumed · 51-02-01 Power and gas (metered)', '22-06-01 to 22-06-03 at average cost · 12-02-01 Accrued power'],
        ['Returns and runners', 'Fettling count', '22-06-04 Returns and runners', '51-01-01 Metal consumed (at melt value)'],
        ['Lot scrapped', 'NCR closed as scrap', '51-05-01 Rejections · 22-06-04 Returns (metal value)', '51-01-01 Metal consumed'],
        ['Tooling approved', 'Development approved', '12-06-01 Customer advances: tooling', '41-02-01 Tooling income · 21-03-01 if foundry-owned'],
        ['Payroll', 'Posted', '51-03-01 Direct labour · 61-01-01 Admin salaries', '12-07-01 Salaries payable · 12-04-01 WHT'],
        ['Month-end stock valuation', 'Period close', '22-08 and 22-09 WIP and finished, from lot positions', '51-07-01 Change in WIP and finished stock'],
    ];

    public string $doc = 'invoice';

    /** @var array<string, string> role => account id */
    public array $roles = [];

    public string $toast = '';

    public function mount(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $this->roles = [];
        foreach (array_keys(LedgerSettings::ROLES) as $role) {
            try {
                $this->roles[$role] = LedgerSettings::account($role)->id;
            } catch (InvalidArgumentException) {
                $this->roles[$role] = '';
            }
        }
    }

    public function pick(string $doc): void
    {
        if (isset(self::DOCS[$doc])) {
            $this->doc = $doc;
        }
    }

    public function discard(): void
    {
        $this->load();
        $this->toast = '';
    }

    public function save(): void
    {
        $valid = LedgerAccount::postable()->pluck('id')->flip();
        foreach (LedgerSettings::ROLES as $role => [$label]) {
            $id = $this->roles[$role] ?? '';
            if ($id !== '' && isset($valid[$id])) {
                AppSetting::setValue('ledger.account.'.$role, $id, 'Ledger account for: '.$label);
            }
        }
        $this->load();
        $this->toast = 'Posting rules saved. They apply to documents posted from today.';
    }

    /** The latest document of the picked kind, for the preview. */
    private function sample(): ?\Illuminate\Database\Eloquent\Model
    {
        return match ($this->doc) {
            'invoice' => Invoice::where('status', 'ISSUED')->where('kind', '<>', 'CREDIT_NOTE')->latest('invoice_date')->latest('created_at')->first(),
            'credit_note' => Invoice::where('status', 'ISSUED')->where('kind', 'CREDIT_NOTE')->latest('invoice_date')->first(),
            'receipt' => Receipt::latest('received_on')->first(),
            'certificate' => WithholdingCertificate::where('status', 'ACTIVE')->latest('certificate_date')->first(),
            'bill' => SupplierBill::where('status', 'POSTED')->where('kind', '<>', 'CREDIT_NOTE')->latest('bill_date')->first(),
            'payment' => SupplierPayment::latest('paid_on')->first(),
            default => null,
        };
    }

    public function with(): array
    {
        $accounts = LedgerAccount::postable()->orderBy('code')->get(['id', 'code', 'name'])->keyBy('id');
        $saved = [];
        foreach (array_keys(LedgerSettings::ROLES) as $role) {
            try {
                $saved[$role] = LedgerSettings::account($role)->id;
            } catch (InvalidArgumentException) {
                $saved[$role] = '';
            }
        }
        $label = fn (string $role) => ($a = $accounts[$this->roles[$role] ?? ''] ?? null) ? $a->code.' '.$a->name : LedgerSettings::ROLES[$role][0];
        $describe = fn (string $text) => preg_replace_callback('/\b('.implode('|', array_map('preg_quote', array_keys(LedgerSettings::ROLES))).')\b/', fn ($m) => $label($m[1]), $text);

        $preview = [];
        $sample = $this->sample();
        if ($sample) {
            // the saved rules, with any account picked on screen but not saved swapped in
            $swap = [];
            foreach ($this->roles as $role => $id) {
                if (($saved[$role] ?? '') !== '' && $id !== $saved[$role] && isset($accounts[$id])) {
                    $swap[$saved[$role]] = $id;
                }
            }
            try {
                $lines = array_map(fn ($l) => ['account_id' => $swap[$l['account_id']] ?? $l['account_id']] + $l, app(PostingRules::class)->preview($sample));
                $names = DayBook::partyNames(collect($lines)->map(fn ($l) => (object) $l));
                $all = LedgerAccount::whereIn('id', array_column($lines, 'account_id'))->get()->keyBy('id');
                $preview = array_map(fn ($l) => ['code' => $all[$l['account_id']]->code ?? '', 'name' => ($all[$l['account_id']]->name ?? '').(isset($names[$l['party_type'].':'.$l['party_id']]) ? ' · '.$names[$l['party_type'].':'.$l['party_id']] : ''), 'debit' => $l['debit'], 'credit' => $l['credit']], $lines);
            } catch (InvalidArgumentException) {
                $preview = [];
            }
        }

        return [
            'docs' => collect(self::DOCS)->map(fn ($d) => $d + ['debitText' => $describe($d['debit']), 'creditText' => $describe($d['credit'])])->all(),
            'later' => self::LATER, 'accounts' => $accounts->values(), 'current' => self::DOCS[$this->doc],
            'sample' => $sample, 'preview' => $preview, 'dirty' => array_diff_assoc($this->roles, $saved) !== [],
        ];
    }
};
?>

<div>
@include('ledger::_styles')
@php
    $n = fn (float $v) => $v ? \App\Support\Formats::number($v, fmod($v, 1.0) == 0.0 ? 0 : 2) : '';
    $sampleNo = $sample ? ($sample->invoice_number ?? $sample->bill_number ?? $sample->receipt_number ?? $sample->payment_number ?? $sample->certificate_number ?? '') : '';
@endphp
<style>
.pr tr.on td { background: #f5f8fe; }
.pr tbody tr { cursor: pointer; }
.pr tr.later { cursor: default; }
.pr tr.later td { color: #9a9da3; }
.pr-side select { width: auto; max-width: 220px; padding: 6px 10px; }
.pr-grid { display: grid; grid-template-columns: minmax(0, 1fr) 400px; }
@media (max-width: 1000px) { .pr-grid { grid-template-columns: minmax(0, 1fr); } .pr-side { border-left: 0 !important; border-top: 1px solid #e6e7e9; } }
</style>
<div class="ac-card">
    <div class="ac-bar">
        <div class="ac-crumb"><span>Accounts</span><span class="sep">/</span><span>Settings</span><span class="sep">/</span><span>Posting rules</span></div>
        <div class="ac-actions"><button type="button" class="ac-btn" wire:click="discard" @disabled(! $dirty)>Discard</button><button type="button" class="ac-btn primary" wire:click="save" @disabled(! $dirty)>Save</button></div>
    </div>
    <div class="pr-grid">
        <div style="padding:16px 28px 24px;min-width:0;display:flex;flex-direction:column;gap:12px">
            @if ($toast)<div class="ac-toast" role="status"><span>{{ $toast }}</span></div>@endif
            <div class="ac-table"><table class="pr"><thead><tr><th>Document</th><th>Posts when</th><th>Debit</th><th>Credit</th></tr></thead><tbody>
                @foreach ($docs as $k => $d)
                    <tr @class(['on' => $k === $doc]) wire:click="pick('{{ $k }}')" wire:key="pr-{{ $k }}"><td style="font-weight:500">{{ $d['name'] }}</td><td class="ac-muted">{{ $d['when'] }}</td><td style="font-size:12.5px">{{ $d['debitText'] }}</td><td style="font-size:12.5px">{{ $d['creditText'] }}</td></tr>
                @endforeach
                @foreach ($later as [$name, $when, $dr, $cr])
                    <tr class="later"><td style="font-weight:500">{{ $name }}</td><td>{{ $when }} · not posted yet</td><td style="font-size:12.5px">{{ $dr }}</td><td style="font-size:12.5px">{{ $cr }}</td></tr>
                @endforeach
            </tbody></table></div>
            <div class="ac-note">Grey rows are designed but not posted yet: stock and cost of sales are kept periodically for now. Payment methods, the tax authority accounts and cost of sales are under <a href="{{ route('ledger.settings') }}">Ledger accounts</a>.</div>
        </div>
        <div class="pr-side" style="border-left:1px solid #e6e7e9;padding:20px 24px;background:#f8f8f9;display:flex;flex-direction:column;gap:16px;font-size:13px">
            <div style="font-size:16px;font-weight:600">{{ $current['name'] }}</div>
            <div style="display:flex;flex-direction:column;gap:12px">
                @foreach ($current['roles'] as $role)
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px"><label for="pr-{{ $role }}">{{ \App\Domains\Ledger\Services\LedgerSettings::ROLES[$role][0] }}</label>
                        <select id="pr-{{ $role }}" wire:model.live="roles.{{ $role }}" class="ac-chip">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} {{ $a->name }}</option>@endforeach</select></div>
                @endforeach
            </div>
            <div><div class="ac-sec">Preview{{ $sampleNo ? ' · '.$sampleNo : '' }}</div>
                @if ($preview)
                <div style="margin-top:8px;display:flex;flex-direction:column;font-size:12.5px">
                    @foreach ($preview as $p)<div style="display:grid;grid-template-columns:64px minmax(0,1fr) 84px 84px;gap:8px;padding:6px 0;border-bottom:1px solid #e6e7e9"><code style="color:#6b6e73">{{ $p['code'] }}</code><span>{{ $p['name'] }}</span><span style="text-align:right">{{ $n($p['debit']) }}</span><span style="text-align:right">{{ $n($p['credit']) }}</span></div>@endforeach
                </div>
                @else<div class="ac-muted" style="margin-top:8px;font-size:12.5px">{{ $doc === 'opening' ? 'See Opening balances for the voucher it posts.' : 'Nothing of this kind yet to preview.' }}</div>@endif
            </div>
            <div class="ac-note">Changes apply to documents posted from today. Posted vouchers are not re-posted.</div>
        </div>
    </div>
</div>
</div>
