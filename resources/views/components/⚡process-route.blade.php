<?php

use App\Models\ProductionStageDefinition;
use App\Services\Production\ProcessRoutes;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * "Our process": the steps every casting follows after the pour, in order. Steps are moved, added, taken off and edited here,
 * and each change applies at once. A step taken off stays in the library, and on the lots that already passed it.
 */
new class extends Component
{
    public bool $formOpen = false;

    public ?string $editingId = null;

    public ?string $saved = null;

    public ?string $problem = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->form = $this->blankForm();
    }

    /** @return array<string, mixed> */
    private function blankForm(): array
    {
        return ['name' => '', 'kind' => 'PROCESS', 'is_quality_gate' => false, 'knocks_out_mould' => false, 'records_weight' => false, 'description' => ''];
    }

    /** @return list<string> */
    private function order(): array
    {
        return app(ProcessRoutes::class)->defaultSteps()->pluck('stage_definition_id')->all();
    }

    /** @param list<string> $order */
    private function apply(array $order, string $message): void
    {
        try {
            app(ProcessRoutes::class)->saveDefault($order);
            [$this->saved, $this->problem] = [$message, null];
        } catch (InvalidArgumentException $e) {
            [$this->saved, $this->problem] = [null, $e->getMessage()];
        }
    }

    public function move(int $index, int $by): void
    {
        $order = $this->order();
        $to = $index + $by;
        if (! isset($order[$index], $order[$to])) {
            return;
        }
        [$order[$index], $order[$to]] = [$order[$to], $order[$index]];
        $this->apply($order, 'Order saved.');
    }

    public function takeOff(string $stageId): void
    {
        $name = ProductionStageDefinition::find($stageId)?->name;
        $this->apply(array_values(array_diff($this->order(), [$stageId])), "{$name} taken off the process. It stays in the library below.");
    }

    public function putBack(string $stageId): void
    {
        $name = ProductionStageDefinition::find($stageId)?->name;
        $this->apply([...$this->order(), $stageId], "{$name} added at the end. Move it into place.");
    }

    public function create(): void
    {
        $this->resetValidation();
        [$this->editingId, $this->form, $this->formOpen] = [null, $this->blankForm(), true];
    }

    public function edit(string $stageId): void
    {
        $stage = ProductionStageDefinition::findOrFail($stageId);
        $this->resetValidation();
        $this->editingId = $stage->id;
        $this->form = ['name' => $stage->name, 'kind' => $stage->kind, 'is_quality_gate' => $stage->is_quality_gate, 'knocks_out_mould' => $stage->knocks_out_mould, 'records_weight' => $stage->records_weight, 'description' => (string) $stage->description];
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, Closure $fail) {
                $taken = ProductionStageDefinition::whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                    ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->exists();
                if ($taken) {
                    $fail('There is already a step with this name.');
                }
            }],
            'form.kind' => ['required', Rule::in(array_keys(ProductionStageDefinition::KINDS))],
            'form.is_quality_gate' => ['boolean'], 'form.knocks_out_mould' => ['boolean'], 'form.records_weight' => ['boolean'],
            'form.description' => ['nullable', 'string', 'max:1000'],
        ], [], ['form.name' => 'name', 'form.kind' => 'kind'])['form'];
        $data['description'] = ($data['description'] ?? '') === '' ? null : $data['description'];

        if ($this->editingId) {
            ProductionStageDefinition::whereKey($this->editingId)->update($data);
            $this->apply($this->order(), "{$data['name']} saved.");   // the route keeps the step's name too
        } else {
            app(ProcessRoutes::class)->addStep($data);
            [$this->saved, $this->problem] = ["{$data['name']} added at the end. Move it into place.", null];
        }
        $this->closeForm();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $steps = app(ProcessRoutes::class)->defaultSteps();
        $onRoute = $steps->pluck('stage_definition_id')->all();

        return [
            'steps' => $steps,
            'library' => ProductionStageDefinition::whereNotIn('id', $onRoute)->orderBy('name')->get(),
            'kinds' => ProductionStageDefinition::KINDS,
        ];
    }
};
?>

<div>
@include('partials.quick-dialog-assets')
<style>
    .route { list-style: none; margin: 0; padding: 0; }
    .route li { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-bottom: 1px solid var(--border); }
    .route li.fixed { color: var(--muted); background: #fafbfc; }
    .route-n { flex: 0 0 26px; height: 26px; border-radius: 50%; background: var(--accent); color: #fff; display: grid; place-items: center; font-size: 12px; font-weight: 700; }
    .route li.fixed .route-n { background: var(--border); color: var(--muted); }
    .route-main { flex: 1; min-width: 0; }
    .route-tags { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 2px; }
    .route-tag { font-size: 11px; padding: 1px 7px; border-radius: 999px; background: #eef1f3; color: var(--muted); }
    .route-tag.gate { background: #fff3cd; color: #7a5b00; }
    .route-actions { display: flex; gap: 4px; flex-wrap: wrap; justify-content: flex-end; }
</style>

@if ($saved)<div class="alert alert-success" role="status">{{ $saved }}</div>@endif
@if ($problem)<div class="alert alert-error" role="alert">{{ $problem }}</div>@endif

<div class="panel">
    <div class="panel-header">
        <h2>Steps after the pour</h2>
        <button class="btn btn-primary" type="button" wire:click="create" style="margin-left:auto">+ New step</button>
    </div>
    <ol class="route">
        <li class="fixed"><span class="route-n">·</span><div class="route-main"><strong>Moulding and pouring</strong><div class="small">Recorded on moulding runs and pours. The castings of a pour become lots.</div></div></li>
        @forelse ($steps as $i => $step)
            @php $stage = $step->stage; @endphp
            <li wire:key="step-{{ $stage->id }}">
                <span class="route-n">{{ $i + 1 }}</span>
                <div class="route-main">
                    <strong>{{ $stage->name }}</strong>
                    <div class="route-tags">
                        <span class="route-tag">{{ $kinds[$stage->kind] ?? $stage->kind }}</span>
                        @if ($stage->is_quality_gate)<span class="route-tag gate">Quality gate</span>@endif
                        @if ($stage->knocks_out_mould)<span class="route-tag">Knocks out the mould: cooling check, flasks back</span>@endif
                        @if ($stage->records_weight)<span class="route-tag">Records weight</span>@endif
                    </div>
                </div>
                <div class="route-actions">
                    <button class="btn btn-small" type="button" wire:click="move({{ $i }}, -1)" @disabled($i === 0) aria-label="Move {{ $stage->name }} up">↑</button>
                    <button class="btn btn-small" type="button" wire:click="move({{ $i }}, 1)" @disabled($i === $steps->count() - 1) aria-label="Move {{ $stage->name }} down">↓</button>
                    <button class="btn btn-small" type="button" wire:click="edit('{{ $stage->id }}')">Edit</button>
                    <button class="btn btn-small" type="button" wire:click="takeOff('{{ $stage->id }}')" wire:confirm="Take {{ $stage->name }} off the process? Lots already past it keep their record.">Take off</button>
                </div>
            </li>
        @empty
            <li><span class="muted">No steps: lots go straight from the pour to inspection and packing. Add a step or put one back from the library.</span></li>
        @endforelse
        <li class="fixed"><span class="route-n">·</span><div class="route-main"><strong>Ready to pack, then dispatch</strong><div class="small">A lot is ready once it passes its final inspection or quick QC.</div></div></li>
    </ol>
</div>

@if ($library->isNotEmpty())
<div class="panel">
    <div class="panel-header"><h2>Library</h2><span class="muted small">Steps not in the process now</span></div>
    <ol class="route">
        @foreach ($library as $stage)
            <li wire:key="lib-{{ $stage->id }}">
                <div class="route-main"><strong>{{ $stage->name }}</strong><div class="route-tags"><span class="route-tag">{{ $kinds[$stage->kind] ?? $stage->kind }}</span></div></div>
                <div class="route-actions">
                    <button class="btn btn-small" type="button" wire:click="edit('{{ $stage->id }}')">Edit</button>
                    <button class="btn btn-small" type="button" wire:click="putBack('{{ $stage->id }}')">Add to process</button>
                </div>
            </li>
        @endforeach
    </ol>
</div>
@endif

@if ($formOpen)
<dialog class="qd" aria-labelledby="step-dialog-title" x-data x-init="$el.showModal()" x-on:close="$wire.closeForm()" wire:key="step-dialog">
    <form wire:submit="save">
        <div class="qd-head"><h2 id="step-dialog-title">{{ $editingId ? 'Edit step' : 'New step' }}</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
        <div class="qd-body">
            @if ($errors->any())<div class="alert alert-error qd-errors" role="alert">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
            <div class="qd-fields">
                <div class="field"><label for="st-name">Name <span class="required">*</span></label><input id="st-name" wire:model="form.name" maxlength="100" autocomplete="off" placeholder="e.g. Weighing" @error('form.name') aria-invalid="true" @enderror></div>
                <div class="field"><label for="st-kind">Kind</label><select id="st-kind" wire:model="form.kind">@foreach ($kinds as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <div class="field field-full">
                    <label><input type="checkbox" wire:model="form.is_quality_gate"> Quality gate</label>
                    <label><input type="checkbox" wire:model="form.knocks_out_mould"> Knocks out the mould (cooling time is checked first, flasks go back to the rack)</label>
                    <label><input type="checkbox" wire:model="form.records_weight"> Records weight</label>
                </div>
                <div class="field field-full"><label for="st-desc">Description</label><textarea id="st-desc" rows="2" wire:model="form.description" placeholder="What is done at this step"></textarea></div>
            </div>
            @unless ($editingId)<p class="muted small">New steps go at the end of the process. Move them into place afterwards.</p>@endunless
        </div>
        <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">{{ $editingId ? 'Save step' : 'Add step' }}</button></div>
    </form>
</dialog>
@endif
</div>
