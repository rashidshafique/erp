<?php

use App\Models\Product;
use App\Models\ProductionStageDefinition;
use App\Services\Production\ProcessRoutes;
use Livewire\Component;

/**
 * A product's route on its Routing tab. The product follows the foundry's default route until it is customised; then its own
 * copy is reordered, cut down or added to from the step library, and each change applies at once. Back to default drops it.
 */
new class extends Component
{
    public string $productId;

    public ?string $saved = null;

    public ?string $problem = null;

    public function mount(Product $product): void
    {
        $this->productId = $product->id;
    }

    private function product(): Product
    {
        return Product::withTrashed()->findOrFail($this->productId);
    }

    private function authorizeChanges(): void
    {
        abort_unless(auth()->user()?->hasPermission('master.manage') && ! $this->product()->trashed(), 403);
    }

    /** @return list<string> */
    private function order(): array
    {
        return app(ProcessRoutes::class)->productRoute($this->productId)?->operations()->pluck('stage_definition_id')->all() ?? [];
    }

    /** @param list<string> $order */
    private function saveOrder(array $order, string $message): void
    {
        $this->authorizeChanges();
        try {
            app(ProcessRoutes::class)->saveForProduct($this->product(), $order);
            [$this->saved, $this->problem] = [$message, null];
        } catch (InvalidArgumentException $e) {
            [$this->saved, $this->problem] = [null, $e->getMessage()];
        }
    }

    public function customise(): void
    {
        $this->authorizeChanges();
        app(ProcessRoutes::class)->customise($this->product());
        [$this->saved, $this->problem] = ['This product now has its own route. Changes here apply to it only.', null];
    }

    public function resetToDefault(): void
    {
        $this->authorizeChanges();
        app(ProcessRoutes::class)->resetToDefault($this->product());
        [$this->saved, $this->problem] = ['This product follows the default route again.', null];
    }

    public function move(int $index, int $by): void
    {
        $order = $this->order();
        $to = $index + $by;
        if (! isset($order[$index], $order[$to])) {
            return;
        }
        [$order[$index], $order[$to]] = [$order[$to], $order[$index]];
        $this->saveOrder($order, 'Order saved.');
    }

    public function remove(string $stageId): void
    {
        $name = ProductionStageDefinition::find($stageId)?->name;
        $this->saveOrder(array_values(array_diff($this->order(), [$stageId])), "{$name} taken off this product's route.");
    }

    public function add(string $stageId): void
    {
        $name = ProductionStageDefinition::find($stageId)?->name;
        $this->saveOrder([...$this->order(), $stageId], "{$name} added at the end. Move it into place.");
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $routes = app(ProcessRoutes::class);
        $product = $this->product();
        $custom = $routes->productRoute($product);
        $steps = $custom ? $custom->operations()->with('stage')->get()->map->stage->filter()->values() : $routes->defaultSteps()->map->stage->filter()->values();

        return [
            'product' => $product,
            'custom' => $custom !== null,
            'steps' => $steps,
            'library' => $custom ? ProductionStageDefinition::whereNotIn('id', $steps->pluck('id'))->orderBy('name')->get() : collect(),
            'kinds' => ProductionStageDefinition::KINDS,
            'canEdit' => (bool) auth()->user()?->hasPermission('master.manage') && ! $product->trashed(),
        ];
    }
};
?>

<div class="panel">
<style>
    .proute { list-style: none; margin: 0; padding: 0; }
    .proute li { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-bottom: 1px solid var(--border); }
    .proute li:last-child { border-bottom: 0; }
    .proute-n { flex: 0 0 26px; height: 26px; border-radius: 50%; background: var(--accent); color: #fff; display: grid; place-items: center; font-size: 12px; font-weight: 700; }
    .proute-main { flex: 1; min-width: 0; }
    .proute-tag { font-size: 11px; padding: 1px 7px; border-radius: 999px; background: #eef1f3; color: var(--muted); margin-left: 6px; }
    .proute-actions { display: flex; gap: 4px; flex-wrap: wrap; justify-content: flex-end; }
    .proute-line { padding: 12px 14px; border-bottom: 1px solid var(--border); }
    .proute-badge { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; background: #f0f1f3; color: #55585d; }
    .proute-badge.custom { background: #eaf0fd; color: #2f5fd0; }
</style>
    <div class="panel-header">
        <h2>Routing</h2>
        <span class="proute-badge {{ $custom ? 'custom' : '' }}">{{ $custom ? 'Custom for this product' : 'Default route' }}</span>
        @if ($canEdit)
            <span style="margin-left:auto;display:flex;gap:6px">
                @if ($custom)
                    <button class="btn btn-small" type="button" wire:click="resetToDefault" wire:confirm="Drop this product's own route and follow the default route again? Lots keep what they recorded.">Back to default</button>
                @else
                    <button class="btn btn-small btn-primary" type="button" wire:click="customise">Customise for this product</button>
                @endif
            </span>
        @endif
    </div>
    @if ($saved)<div class="alert alert-success" role="status" style="margin:12px 14px 0">{{ $saved }}</div>@endif
    @if ($problem)<div class="alert alert-error" role="alert" style="margin:12px 14px 0">{{ $problem }}</div>@endif

    <div class="proute-line">Mould → pour{{ $steps->isNotEmpty() ? ' → '.$steps->pluck('name')->implode(' → ') : '' }} → pack</div>
    @unless ($custom)
        <p class="muted small" style="margin:10px 14px">This product follows the foundry's default route from <a href="{{ route('settings.process') }}">Settings → Our process</a>. Customise it to leave out steps, change their order or add steps only this product needs.</p>
    @endunless

    @if ($custom)
        <ol class="proute">
            @forelse ($steps as $i => $stage)
                <li wire:key="pr-{{ $stage->id }}">
                    <span class="proute-n">{{ $i + 1 }}</span>
                    <div class="proute-main"><strong>{{ $stage->name }}</strong><span class="proute-tag">{{ $kinds[$stage->kind] ?? $stage->kind }}</span>@if ($stage->is_quality_gate)<span class="proute-tag">Quality gate</span>@endif</div>
                    @if ($canEdit)
                        <div class="proute-actions">
                            <button class="btn btn-small" type="button" wire:click="move({{ $i }}, -1)" @disabled($i === 0) aria-label="Move {{ $stage->name }} up">↑</button>
                            <button class="btn btn-small" type="button" wire:click="move({{ $i }}, 1)" @disabled($i === $steps->count() - 1) aria-label="Move {{ $stage->name }} down">↓</button>
                            <button class="btn btn-small" type="button" wire:click="remove('{{ $stage->id }}')">Take off</button>
                        </div>
                    @endif
                </li>
            @empty
                <li><span class="muted">No steps: lots of this product go straight from the pour to inspection and packing.</span></li>
            @endforelse
        </ol>
        @if ($canEdit && $library->isNotEmpty())
            <div class="panel-header"><h2 style="font-size:14px">Add a step</h2><span class="muted small">From the step library</span></div>
            <ol class="proute">
                @foreach ($library as $stage)
                    <li wire:key="pl-{{ $stage->id }}">
                        <div class="proute-main">{{ $stage->name }}<span class="proute-tag">{{ $kinds[$stage->kind] ?? $stage->kind }}</span></div>
                        <div class="proute-actions"><button class="btn btn-small" type="button" wire:click="add('{{ $stage->id }}')">Add to route</button></div>
                    </li>
                @endforeach
            </ol>
        @endif
    @endif
</div>
