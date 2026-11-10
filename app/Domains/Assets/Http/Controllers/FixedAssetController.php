<?php

namespace App\Domains\Assets\Http\Controllers;

use App\Domains\Assets\Models\AssetClass;
use App\Domains\Assets\Models\AssetEvent;
use App\Domains\Assets\Models\DepreciationRun;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Assets\Services\AssetPosting;
use App\Domains\Assets\Services\Depreciation;
use App\Http\Controllers\Controller;
use App\Models\CoreBox;
use App\Models\Equipment;
use App\Models\Pattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** The register (design 28c), one asset (28d), adding an asset the foundry already has, and putting one in use. */
class FixedAssetController extends Controller
{
    public function index(): View
    {
        return view('assets::assets.index');
    }

    public function create(): View
    {
        return $this->form(new FixedAsset(['status' => 'IN_USE', 'kind' => 'PLANT', 'residual_value' => 0, 'opening_depreciation' => 0, 'opening_fills' => 0]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $asset = DB::transaction(function () use ($data) {
            $asset = FixedAsset::create($data);
            if ($asset->cost > 0) {
                $asset->costs()->create(['source_type' => 'OPENING', 'description' => 'Cost brought in', 'amount' => $asset->cost, 'incurred_on' => $asset->in_use_on ?? today()]);
            }
            AssetEvent::create(['fixed_asset_id' => $asset->id, 'kind' => 'ADDED', 'happened_on' => today(), 'user_id' => auth()->id(),
                'note' => 'Added to the register with its cost'.($asset->opening_depreciation > 0 ? ' and depreciation to date' : '').'. No journal: the opening balances hold them.']);

            return $asset;
        });

        return redirect()->route('assets.show', $asset)->with('success', "{$asset->code} added to the register.");
    }

    public function show(FixedAsset $asset, Depreciation $depreciation): View
    {
        $asset->load(['assetClass.costAccount', 'assetClass.depreciationAccount', 'costs', 'events.journal', 'events.user', 'pattern', 'coreBox', 'equipment', 'createdBy']);
        $months = $asset->runLines()->with('run')->whereHas('run', fn ($q) => $q->where('status', DepreciationRun::POSTED))->get()
            ->sortByDesc(fn ($l) => $l->run->period)->values();
        $last = DepreciationRun::where('status', DepreciationRun::POSTED)->max('period');
        $next = $last ? Carbon::parse($last)->addMonthNoOverflow()->startOfMonth() : today()->startOfMonth();
        $pending = $next->lte(today()) ? $depreciation->forMonth($next, [$asset->id])->first() : null;
        $development = $asset->pattern_development_id ? DB::table('pattern_developments')->where('id', $asset->pattern_development_id)->first(['id', 'code']) : null;

        return view('assets::assets.show', [
            'asset' => $asset, 'months' => $months, 'pending' => $pending, 'next' => $next, 'development' => $development,
            'depreciated' => $asset->depreciatedTo(), 'fillsUsed' => $asset->isTool() ? $asset->fillsUsed() : null,
            'rate' => $asset->method === 'USAGE' && $asset->life_fills > 0 ? round(($asset->cost - $asset->residual_value) / $asset->life_fills, 2) : null,
        ]);
    }

    public function edit(FixedAsset $asset): View
    {
        return $this->form($asset);
    }

    public function update(Request $request, FixedAsset $asset): RedirectResponse
    {
        $data = $this->validated($request, $asset);
        $onlyOpening = $asset->costs()->where('source_type', '!=', 'OPENING')->doesntExist();
        if (! $onlyOpening) {
            unset($data['cost']);
        }
        unset($data['status']);   // an asset goes into use through Put in use, which posts it
        DB::transaction(function () use ($asset, $data, $onlyOpening) {
            $asset->update($data);
            if ($onlyOpening && isset($data['cost'])) {
                $asset->costs()->where('source_type', 'OPENING')->delete();
                if ($asset->cost > 0) {
                    $asset->costs()->create(['source_type' => 'OPENING', 'description' => 'Cost brought in', 'amount' => $asset->cost, 'incurred_on' => $asset->in_use_on ?? today()]);
                }
            }
        });

        return redirect()->route('assets.show', $asset)->with('success', "{$asset->code} saved.");
    }

    public function putInUse(Request $request, FixedAsset $asset, AssetPosting $posting): RedirectResponse
    {
        $data = $request->validate(['in_use_on' => ['required', 'date']]);
        try {
            $posting->putInUse($asset, Carbon::parse($data['in_use_on'])->toDateString());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['in_use_on' => $e->getMessage()]);
        }

        return redirect()->route('assets.show', $asset)->with('success', "{$asset->code} is in use from ".Carbon::parse($data['in_use_on'])->displayDate().'; depreciation starts then.');
    }

    private function form(FixedAsset $asset): View
    {
        return view('assets::assets.form', [
            'asset' => $asset, 'classes' => AssetClass::with('costAccount')->orderBy('sort_order')->orderBy('name')->get(),
            'equipment' => Equipment::orderBy('code')->get(['id', 'code', 'name']),
            'patterns' => Pattern::where('owner_type', 'FOUNDRY')->orWhere('id', $asset->pattern_id)->orderBy('code')->get(['id', 'code', 'name', 'amortise_qty']),
            'coreBoxes' => CoreBox::where('owner_type', 'FOUNDRY')->orWhere('id', $asset->core_box_id)->orderBy('code')->get(['id', 'code', 'name']),
            'costLocked' => $asset->exists && $asset->costs()->where('source_type', '!=', 'OPENING')->exists(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?FixedAsset $asset = null): array
    {
        $data = $request->validate([
            'asset_class_id' => ['required', 'uuid', 'exists:asset_classes,id'],
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::in(array_keys(FixedAsset::KINDS))],
            'status' => [$asset ? 'nullable' : 'required', Rule::in(['IN_USE', 'IN_PROGRESS'])],
            'in_use_on' => ['nullable', 'date', Rule::requiredIf(fn () => ($asset ? $asset->status : $request->input('status')) === 'IN_USE')],
            'location' => ['nullable', 'string', 'max:150'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'method' => ['required', Rule::in(array_keys(FixedAsset::METHODS))],
            'life_years' => ['nullable', 'numeric', 'gt:0', 'max:100', 'required_if:method,STRAIGHT_LINE'],
            'rate_percent' => ['nullable', 'numeric', 'gt:0', 'max:100', 'required_if:method,REDUCING_BALANCE'],
            'life_fills' => ['nullable', 'integer', 'min:1', 'required_if:method,USAGE'],
            'opening_depreciation' => ['nullable', 'numeric', 'min:0'],
            'opening_fills' => ['nullable', 'integer', 'min:0'],
            'opening_to' => ['nullable', 'date', 'before_or_equal:today', Rule::requiredIf(fn () => (float) $request->input('opening_depreciation') > 0 || (int) $request->input('opening_fills') > 0)],
            'tax_class' => ['nullable', 'string', 'max:60'],
            'equipment_id' => ['nullable', 'uuid', 'exists:equipment,id'],
            'pattern_id' => ['nullable', 'uuid', 'exists:patterns,id'],
            'core_box_id' => ['nullable', 'uuid', 'exists:core_boxes,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['in_use_on.required' => 'An asset in use needs the date it went into use.', 'opening_to.required' => 'Say the day the depreciation to date runs to.']);
        foreach (['cost', 'residual_value', 'opening_depreciation', 'opening_fills'] as $f) {
            $data[$f] = (float) ($data[$f] ?? 0);
        }
        $data['opening_fills'] = (int) $data['opening_fills'];
        if ($data['residual_value'] + $data['opening_depreciation'] > $data['cost'] && $data['cost'] > 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['opening_depreciation' => 'Depreciation to date and the residual value cannot be more than the cost.']);
        }
        if ($data['opening_depreciation'] <= 0 && $data['opening_fills'] <= 0) {
            $data['opening_to'] = null;
        }
        // only the link that fits the kind is kept: a hidden select still sends what was picked before the kind changed
        $data['pattern_id'] = $data['kind'] === 'PATTERN' ? ($data['pattern_id'] ?? null) : null;
        $data['core_box_id'] = $data['kind'] === 'CORE_BOX' ? ($data['core_box_id'] ?? null) : null;
        $data['equipment_id'] = in_array($data['kind'], ['PATTERN', 'CORE_BOX'], true) ? null : ($data['equipment_id'] ?? null);
        if (($asset ? $asset->status : $data['status']) === 'IN_PROGRESS') {
            $data['in_use_on'] = null;
        }

        return $data;
    }
}
