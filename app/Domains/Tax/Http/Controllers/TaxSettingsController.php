<?php

namespace App\Domains\Tax\Http\Controllers;

use App\Domains\Core\Tax\TaxJurisdictions;
use App\Domains\Core\Tax\TaxLine;
use App\Domains\Tax\Models\TaxAuthority;
use App\Domains\Tax\Models\TaxComponent;
use App\Domains\Tax\Models\TaxRule;
use App\Domains\Tax\Services\TaxRules;
use App\Domains\Tax\Services\TaxSetup;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\Stock\InventoryClass;
use App\Support\TaxJurisdiction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** The Taxation settings: the country, the company's registrations, the authorities, the dated tax rates and the rules that pick them. */
class TaxSettingsController extends Controller
{
    public function index(TaxJurisdictions $packs): View
    {
        $code = TaxJurisdiction::code();
        $components = TaxComponent::with('authority')->where('jurisdiction', $code)->orderBy('code')->orderByDesc('effective_from')->get();

        return view('tax::settings.index', [
            'code' => $code,
            'countries' => $packs->options(),
            'pack' => $packs->find($code),
            'company' => TaxSetup::company(),
            'authorities' => TaxAuthority::where('jurisdiction', $code)->orderBy('level')->orderBy('code')->get(),
            'current' => $components->filter(fn ($c) => $c->effective_from->lte(today()) && ($c->effective_to === null || $c->effective_to->gte(today())))->values(),
            'scheduled' => $components->filter(fn ($c) => $c->effective_from->gt(today()))->values(),
            'componentCodes' => $components->pluck('name', 'code')->all(),
            'rules' => TaxRule::where('jurisdiction', $code)->orderBy('priority')->orderBy('name')->get(),
            'inventoryClasses' => InventoryClass::options(),
        ]);
    }

    public function updateCompany(Request $request, TaxJurisdictions $packs): RedirectResponse
    {
        $d = $request->validate([
            'jurisdiction' => ['required', Rule::in(array_keys($packs->options()))],
            'region' => ['nullable', 'string', 'max:10'],
            'registrations' => ['nullable', 'array'],
            'registrations.*' => ['nullable', 'string', 'max:60'],
            'registered' => ['nullable', 'array'],
        ]);
        AppSetting::setValue(TaxJurisdiction::SETTING, $d['jurisdiction'], 'The country whose tax rules apply');
        TaxSetup::saveCompany($d['region'] ?? null, $d['registrations'] ?? []);
        TaxAuthority::where('jurisdiction', $d['jurisdiction'])->get()
            ->each(fn (TaxAuthority $a) => $a->update(['is_registered' => in_array($a->id, $d['registered'] ?? [], true)]));

        return back()->with('success', 'Tax registration saved.');
    }

    public function updateChecks(Request $request): RedirectResponse
    {
        $d = $request->validate(['checks' => ['required', 'array'], 'checks.*' => [Rule::in(array_keys(TaxRules::LEVELS))]]);
        foreach (array_intersect_key($d['checks'], TaxRules::RULES) as $rule => $level) {
            TaxRules::set($rule, $level);
        }

        return back()->with('success', 'Checks saved.');
    }

    public function loadDefaults(Request $request, TaxSetup $setup): RedirectResponse
    {
        $pack = TaxJurisdiction::pack();
        abort_if($pack === null, 404);
        try {
            $setup->loadDefaults($pack, $request->boolean('replace'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['defaults' => $e->getMessage()]);
        }

        return back()->with('success', "{$pack->name()}'s starting taxes and rules are loaded. Check each rate before you invoice.");
    }

    public function changeRate(Request $request, TaxComponent $component, TaxSetup $setup): RedirectResponse
    {
        $d = $request->validate(['rate' => ['required', 'numeric', 'min:0', 'max:100'], 'effective_from' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:300']]);
        try {
            $setup->changeRate($component, (float) $d['rate'], Carbon::parse($d['effective_from']), $d['note'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['rate' => $e->getMessage()]);
        }

        return back()->with('success', "{$component->name} is ".rtrim(rtrim(number_format((float) $d['rate'], 3), '0'), '.')."% from {$d['effective_from']}.");
    }

    public function storeRule(Request $request): RedirectResponse
    {
        TaxRule::create(['jurisdiction' => TaxJurisdiction::code()] + $this->validatedRule($request));

        return back()->with('success', 'Rule added.');
    }

    public function updateRule(Request $request, TaxRule $rule): RedirectResponse
    {
        $rule->update($this->validatedRule($request));

        return back()->with('success', 'Rule saved.');
    }

    public function destroyRule(TaxRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', 'Rule removed.');
    }

    /** @return array<string, mixed> */
    private function validatedRule(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'priority' => ['required', 'integer', 'min:0', 'max:9999'],
            'side' => ['required', Rule::in(array_keys(TaxRule::SIDES))],
            'supply_type' => ['nullable', Rule::in([TaxLine::GOODS, TaxLine::SERVICE])],
            'regions' => ['nullable', 'array'],
            'regions.*' => ['string', 'max:10'],
            'party_status' => ['nullable', 'array'],
            'party_status.*' => ['string', 'max:30'],
            'withholding_agent' => ['nullable', Rule::in(['', '1', '0'])],
            'inventory_classes' => ['nullable', 'array'],
            'inventory_classes.*' => [Rule::enum(InventoryClass::class)],
            'code_prefix' => ['nullable', 'string', 'max:20'],
            'components' => ['required', 'array', 'min:1'],
            'components.*' => ['string', 'max:30'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $conditions = array_filter([
            'supply_type' => $d['supply_type'] ?? null,
            'regions' => array_values($d['regions'] ?? []),
            'party_status' => array_values($d['party_status'] ?? []),
            'withholding_agent' => ($d['withholding_agent'] ?? '') === '' ? null : $d['withholding_agent'] === '1',
            'inventory_classes' => array_values($d['inventory_classes'] ?? []),
            'code_prefix' => filled($d['code_prefix'] ?? null) ? preg_replace('/[\s.]/', '', $d['code_prefix']) : null,
        ], fn ($v) => $v !== null && $v !== []);

        return [
            'name' => trim($d['name']), 'priority' => (int) $d['priority'], 'side' => $d['side'], 'conditions' => $conditions,
            'components' => array_values(array_unique($d['components'])), 'effective_from' => $d['effective_from'] ?? null,
            'effective_to' => $d['effective_to'] ?? null, 'is_active' => $request->boolean('is_active'),
        ];
    }
}
