<?php

use App\Models\MaterialLot;
use App\Models\StockLocation;
use App\Services\Stock\StockLedger;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Where the stock is: each lot's quantity at each location, with the part not yet put anywhere shown as such.
 * Filter by location or search a material; totals follow the filter.
 */
new class extends Component
{
    #[Url(except: '')]
    public string $location = '';

    #[Url(except: '')]
    public string $search = '';

    /** @return array<string, mixed> */
    public function with(): array
    {
        $search = trim($this->search);
        $lots = MaterialLot::query()
            ->with('material:id,code,name')
            ->whereHas('material', fn ($q) => $q->where('is_stock_tracked', true)
                ->when($search !== '', fn ($w) => $w->where(fn ($m) => $m->where('code', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%"))))
            ->whereHas('costLayers', fn ($q) => $q->where('remaining_qty', '>', 0))
            ->orderBy('lot_number')
            ->get();

        $held = app(StockLedger::class)->heldByLocationForLots($lots->pluck('id')->all());
        $names = StockLocation::pluck('code', 'id');

        $rows = [];
        foreach ($lots as $lot) {
            foreach ($held[$lot->id] ?? [] as $locationId => $quantity) {
                if ($this->location !== '' && $this->location !== ($locationId === '' ? 'none' : $locationId)) {
                    continue;
                }
                $rows[] = ['location' => $locationId === '' ? 'Not put anywhere' : ($names[$locationId] ?? '?'), 'lot' => $lot, 'quantity' => $quantity];
            }
        }
        usort($rows, fn ($a, $b) => [$a['location'], $a['lot']->lot_number] <=> [$b['location'], $b['lot']->lot_number]);

        return [
            'rows' => $rows,
            'total' => round(array_sum(array_column($rows, 'quantity')), 3),
            'locations' => StockLocation::orderBy('code')->get(['id', 'code', 'name']),
        ];
    }
};
?>

<div>
<div class="panel">
    <div class="panel-body">
        <div class="filters" role="search">
            <div class="field"><label for="bal-search">Search material</label><input id="bal-search" type="search" wire:model.live.debounce.300ms="search" autocomplete="off"></div>
            <div class="field"><label for="bal-loc">Location</label>
                <select id="bal-loc" wire:model.live="location"><option value="">All locations</option><option value="none">Not put anywhere</option>@foreach ($locations as $loc)<option value="{{ $loc->id }}">{{ $loc->code }} · {{ $loc->name }}</option>@endforeach</select></div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Stock by location</h2><span class="muted small">{{ \App\Support\Formats::number($total, 3) }} kg shown</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Location</th><th>Material</th><th>Lot</th><th>Quantity (kg)</th></tr></thead>
        <tbody>
        @forelse ($rows as $row)
            <tr wire:key="bal-{{ $row['lot']->id }}-{{ $row['location'] }}">
                <td>{{ $row['location'] }}</td>
                <td>{{ $row['lot']->material->code }} · {{ $row['lot']->material->name }}</td>
                <td class="mono">{{ $row['lot']->lot_number }}</td>
                <td>{{ \App\Support\Formats::number($row['quantity'], 3) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No stock matches. Post a lot receipt to bring stock in.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
</div>
