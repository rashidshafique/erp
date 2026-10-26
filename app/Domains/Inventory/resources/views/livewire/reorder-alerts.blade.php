<?php

use App\Models\Material;
use App\Services\Stock\ReorderWatcher;
use App\Services\Stock\StockLevels;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Materials at or below their minimum stock, emptiest first, with how much to order. The list refreshes every
 * half minute on its own, and "Check now" raises any notices that are due without waiting for the hourly check.
 */
new class extends Component
{
    #[Url(except: '')]
    public string $search = '';

    public ?string $checked = null;

    public function checkNow(): void
    {
        $raised = app(ReorderWatcher::class)->checkAll();
        $this->checked = $raised === 0 ? 'Checked. No new notices were needed.' : "Checked. {$raised} notice".($raised === 1 ? '' : 's').' sent to the people who manage stores.';
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $search = mb_strtolower(trim($this->search));
        $rows = app(StockLevels::class)->needingReorder()
            ->when($search !== '', fn ($rows) => $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['material']->code.' '.$row['material']->name), $search)))
            ->values();

        return ['rows' => $rows, 'outCount' => $rows->where('out', true)->count()];
    }
};
?>

<div wire:poll.30s>
<div class="metrics">
    <div class="metric"><div class="metric-label">Need reordering</div><div class="metric-value">{{ $rows->count() }}</div></div>
    <div class="metric"><div class="metric-label">Out of stock</div><div class="metric-value" @if ($outCount) style="color:var(--danger)" @endif>{{ $outCount }}</div></div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="filters" role="search">
            <div class="field"><label for="ro-search">Search code or name</label><input id="ro-search" type="search" wire:model.live.debounce.300ms="search" autocomplete="off"></div>
            <button class="btn" type="button" wire:click="checkNow">Check now</button>
            @if ($checked)<span class="muted small">{{ $checked }}</span>@endif
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Materials at or below minimum</h2><span class="muted small">Set the minimum and reorder quantity on each material.</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Material</th><th>On hand</th><th>Minimum</th><th>Short by</th><th>Suggested order</th><th>Alerted</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse ($rows as $row)
            <tr wire:key="ro-{{ $row['material']->id }}">
                <td><a href="{{ route('materials.show', $row['material']) }}">{{ $row['material']->code }}</a> · {{ $row['material']->name }}@if ($row['out']) <strong style="color:var(--danger)">· out of stock</strong>@endif</td>
                <td>{{ \App\Support\Formats::number($row['on_hand'], \App\Support\Formats::decimals()) }} {{ $row['material']->base_uom }}</td>
                <td>{{ \App\Support\Formats::number($row['minimum'], \App\Support\Formats::decimals()) }}</td>
                <td>{{ \App\Support\Formats::number(max($row['shortfall'], 0), \App\Support\Formats::decimals()) }}</td>
                <td><strong>{{ \App\Support\Formats::number($row['suggested'], \App\Support\Formats::decimals()) }}</strong> {{ $row['material']->base_uom }}</td>
                <td class="muted small">{{ $row['material']->reorder_alerted_at ? \Illuminate\Support\Carbon::parse($row['material']->reorder_alerted_at)->diffForHumans() : 'not yet' }}</td>
                <td><a class="btn btn-small" href="{{ route('materials.lots.create', $row['material']) }}">+ Record lot</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Nothing is at its minimum. Materials with a minimum above zero are watched.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
</div>
