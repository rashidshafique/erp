<?php

use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Models\OpeningBalance;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Domains\Ledger\Services\OpeningBalances;
use App\Domains\Ledger\Services\Vouchers;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Opening balances at go-live (design 19f): the go-live date, receivables and payables carried from documents still open on it,
 * typed balances for every other account (or a CSV of code, debit, credit), the difference in opening balance equity, and Lock,
 * which posts the opening voucher.
 */
new class extends Component
{
    use WithFileUploads;

    public string $goLive = '';

    /** @var array<string, array{debit: string, credit: string}> typed balances by account id */
    public array $amounts = [];

    public string $newAccount = '';

    public string $newDebit = '';

    public string $newCredit = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $csv = null;

    public string $toast = '';

    public string $tone = 'ok';

    public function mount(): void
    {
        $this->goLive = OpeningBalances::goLive() ?? \App\Support\FinancialYear::containing()['start']->toDateString();
        $this->loadAmounts();
    }

    private function loadAmounts(): void
    {
        $this->amounts = OpeningBalance::all()->mapWithKeys(fn ($b) => [$b->account_id => ['debit' => $b->debit ? $this->plain($b->debit) : '', 'credit' => $b->credit ? $this->plain($b->credit) : '']])->all();
    }

    private function plain(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    private function locked(): bool
    {
        return app(OpeningBalances::class)->journal() !== null;
    }

    private function num(string $v): float
    {
        return round((float) str_replace(',', '', $v), 2);
    }

    public function updatedGoLive(): void
    {
        if ($this->locked() || ! strtotime($this->goLive)) {
            return;
        }
        OpeningBalances::setGoLive($this->goLive);
        $this->say('Go-live set to '.\Illuminate\Support\Carbon::parse($this->goLive)->format('d M Y').'.');
    }

    public function updatedAmounts(mixed $value, string $key): void
    {
        [$id, $side] = explode('.', $key);
        if ($this->locked()) {
            return;
        }
        if ($this->num((string) $value) != 0) {
            $this->amounts[$id][$side === 'debit' ? 'credit' : 'debit'] = '';
        }
        $this->store($id, $this->num($this->amounts[$id]['debit'] ?? ''), $this->num($this->amounts[$id]['credit'] ?? ''));
    }

    public function add(): void
    {
        if ($this->locked() || $this->newAccount === '') {
            return;
        }
        if ($this->store($this->newAccount, $this->num($this->newDebit), $this->num($this->newCredit))) {
            $this->reset('newAccount', 'newDebit', 'newCredit');
        }
    }

    public function remove(string $id): void
    {
        if (! $this->locked()) {
            $this->store($id, 0, 0);
        }
    }

    private function store(string $id, float $debit, float $credit): bool
    {
        try {
            app(OpeningBalances::class)->type($id, $debit, $credit);
        } catch (InvalidArgumentException $e) {
            $this->say($e->getMessage(), 'bad');

            return false;
        }
        $this->loadAmounts();

        return true;
    }

    public function updatedCsv(): void
    {
        $this->validate(['csv' => ['file', 'mimes:csv,txt', 'max:2048']]);
        if ($this->locked()) {
            return;
        }
        $r = app(OpeningBalances::class)->import($this->csv->getRealPath());
        $this->csv = null;
        $this->loadAmounts();
        $this->say('Imported '.$r['done'].' '.\Illuminate\Support\Str::plural('balance', $r['done']).'.'.($r['skipped'] ? ' Skipped: '.implode('; ', array_slice($r['skipped'], 0, 5)).'.' : ''), $r['skipped'] ? 'warn' : 'ok');
    }

    public function lock(): void
    {
        OpeningBalances::setGoLive($this->goLive);
        try {
            $j = app(OpeningBalances::class)->lock();
        } catch (InvalidArgumentException $e) {
            $this->say($e->getMessage(), 'bad');

            return;
        }
        $this->say('Locked. Posted '.$j->number.'.');
    }

    private function say(string $text, string $tone = 'ok'): void
    {
        $this->toast = $text;
        $this->tone = $tone;
    }

    public function with(): array
    {
        $service = app(OpeningBalances::class);
        $journal = $service->journal();
        $draft = $service->draft($this->goLive ?: today()->toDateString());
        $live = \Illuminate\Support\Carbon::parse($this->goLive ?: today());
        if ($journal) {
            $journal->load('lines.account');
            $rows = $journal->lines->groupBy('account_id')->map(fn ($ls) => ['account' => $ls->first()->account, 'debit' => round($ls->sum('debit'), 2), 'credit' => round($ls->sum('credit'), 2),
                'source' => $ls->count() > 1 || $ls->first()->party_id ? $ls->count().' open documents by party' : ($ls->first()->account_id === LedgerSettings::account('opening_equity')->id ? 'Difference, posted here until you move it' : 'Typed'),
                'kind' => $ls->first()->account_id === LedgerSettings::account('opening_equity')->id ? 'difference' : 'posted', 'id' => null])
                ->sortBy(fn ($r) => $r['account']->code)->values()->all();
            $draft = ['rows' => $rows, 'difference' => 0.0, 'debit' => $journal->total, 'credit' => $journal->total, 'control' => $draft['control']];
        }
        $difference = collect($draft['rows'])->firstWhere('kind', 'difference');
        $typedIds = OpeningBalance::pluck('account_id')->all();
        $opening = LedgerSettings::account('opening_equity')->id;

        return [
            'journal' => $journal, 'rows' => $draft['rows'], 'debit' => $draft['debit'], 'credit' => $draft['credit'], 'diff' => $difference,
            'control' => $draft['control'], 'live' => $live, 'obNumber' => $journal?->number ?? Vouchers::preview('OB', $live),
            'choices' => LedgerAccount::postable()->whereNull('control')->where('id', '<>', $opening)->whereNotIn('id', $typedIds)->orderBy('code')->get(['id', 'code', 'name']),
            'canLock' => ! $journal && count($draft['rows']) > 0,
        ];
    }
};
?>

<div>
@include('ledger::_styles')
@php
    $n = fn (float $v) => $v ? \App\Support\Formats::number($v, fmod($v, 1.0) == 0.0 ? 0 : 2) : '';
    $step = fn (bool $done, int $no, bool $current) => $done ? ['✓', '#1d7048', '#fff'] : ($current ? [(string) $no, '#2f5fd0', '#fff'] : [(string) $no, '#f0f1f3', '#6b6e73']);
    $docs = $control['receivables']['count'] + $control['payables']['count'];
    $steps = [
        [true, 'Go-live date', $live->format('d M Y').' · '.\App\Domains\Ledger\Services\Vouchers::yearLabel($live)],
        [true, 'Open documents', $docs ? $control['receivables']['count'].' sales and '.$control['payables']['count'].' supplier invoices' : 'Sales and supplier invoices'],
        [(bool) $journal, 'Account balances', 'Typed or carried from step 2'],
        [(bool) $journal, 'Check and lock', 'Posts '.$obNumber],
    ];
    $current = $journal ? 5 : 3;
    $cur = trim(\App\Support\Formats::withCurrency(''));
@endphp
<style>
.ob-steps { display: flex; gap: 40px; align-items: center; font-size: 13px; flex-wrap: wrap; }
.ob-steps .dot { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; flex: none; }
.ob-table input { width: 140px; padding: 6px 8px; text-align: right; }
.ob-table select { padding: 6px 8px; }
.ob-table tr.diff td { background: #fbf0dc; }
</style>
<div class="ac-card">
    <div class="ac-bar">
        <div class="ac-crumb"><span>Accounts</span><span class="sep">/</span><span>Opening balances</span>@if ($journal)<span class="ac-pill ok">Locked</span>@else<span class="ac-pill warn">Not locked</span>@endif</div>
        <div class="ac-actions">
            @if ($journal)<a class="ac-btn" href="{{ route('ledger.journals.show', $journal) }}">Open {{ $journal->number }}</a>
            @else<label class="ac-btn" style="margin:0">Import CSV<input type="file" accept=".csv,text/csv" wire:model="csv" style="display:none"></label>
            <button type="button" class="ac-btn primary" wire:click="lock" @disabled(! $canLock) wire:confirm="Lock the opening balances and post {{ $obNumber }}?">Lock opening balances</button>@endif
        </div>
    </div>
    <div style="padding:20px 28px;display:flex;flex-direction:column;gap:18px">
        @if ($toast)<div @class(['ac-toast', 'bad' => $tone === 'bad', 'warn' => $tone === 'warn']) role="status"><span>{{ $toast }}</span><button type="button" class="ac-x" style="font-size:14px;color:inherit" wire:click="$set('toast', '')" aria-label="Dismiss">×</button></div>@endif
        @error('csv')<div class="ac-toast bad"><span>{{ $message }}</span></div>@enderror
        <div class="ob-steps">
            @foreach ($steps as $i => [$done, $title, $sub])
                @php [$mark, $bg, $fg] = $step($done, $i + 1, $current === $i + 1); @endphp
                <div style="display:flex;gap:10px;align-items:center"><span class="dot" style="background:{{ $bg }};color:{{ $fg }}">{{ $mark }}</span><div><div style="font-weight:{{ $current === $i + 1 ? 600 : 400 }}">{{ $title }}</div><div style="font-size:12px;color:#6b6e73">{{ $sub }}</div></div></div>
            @endforeach
        </div>
        @unless ($journal)
        <div class="ac-filters"><label class="ac-muted" style="display:flex;gap:8px;align-items:center;margin:0">Go-live date <input type="date" wire:model.live="goLive" style="width:auto;padding:6px 10px"></label><span class="ac-note">The opening voucher is dated the day before. Documents dated before go-live are carried here and not posted one by one.</span></div>
        @endunless
        <div class="ac-table"><table class="ob-table"><thead><tr><th>Code</th><th>Account</th><th class="r">Debit{{ $cur ? ' ('.$cur.')' : '' }}</th><th class="r">Credit{{ $cur ? ' ('.$cur.')' : '' }}</th><th>Source</th>@unless ($journal)<th></th>@endunless</tr></thead><tbody>
            @foreach ($rows as $r)
                <tr @class(['diff' => $r['kind'] === 'difference']) wire:key="ob-{{ $r['account']->id }}-{{ $r['kind'] }}">
                    <td><code>{{ $r['account']->code }}</code></td><td>{{ $r['account']->name }}</td>
                    @if ($r['kind'] === 'typed' && ! $journal)
                        <td class="r"><input wire:model.blur="amounts.{{ $r['account']->id }}.debit" inputmode="decimal" placeholder="0" aria-label="Debit {{ $r['account']->code }}"></td>
                        <td class="r"><input wire:model.blur="amounts.{{ $r['account']->id }}.credit" inputmode="decimal" placeholder="0" aria-label="Credit {{ $r['account']->code }}"></td>
                    @else
                        <td class="r">{{ $n($r['debit']) }}</td><td class="r">{{ $n($r['credit']) }}</td>
                    @endif
                    <td style="color:{{ $r['kind'] === 'difference' ? '#94610a' : '#6b6e73' }}">@if (in_array($r['kind'], ['receivables', 'payables'], true))<a href="{{ route($r['kind'] === 'receivables' ? 'receivables.index' : 'payables.index') }}">{{ $r['source'] }}</a> · from documents @else{{ $r['source'] }}@endif</td>
                    @unless ($journal)<td class="r">@if ($r['kind'] === 'typed')<button type="button" class="ac-x" style="font-size:13px" wire:click="remove('{{ $r['account']->id }}')" aria-label="Remove {{ $r['account']->code }}">×</button>@endif</td>@endunless
                </tr>
            @endforeach
            @unless ($journal)
            <tr><td colspan="2"><select wire:model="newAccount" aria-label="Account to add"><option value="">+ Add an account balance</option>@foreach ($choices as $a)<option value="{{ $a->id }}">{{ $a->code }}  {{ $a->name }}</option>@endforeach</select></td>
                <td class="r"><input wire:model="newDebit" inputmode="decimal" placeholder="0" aria-label="New debit"></td><td class="r"><input wire:model="newCredit" inputmode="decimal" placeholder="0" aria-label="New credit"></td>
                <td><button type="button" class="ac-btn" wire:click="add">Add</button></td><td></td></tr>
            @endunless
            <tr style="font-weight:600"><td style="border:0"></td><td style="border:0">Total</td><td class="r" style="border:0">{{ $n($debit) ?: '0' }}</td><td class="r" style="border:0">{{ $n($credit) ?: '0' }}</td><td style="border:0"></td>@unless ($journal)<td style="border:0"></td>@endunless</tr>
        </tbody></table></div>
        @if ($diff)
        <div style="padding:10px 12px;border-radius:7px;background:#fbf0dc;color:#94610a;font-size:12.5px;line-height:1.5;text-wrap:pretty">{{ \App\Support\Formats::money(max($diff['debit'], $diff['credit']), 0) }} sits in {{ $diff['account']->code }}. Usually this is last year's retained earnings; move it with a JV to the retained earnings account once the auditor signs the accounts. Control account balances come from their open documents and cannot be typed.</div>
        @endif
        @if ($journal)<div class="ac-note">Locked by {{ $journal->number }} on {{ $journal->journal_date->format('d M Y') }}. To change it, reverse {{ $journal->number }} from its page and lock again.</div>@endif
    </div>
</div>
</div>
