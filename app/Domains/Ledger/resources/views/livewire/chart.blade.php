<?php

use App\Domains\Ledger\Livewire\ChartDialog;
use App\Domains\Ledger\Models\JournalLine;
use App\Domains\Ledger\Models\LedgerAccount;
use Livewire\Component;

/**
 * Chart of accounts (design 19a): categories, sections and nominal accounts in one tree with balances to today. Groups open and
 * close, search finds a code or a name, inactive accounts show on request, ticked accounts can be deleted together (those with
 * postings are kept), and + Nominal account, + Section and Edit open the dialogs (19i to 19l).
 */
new class extends Component
{
    use ChartDialog;

    public string $q = '';

    public bool $showInactive = false;

    /** @var list<string> category codes closed by hand */
    public array $closed = [];

    /** @var list<string> section codes opened */
    public array $opened = [];

    /** @var list<string> ticked account ids */
    public array $ticked = [];

    public function toggleCategory(string $code): void
    {
        $this->closed = in_array($code, $this->closed, true) ? array_values(array_diff($this->closed, [$code])) : [...$this->closed, $code];
    }

    public function toggleSection(string $code): void
    {
        $this->opened = in_array($code, $this->opened, true) ? array_values(array_diff($this->opened, [$code])) : [...$this->opened, $code];
    }

    public function expandAll(): void
    {
        $this->closed = [];
        $this->opened = LedgerAccount::where('code', 'like', '__-__')->pluck('code')->all();
    }

    public function collapseAll(): void
    {
        $this->opened = [];
    }

    public function tick(string $id): void
    {
        $this->ticked = in_array($id, $this->ticked, true) ? array_values(array_diff($this->ticked, [$id])) : [...$this->ticked, $id];
    }

    /** Deletes the ticked accounts that were never used; the rest stay and are named in the message. */
    public function deleteTicked(): void
    {
        $accounts = LedgerAccount::whereIn('id', $this->ticked)->orderBy('code')->get();
        if ($accounts->isEmpty()) {
            return;
        }
        $posted = JournalLine::whereIn('account_id', $accounts->pluck('id'))->distinct()->pluck('account_id')->flip();
        $gone = [];
        foreach ($accounts as $a) {
            if ($this->deleteBlock($a, isset($posted[$a->id])) === null) {
                $a->delete();
                $gone[] = $a->code;
            }
        }
        $kept = $accounts->count() - count($gone);
        $this->ticked = [];
        $this->say(trim(($gone ? 'Deleted '.implode(', ', $gone).'. ' : '').($kept ? $kept.' kept: they have postings or a balance. Deactivate them instead.' : '')), $gone ? 'ok' : 'warn');
    }

    protected function opened(string $code): void
    {
        $cat = substr($code, 0, 2);
        $this->closed = array_values(array_diff($this->closed, [$cat]));
        $section = substr($code, 0, 5);
        if (! in_array($section, $this->opened, true)) {
            $this->opened[] = $section;
        }
    }

    public function with(): array
    {
        $tree = LedgerAccount::tree(today()->toDateString());
        $posted = JournalLine::query()->distinct()->pluck('account_id')->flip();
        $byParent = $tree->groupBy(fn ($a) => $a->parent_id ?? '');
        $q = mb_strtolower(trim($this->q));
        $qc = str_replace('-', '', $q);
        $match = fn ($a) => $q === '' || str_contains(str_replace('-', '', $a->code), $qc) || str_contains(mb_strtolower($a->name), $q);
        $rows = [];
        $nAcc = 0;
        $nSec = 0;
        $cats = $byParent->get('', collect());
        foreach ($cats as $cat) {
            $secs = $byParent->get($cat->id, collect());
            $nSec += $secs->count();
            $catAccounts = $secs->sum(fn ($s) => $byParent->get($s->id, collect())->count());
            $nAcc += $catAccounts;
            $visible = fn ($s) => $byParent->get($s->id, collect())->filter(fn ($a) => ($this->showInactive || $a->is_active) && $match($a));
            $secHit = fn ($s) => $q === '' || $visible($s)->isNotEmpty() || str_contains(mb_strtolower($s->name), $q) || str_starts_with(str_replace('-', '', $s->code), $qc);
            if ($q !== '' && ! $secs->contains($secHit)) {
                continue;
            }
            $catOpen = $q !== '' || ! in_array($cat->code, $this->closed, true);
            $rows[] = ['kind' => 'cat', 'a' => $cat, 'open' => $catOpen, 'count' => '('.$secs->count().' sections · '.$catAccounts.' accounts)'];
            if (! $catOpen) {
                continue;
            }
            foreach ($secs as $s) {
                if (! $secHit($s)) {
                    continue;
                }
                $secOpen = $q !== '' || in_array($s->code, $this->opened, true);
                $rows[] = ['kind' => 'sec', 'a' => $s, 'open' => $secOpen, 'count' => '('.$byParent->get($s->id, collect())->count().')', 'new' => $s->created_at?->isToday() && $s->created_at->gt(now()->subHour())];
                if ($secOpen) {
                    foreach ($visible($s) as $a) {
                        $rows[] = ['kind' => 'acc', 'a' => $a, 'ticked' => in_array($a->id, $this->ticked, true), 'new' => ! isset($posted[$a->id]) && $a->created_at?->gt(now()->subHour())];
                    }
                }
            }
        }

        return [
            'rows' => $rows,
            'count' => $nAcc.' accounts · '.$nSec.' sections · '.$cats->count().' categories',
            'ticks' => LedgerAccount::whereIn('id', $this->ticked)->count(),
            'd' => $this->dialog(),
        ];
    }
};
?>

<div>
@include('ledger::_styles')
@php $bal = fn (float $v) => $v == 0 ? '—' : \App\Support\Formats::money(abs($v), 0).($v > 0 ? ' Dr' : ' Cr'); $grid = 'display:grid;grid-template-columns:28px 100px minmax(0,1fr) 180px 130px;gap:12px;align-items:center'; @endphp
<style>
.coa-list { display: flex; flex-direction: column; border: 1px solid #e6e7e9; border-radius: 8px; overflow: hidden; }
.coa-list .h { padding: 9px 12px; border-bottom: 1px solid #e6e7e9; font-size: 11.5px; color: #6b6e73; font-weight: 500; }
.coa-list .c { padding: 9px 12px; background: #eef0f3; border-bottom: 1px solid #e3e5e8; cursor: pointer; }
.coa-list .s { padding: 8px 12px; background: #f8f8f9; border-bottom: 1px solid #eceef0; cursor: pointer; }
.coa-list .a { padding: 7px 12px; border-bottom: 1px solid #f2f3f4; background: #fff; }
.coa-list .a.on { background: #f5f8fe; }
.coa-list .caret { color: #6b6e73; font-size: 11px; }
.coa-list .acts { display: flex; gap: 10px; justify-content: flex-end; font-size: 12.5px; }
.coa-list .r { text-align: right; }
@media (max-width: 760px) { .coa-list > div { grid-template-columns: 22px 80px minmax(0,1fr) 120px !important; } .coa-list .acts { display: none; } .coa-list .h > :last-child { display: none; } }
</style>
<div class="ac-card" style="min-height:560px">
    <div class="ac-bar">
        <div class="ac-crumb"><span>Accounts</span><span class="sep">/</span><span>Chart of accounts</span></div>
        <div class="ac-actions">
            <a class="ac-btn" href="{{ route('ledger.accounts.export') }}">Export</a>
            <button type="button" class="ac-btn" wire:click="deleteTicked" @disabled($ticks === 0) @if ($ticks) style="color:#b3352c" wire:confirm="Delete the ticked accounts? Accounts with postings are kept." @endif>{{ $ticks ? 'Delete selected ('.$ticks.')' : 'Delete selected' }}</button>
            <button type="button" class="ac-btn" wire:click="openSection('')">+ Section</button>
            <button type="button" class="ac-btn primary" wire:click="openAccount('')">+ Nominal account</button>
        </div>
    </div>
    <div class="ac-body">
        <div class="ac-filters" style="gap:12px">
            <input class="ac-input" type="search" wire:model.live.debounce.250ms="q" placeholder="Search code or name, e.g. 22-06 or scrap" style="max-width:320px" aria-label="Search accounts">
            <span class="ac-check" wire:click="$toggle('showInactive')" role="checkbox" aria-checked="{{ $showInactive ? 'true' : 'false' }}" tabindex="0"><span @class(['ac-tick', 'on' => $showInactive]) style="width:14px;height:14px">{{ $showInactive ? '✓' : '' }}</span>Show inactive</span>
            <span style="flex:1"></span>
            <span class="ac-muted" style="font-size:12px;white-space:nowrap">{{ $count }}</span>
            <button type="button" class="ac-link" wire:click="expandAll">Expand all</button>
            <button type="button" class="ac-link" wire:click="collapseAll">Collapse all</button>
        </div>
        @if ($toast)<div @class(['ac-toast', 'warn' => $toastTone === 'warn']) role="status"><span>{{ $toast }}</span><button type="button" class="ac-x" style="font-size:14px;color:inherit" wire:click="$set('toast', '')" aria-label="Dismiss">×</button></div>@endif
        <div class="coa-list">
            <div class="h" style="{{ $grid }}"><span></span><span>Code</span><span>Name</span><span class="r">Balance {{ today()->format('d M Y') }}</span><span class="r">Action</span></div>
            @forelse ($rows as $r)
                @php $a = $r['a']; @endphp
                @if ($r['kind'] === 'cat')
                <div class="c" style="{{ $grid }}" wire:click="toggleCategory('{{ $a->code }}')" wire:key="c-{{ $a->id }}"><span class="caret">{{ $r['open'] ? '▾' : '▸' }}</span><code style="font-weight:600">{{ $a->code }}</code><span><strong style="font-weight:600">{{ $a->name }}</strong> <span class="ac-muted">{{ $r['count'] }}</span></span><span class="r" style="font-weight:600">{{ $bal($a->net) }}</span><span class="r"><button type="button" class="ac-link" wire:click.stop="openSection('{{ $a->code }}')">+ Section</button></span></div>
                @elseif ($r['kind'] === 'sec')
                <div class="s" style="{{ $grid }}" wire:click="toggleSection('{{ $a->code }}')" wire:key="s-{{ $a->id }}"><span class="caret" style="padding-left:10px">{{ $r['open'] ? '▾' : '▸' }}</span><code style="color:#3a3c40">{{ $a->code }}</code><span style="display:flex;gap:6px;align-items:baseline"><span style="font-weight:500">{{ $a->name }}</span><span style="color:#2f5fd0">{{ $r['count'] }}</span>@if ($r['new'])<span style="font-size:11px;color:#2f5fd0">new</span>@endif</span><span class="r" style="font-weight:500">{{ $bal($a->net) }}</span><span class="acts"><button type="button" class="ac-link" wire:click.stop="openAccount('{{ $a->code }}')">+ Account</button><button type="button" class="ac-link" wire:click.stop="openSectionEdit('{{ $a->id }}')">Edit</button></span></div>
                @else
                @php $color = $a->is_active ? '#1c1d1f' : '#9a9da3'; @endphp
                <div @class(['a', 'on' => $r['ticked']]) style="{{ $grid }};font-size:13px" wire:key="a-{{ $a->id }}"><span @class(['ac-tick', 'on' => $r['ticked']]) wire:click="tick('{{ $a->id }}')" role="checkbox" aria-checked="{{ $r['ticked'] ? 'true' : 'false' }}" aria-label="Tick {{ $a->code }}" tabindex="0">{{ $r['ticked'] ? '✓' : '' }}</span><code style="color:{{ $color }}">{{ $a->code }}</code><span style="display:flex;gap:6px;align-items:center;min-width:0;flex-wrap:wrap"><span style="color:{{ $color }}">{{ $a->name }}</span>@if ($a->control)<span class="ac-tag" title="{{ $a->control }}">Control</span>@endif @if ($a->is_charge)<span class="ac-tag dc">Deduction and charge</span>@endif @unless ($a->is_active)<span class="ac-tag off">Inactive</span>@endunless @if ($r['new'])<span class="ac-tag new">New</span>@endif</span><span class="r" style="color:{{ $color }}">{{ $bal($a->net) }}</span><span class="acts"><a href="{{ route('ledger.accounts.show', $a) }}">Ledger</a><button type="button" class="ac-link" wire:click="openAccountEdit('{{ $a->id }}')">Edit</button></span></div>
                @endif
            @empty
                <div class="a ac-muted">No account matches “{{ $q }}”.</div>
            @endforelse
        </div>
        <div class="ac-note">Codes read category, section, account: 22-06-01 is Current Assets, Raw Materials, Pig iron. New codes are the next free number in their section and cannot be changed. Control accounts are posted only by their documents. Section order is set on <a href="{{ route('ledger.sections.index') }}">Sections</a>.</div>
    </div>
</div>
@include('ledger::accounts._dialog')
</div>
