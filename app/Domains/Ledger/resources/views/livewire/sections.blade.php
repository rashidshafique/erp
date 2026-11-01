<?php

use App\Domains\Ledger\Livewire\ChartDialog;
use App\Domains\Ledger\Models\LedgerAccount;
use Livewire\Component;

/**
 * Sections of the chart by category (design 19m). Up and Down set the order sections show in on reports and in pickers; codes
 * never change. Edit and + Section open the section dialog (19k, 19l).
 */
new class extends Component
{
    use ChartDialog;

    /** Swaps a section with its neighbour in the category and renumbers the order 1, 2, 3… */
    public function move(string $id, int $step): void
    {
        $section = LedgerAccount::findOrFail($id);
        $list = $this->sectionsOf(substr($section->code, 0, 2))->values();
        $i = $list->search(fn ($s) => $s->id === $section->id);
        $j = $i + ($step < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= $list->count()) {
            return;
        }
        $ids = $list->pluck('id')->all();
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $n => $sid) {
            LedgerAccount::whereKey($sid)->update(['sort_order' => $n + 1]);
        }
    }

    public function with(): array
    {
        return [
            'groups' => LedgerAccount::whereNull('parent_id')->orderBy('sort_order')->get()->map(fn ($c) => ['cat' => $c, 'items' => $this->sectionsOf($c->code)]),
            'd' => $this->dialog(),
        ];
    }
};
?>

<div>
@include('ledger::_styles')
@php $grid = 'display:grid;grid-template-columns:minmax(0,1fr) 90px 90px 240px 150px 70px;gap:12px;align-items:center'; @endphp
<style>
.sec-list { display: flex; flex-direction: column; border: 1px solid #e6e7e9; border-radius: 8px; overflow: hidden; }
.sec-list .h { padding: 9px 12px; border-bottom: 1px solid #e6e7e9; font-size: 11.5px; color: #6b6e73; font-weight: 500; }
.sec-list .c { display: flex; gap: 8px; align-items: baseline; padding: 9px 12px; background: #eef0f3; border-bottom: 1px solid #e3e5e8; }
.sec-list .s { padding: 8px 12px; border-bottom: 1px solid #f2f3f4; }
.sec-list .r { text-align: right; }
.sec-list .mv { display: flex; gap: 14px; font-size: 12.5px; }
.sec-list .mv button { background: none; border: 0; padding: 0; min-height: 0; box-shadow: none; cursor: pointer; font-size: 12.5px; }
.sec-list .mv button[disabled] { color: #c4c7cc !important; cursor: default; }
@media (max-width: 760px) { .sec-list > div.s, .sec-list > div.h { grid-template-columns: minmax(0,1fr) 70px 110px 50px !important; } .sec-list .hide-sm { display: none; } }
</style>
<div class="ac-card" style="min-height:560px">
    <div class="ac-bar">
        <div class="ac-crumb"><span>Accounts</span><span class="sep">/</span><a href="{{ route('ledger.accounts.index') }}">Chart of accounts</a><span class="sep">/</span><span>Sections</span></div>
        <div class="ac-actions"><button type="button" class="ac-btn primary" wire:click="openSection('')">+ Section</button></div>
    </div>
    <div class="ac-body">
        <div class="ac-muted" style="font-size:12.5px">Up and Down set the order of sections on reports and in pickers. Codes do not change.</div>
        @if ($toast)<div class="ac-toast" role="status"><span>{{ $toast }}</span><button type="button" class="ac-x" style="font-size:14px;color:inherit" wire:click="$set('toast', '')" aria-label="Dismiss">×</button></div>@endif
        <div class="sec-list">
            <div class="h" style="{{ $grid }}"><span>Section</span><span>Code</span><span class="r hide-sm">Accounts</span><span class="hide-sm">Report line</span><span>Order</span><span class="r">Action</span></div>
            @foreach ($groups as $g)
                <div class="c" wire:key="g-{{ $g['cat']->id }}"><code style="font-weight:600">{{ $g['cat']->code }}</code><strong style="font-weight:600">{{ $g['cat']->name }}</strong><span class="ac-muted">({{ $g['items']->count() }})</span></div>
                @foreach ($g['items'] as $i => $s)
                <div class="s" style="{{ $grid }};font-size:13px" wire:key="s-{{ $s->id }}">
                    <span style="padding-left:14px">{{ $s->name }}</span><code style="color:#3a3c40">{{ $s->code }}</code><span class="r hide-sm">{{ $s->children_count }}</span><span class="ac-muted hide-sm">{{ $s->report_line }}</span>
                    <span class="mv"><button type="button" wire:click="move('{{ $s->id }}', -1)" style="color:#1d7048" @disabled($i === 0) aria-label="Move {{ $s->name }} up">↑ Up</button><button type="button" wire:click="move('{{ $s->id }}', 1)" style="color:#b3352c" @disabled($i === $g['items']->count() - 1) aria-label="Move {{ $s->name }} down">↓ Down</button></span>
                    <span class="r"><button type="button" class="ac-link" wire:click="openSectionEdit('{{ $s->id }}')">Edit</button></span>
                </div>
                @endforeach
            @endforeach
        </div>
    </div>
</div>
@include('ledger::accounts._dialog')
</div>
