<?php

use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Models\Voucher;
use App\Domains\Ledger\Services\Ledger;
use App\Domains\Ledger\Services\Vouchers;
use App\Http\Controllers\AttachmentController;
use App\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Journal entry (design 19b). JV, CPV, BPV, CRV and BRV are presets of one screen: a cash or bank type names the account the money
 * goes through and adds the balancing line itself. Save draft keeps the voucher and its number without touching the books; Post is
 * open only when debits equal credits, a payment takes money out and a receipt brings it in, and every line has an account and an amount.
 */
new class extends Component
{
    use WithFileUploads;

    public ?string $voucherId = null;

    public string $type = 'JV';

    public string $date = '';

    public string $counter = '';

    public string $reference = '';

    /** @var list<array{account_id: string, narration: string, debit: string, credit: string}> */
    public array $lines = [];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $file = null;

    public string $error = '';

    public function mount(?Voucher $voucher = null, string $type = 'JV'): void
    {
        if ($voucher?->exists) {
            $this->voucherId = $voucher->id;
            $this->type = $voucher->voucher_type;
            $this->date = $voucher->voucher_date->toDateString();
            $this->counter = (string) $voucher->counter_account_id;
            $this->reference = (string) $voucher->reference;
            $this->lines = array_map(fn ($l) => ['account_id' => (string) ($l['account_id'] ?? ''), 'narration' => (string) ($l['narration'] ?? ''), 'debit' => $this->plain($l['debit'] ?? ''), 'credit' => $this->plain($l['credit'] ?? '')], $voucher->lines);

            return;
        }
        $this->type = isset(Vouchers::TYPES[$type]) ? $type : 'JV';
        $this->date = today()->toDateString();
        $this->counter = $this->defaultCounter();
        $this->lines = array_fill(0, $this->type === 'JV' ? 2 : 1, $this->blank());
    }

    private function plain(mixed $v): string
    {
        return $v === null || $v === '' || (float) $v == 0 ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    /** @return array{account_id: string, narration: string, debit: string, credit: string} */
    private function blank(): array
    {
        return ['account_id' => '', 'narration' => '', 'debit' => '', 'credit' => ''];
    }

    private function defaultCounter(): string
    {
        $t = Vouchers::TYPES[$this->type];

        return $t['counter'] ? (string) (Vouchers::counterAccounts($t['cash'])->first() ?? Vouchers::counterAccounts()->first())?->id : '';
    }

    public function pickType(string $type): void
    {
        if (! isset(Vouchers::TYPES[$type]) || $type === $this->type) {
            return;
        }
        $this->type = $type;
        $this->counter = $this->defaultCounter();
        $this->error = '';
    }

    public function addLine(): void
    {
        $this->lines[] = $this->blank();
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    /** A line is a debit or a credit: typing one clears the other. */
    public function updatedLines(mixed $value, string $key): void
    {
        [$i, $field] = array_pad(explode('.', $key), 2, null);
        if (in_array($field, ['debit', 'credit'], true) && $this->num($value) != 0) {
            $this->lines[(int) $i][$field === 'debit' ? 'credit' : 'debit'] = '';
        }
    }

    private function num(mixed $v): float
    {
        return round((float) str_replace(',', '', (string) $v), 2);
    }

    /**
     * Totals, the balancing line and whether the voucher can be posted.
     *
     * @return array<string, mixed>
     */
    private function state(): array
    {
        $t = Vouchers::TYPES[$this->type];
        $sd = array_sum(array_map(fn ($l) => $this->num($l['debit']), $this->lines));
        $sc = array_sum(array_map(fn ($l) => $this->num($l['credit']), $this->lines));
        $hasCtr = $t['counter'] !== null;
        $net = round($sd - $sc, 2);
        $ctrDr = $hasCtr && $net < 0 ? -$net : 0.0;
        $ctrCr = $hasCtr && $net > 0 ? $net : 0.0;
        $tDr = round($sd + $ctrDr, 2);
        $tCr = round($sc + $ctrCr, 2);
        $diff = round($tDr - $tCr, 2);
        $wrong = ($t['side'] === 'out' && $net < 0) || ($t['side'] === 'in' && $net > 0);
        $complete = $this->lines !== [] && collect($this->lines)->every(fn ($l) => $l['account_id'] !== '' && ($this->num($l['debit']) || $this->num($l['credit'])));
        $ok = $tDr > 0 && $diff == 0.0 && ! $wrong && $complete && (! $hasCtr || $this->counter !== '');
        $money = fn (float $v) => \App\Support\Formats::number(abs($v), fmod($v, 1.0) == 0.0 ? 0 : 2);
        $msg = match (true) {
            $diff != 0.0 => 'Debits and credits differ by '.\App\Support\Formats::money(abs($diff), fmod($diff, 1.0) == 0.0 ? 0 : 2).'. Fix the lines to post.',
            $wrong => $t['side'] === 'out' ? 'A payment must take money out. Use a receipt voucher instead.' : 'A receipt must bring money in. Use a payment voucher instead.',
            ! $complete || $tDr <= 0 => 'Every line needs an account and an amount.',
            $hasCtr && $this->counter === '' => 'Pick the account the money goes through.',
            default => 'Balanced. Posting assigns '.$this->number().' and locks the voucher.',
        };

        return compact('t', 'hasCtr', 'ctrDr', 'ctrCr', 'tDr', 'tCr', 'diff', 'ok', 'msg', 'money');
    }

    private function number(): string
    {
        $draft = $this->voucherId ? Voucher::find($this->voucherId) : null;

        return $draft && $draft->voucher_type === $this->type
            ? $draft->number : Vouchers::preview($this->type, $this->date ?: today());
    }

    /** @return list<array{account_id: string, narration: ?string, debit: float, credit: float}> */
    private function cleanLines(): array
    {
        return array_map(fn ($l) => ['account_id' => $l['account_id'], 'narration' => trim($l['narration']) ?: null, 'debit' => $this->num($l['debit']), 'credit' => $this->num($l['credit'])], $this->lines);
    }

    private function checkFields(): void
    {
        $this->validate([
            'date' => ['required', 'date'], 'reference' => ['nullable', 'string', 'max:100'], 'counter' => ['nullable', 'uuid'],
            'lines' => ['array'], 'lines.*.account_id' => ['nullable', 'uuid'], 'lines.*.narration' => ['nullable', 'string', 'max:300'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'], 'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'file' => ['nullable', 'file', 'max:'.(int) config('foundry.attachments.max_kb', 20480)],
        ], ['lines.*.debit.numeric' => 'Amounts must be numbers.', 'lines.*.credit.numeric' => 'Amounts must be numbers.']);
    }

    private function saveFile(string $id): void
    {
        if ($this->file) {
            AttachmentController::saveFiles([$this->file], 'ledger_voucher', $id, 'OTHER', null);
            $this->file = null;
        }
    }

    public function saveDraft(): void
    {
        $this->checkFields();
        $voucher = DB::transaction(function () {
            $v = $this->voucherId ? Voucher::findOrFail($this->voucherId) : new Voucher(['user_id' => auth()->id()]);
            if (! $v->exists || $v->voucher_type !== $this->type) {
                $v->number = Vouchers::next($this->type, $this->date);
            }
            $v->fill(['voucher_type' => $this->type, 'voucher_date' => $this->date, 'reference' => trim($this->reference) ?: null,
                'counter_account_id' => Vouchers::TYPES[$this->type]['counter'] ? ($this->counter ?: null) : null, 'lines' => $this->cleanLines()])->save();

            return $v;
        });
        $this->saveFile($voucher->id);
        session()->flash('success', "Draft {$voucher->number} saved.");
        $this->redirectRoute('ledger.vouchers.edit', $voucher);
    }

    public function post(Ledger $ledger): void
    {
        $this->checkFields();
        $s = $this->state();
        if (! $s['ok']) {
            $this->error = $s['msg'];

            return;
        }
        $draft = $this->voucherId ? Voucher::find($this->voucherId) : null;
        $lines = array_map(fn ($l) => ['account_id' => $l['account_id'], 'debit' => $l['debit'], 'credit' => $l['credit'], 'description' => $l['narration']], $this->cleanLines());
        if ($s['hasCtr']) {
            $lines[] = ['account_id' => $this->counter, 'debit' => $s['ctrDr'], 'credit' => $s['ctrCr'], 'description' => trim($this->reference) ?: null];
        }
        $memo = collect($this->lines)->pluck('narration')->map(fn ($n) => trim($n))->filter()->first() ?: (trim($this->reference) ?: Vouchers::TYPES[$this->type]['name']);
        try {
            $journal = DB::transaction(function () use ($ledger, $draft, $lines, $memo) {
                $keep = $draft && $draft->voucher_type === $this->type;
                $journal = $ledger->post($this->date, $lines, null, $memo, voucher: [
                    'id' => $draft?->id, 'number' => $keep ? $draft->number : null, 'type' => $this->type,
                    'reference' => trim($this->reference) ?: null, 'counter_account_id' => Vouchers::TYPES[$this->type]['counter'] ? $this->counter : null,
                ]);
                $draft?->delete();

                return $journal;
            });
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->saveFile($journal->id);
        session()->flash('success', "Posted {$journal->number}.");
        $this->redirectRoute('ledger.journals.show', $journal);
    }

    public function discard(): void
    {
        if ($this->voucherId && ($v = Voucher::find($this->voucherId))) {
            $v->delete();
            session()->flash('success', "Draft {$v->number} discarded.");
        }
        $this->redirectRoute('ledger.journals.index');
    }

    public function with(): array
    {
        $s = $this->state();

        return $s + [
            'no' => $this->number(),
            'accounts' => Vouchers::lineAccounts(),
            'counters' => Vouchers::counterAccounts(),
            'counterAccount' => $this->counter ? LedgerAccount::find($this->counter) : null,
            'attached' => $this->voucherId ? Attachment::where('attachable_type', 'ledger_voucher')->where('attachable_id', $this->voucherId)->current()->get() : collect(),
            'controls' => LedgerAccount::whereNotNull('control')->where('id', '<>', \App\Domains\Ledger\Services\LedgerSettings::account('opening_equity')->id)->orderBy('code')->pluck('code')->all(),
        ];
    }
};
?>

<div>
@include('ledger::_styles')
@php $grid = 'display:grid;grid-template-columns:28px 300px minmax(0,1fr) 150px 150px 24px;gap:10px;align-items:center'; $f = fn (float $v) => $v ? $money($v) : ''; @endphp
<style>
.je-lines .h { padding: 8px 0; border-bottom: 1px solid #e6e7e9; font-size: 11.5px; color: #6b6e73; font-weight: 500; }
.je-lines .l { padding: 7px 0; border-bottom: 1px solid #eff0f2; font-size: 13px; }
.je-lines .l input, .je-lines .l select { padding: 7px 9px; }
.je-lines .ctr { padding: 9px 0; border-bottom: 1px solid #eff0f2; background: #f8f8f9; font-size: 13px; }
.je-lines .t { padding: 10px 0; border-top: 1px solid #e6e7e9; font-size: 13px; font-weight: 600; }
.je-lines .r { text-align: right; }
.je-head { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; font-size: 13px; }
.je-head input, .je-head select { padding: 7px 10px; }
.je-drop { padding: 7px 10px; border: 1px dashed #c4c7cc; border-radius: 6px; color: #6b6e73; cursor: pointer; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
@media (max-width: 1000px) { .je-head { grid-template-columns: 1fr 1fr; } .je-scroll { overflow-x: auto; } .je-lines { min-width: 860px; } }
</style>
<div class="ac-card">
    <div class="ac-bar">
        <div class="ac-crumb"><span>Accounts</span><span class="sep">/</span><a href="{{ route('ledger.journals.index') }}">Journal</a><span class="sep">/</span><span>{{ $no }}</span><span class="ac-pill">Draft</span></div>
        <div class="ac-actions">
            @if ($voucherId)<button type="button" class="ac-btn" wire:click="discard" wire:confirm="Discard this draft?">Discard</button><a class="ac-btn" href="{{ route('ledger.vouchers.print', $voucherId) }}" target="_blank">Print</a>@endif
            <button type="button" class="ac-btn" wire:click="saveDraft">Save draft</button>
            <button type="button" class="ac-btn primary" wire:click="post" @disabled(! $ok)>Post</button>
        </div>
    </div>
    <div style="padding:20px 28px;display:flex;flex-direction:column;gap:18px">
        @if (session('success'))<div class="ac-toast" role="status"><span>{{ session('success') }}</span></div>@endif
        @if ($error)<div class="ac-toast bad" role="alert"><span>{{ $error }}</span><button type="button" class="ac-x" style="font-size:14px;color:inherit" wire:click="$set('error', '')" aria-label="Dismiss">×</button></div>@endif
        @if ($errors->any())<div class="ac-toast bad" role="alert"><span>{{ $errors->first() }}</span></div>@endif
        <div style="display:flex;flex-direction:column;gap:6px"><span style="font-size:12px;color:#6b6e73">Voucher type</span>
            <div class="ac-seg" role="radiogroup" aria-label="Voucher type">@foreach (\App\Domains\Ledger\Services\Vouchers::TYPES as $k => $vt)<button type="button" role="radio" aria-checked="{{ $k === $type ? 'true' : 'false' }}" @class(['on' => $k === $type]) wire:click="pickType('{{ $k }}')"><strong style="font-weight:600">{{ $k }}</strong> {{ $vt['name'] }}</button>@endforeach</div>
        </div>
        <div class="je-head">
            <div class="ac-field"><label class="lab" for="je-date">Date</label><input id="je-date" type="date" wire:model.live="date"><span class="hint">{{ $date ? \App\Domains\Ledger\Services\Vouchers::periodLabel($date) : '' }}</span></div>
            @if ($hasCtr)
            <div class="ac-field"><label class="lab" for="je-ctr">{{ $t['counter'] }}</label><select id="je-ctr" wire:model.live="counter"><option value="">Select an account</option>@foreach ($counters as $a)<option value="{{ $a->id }}">{{ $a->code }}  {{ $a->name }}</option>@endforeach</select></div>
            @endif
            <div class="ac-field"><label class="lab" for="je-ref">{{ $t['ref'] }}</label><input id="je-ref" wire:model.blur="reference" maxlength="100"></div>
            <div class="ac-field"><span class="lab">Attachment</span>
                <label class="je-drop">{{ $file ? $file->getClientOriginalName() : ($attached->isNotEmpty() ? $attached->pluck('original_name')->implode(', ') : 'Drop or choose a file') }}<input type="file" wire:model="file" style="display:none"></label>
            </div>
        </div>
        <div class="je-scroll"><div class="je-lines" style="display:flex;flex-direction:column">
            <div class="h" style="{{ $grid }}"><span>#</span><span>Account</span><span>Narration</span><span class="r">Debit ({{ trim(\App\Support\Formats::withCurrency('')) ?: 'amount' }})</span><span class="r">Credit ({{ trim(\App\Support\Formats::withCurrency('')) ?: 'amount' }})</span><span></span></div>
            @foreach ($lines as $i => $l)
            <div class="l" style="{{ $grid }}" wire:key="line-{{ $i }}">
                <span class="ac-muted">{{ $i + 1 }}</span>
                <select wire:model.live="lines.{{ $i }}.account_id" aria-label="Account, line {{ $i + 1 }}"><option value="">Select an account</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }}  {{ $a->name }}</option>@endforeach</select>
                <input wire:model.blur="lines.{{ $i }}.narration" maxlength="300" aria-label="Narration, line {{ $i + 1 }}">
                <input wire:model.live.debounce.400ms="lines.{{ $i }}.debit" placeholder="0" inputmode="decimal" style="text-align:right" aria-label="Debit, line {{ $i + 1 }}">
                <input wire:model.live.debounce.400ms="lines.{{ $i }}.credit" placeholder="0" inputmode="decimal" style="text-align:right" aria-label="Credit, line {{ $i + 1 }}">
                <button type="button" class="ac-x" style="font-size:13px;text-align:center" wire:click="removeLine({{ $i }})" aria-label="Remove line {{ $i + 1 }}">×</button>
            </div>
            @endforeach
            @if ($hasCtr)
            <div class="ctr" style="{{ $grid }}"><span></span><span>@if ($counterAccount)<code style="color:#6b6e73">{{ $counterAccount->code }}</code> {{ $counterAccount->name }}@else<span class="ac-muted">{{ $t['counter'] }}</span>@endif</span><span class="ac-muted">Balancing line, filled in for you</span><span class="r" style="padding-right:10px">{{ $f($ctrDr) }}</span><span class="r" style="padding-right:10px">{{ $f($ctrCr) }}</span><span></span></div>
            @endif
            <div style="padding:10px 0"><button type="button" class="ac-link" wire:click="addLine">+ Add line</button></div>
            <div class="t" style="{{ $grid }}"><span></span><span>Total</span><span class="r" style="font-weight:400;color:{{ $diff ? '#b3352c' : '#1d7048' }}">{{ $diff ? 'Difference '.\App\Support\Formats::money(abs($diff), fmod($diff, 1.0) == 0.0 ? 0 : 2) : 'Balanced' }}</span><span class="r" style="padding-right:10px">{{ $money($tDr) }}</span><span class="r" style="padding-right:10px">{{ $money($tCr) }}</span><span></span></div>
        </div></div>
        <div style="display:flex;justify-content:space-between;gap:16px;font-size:12.5px;color:#6b6e73;flex-wrap:wrap"><span>{{ $msg }}</span>@if ($controls)<span>Control accounts {{ \Illuminate\Support\Arr::join($controls, ', ', ' and ') }} are not offered. Use their documents.</span>@endif</div>
    </div>
</div>
</div>
