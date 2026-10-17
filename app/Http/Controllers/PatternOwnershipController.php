<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Pattern;
use App\Services\Patterns\PatternOwnership;
use App\Support\PatternOwnershipRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Who owns each pattern and where it is: the owner and custody form, paying off, handing back, scrapping, idle patterns. */
class PatternOwnershipController extends Controller
{
    public function __construct(private PatternOwnership $ownership) {}

    /** Patterns held by owner, idle ones to ask customers about, and the ownership rules. */
    public function index(Request $request): View
    {
        $customerId = $request->input('customer');

        return view('pattern-ownership.index', [
            'byOwner' => Pattern::selectRaw('owner_type, owner_customer_id, count(*) n, sum(insured_value) value')->groupBy('owner_type', 'owner_customer_id')->get(),
            'customers' => Customer::withTrashed()->whereIn('id', Pattern::whereNotNull('owner_customer_id')->select('owner_customer_id'))->orderBy('name')->get()->keyBy('id'),
            'idle' => $this->ownership->idle($customerId),
            'payingOff' => Pattern::with('ownerCustomer')->where('owner_type', 'PAYING_OFF')->orderBy('code')->get()->map(fn ($p) => ['pattern' => $p] + $this->ownership->payOffProgress($p)),
            'away' => Pattern::with('ownerCustomer')->whereIn('custody', ['PATTERN_SHOP', 'LENT'])->orderBy('code')->get(),
            'rules' => collect(PatternOwnershipRules::RULES)->map(fn ($r, $k) => PatternOwnershipRules::get($k))->all(),
            'customerId' => $customerId,
        ]);
    }

    public function update(Request $request, Pattern $pattern): RedirectResponse
    {
        $data = $request->validate([
            'owner_type' => ['required', Rule::in(array_keys(Pattern::OWNERS))],
            'owner_customer_id' => ['required_unless:owner_type,FOUNDRY', 'nullable', 'uuid', Rule::exists('customers', 'id')],
            'supplied_by_customer' => ['nullable', 'boolean'],
            'ownership_quotation_id' => ['nullable', 'uuid', Rule::exists('quotations', 'id')],
            'ownership_reference' => ['nullable', 'string', 'max:100'],
            'acquired_on' => ['nullable', 'date'],
            'insured_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'amortise_qty' => ['required_if:owner_type,PAYING_OFF', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'ownership_notes' => ['nullable', 'string', 'max:2000'],
            'custody' => ['required', Rule::in(['IN_STORE', 'PATTERN_SHOP', 'LENT'])],
            'custody_location' => ['required_if:custody,LENT', 'nullable', 'string', 'max:150'],
            'event_reference' => ['nullable', 'string', 'max:100'],
            'event_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'owner_customer_id.required_unless' => 'Choose the customer who owns the pattern.',
            'amortise_qty.required_if' => 'Enter how many castings pay the pattern off.',
            'custody_location.required_if' => 'Say who the pattern is lent to.',
        ], ['custody' => 'where it is']);
        if (in_array($pattern->custody, ['RETURNED', 'SCRAPPED'], true)) {
            return back()->withErrors(['custody' => "{$pattern->code} is ".strtolower(Pattern::CUSTODIES[$pattern->custody]).'.']);
        }
        $data['supplied_by_customer'] = $request->boolean('supplied_by_customer');
        if ($data['owner_type'] !== 'PAYING_OFF') {
            $data['amortise_qty'] = null;
        }
        DB::transaction(fn () => $this->ownership->update($pattern, $data, $data['event_reference'] ?? null, $data['event_notes'] ?? null));

        return back()->with('success', "Ownership of {$pattern->code} saved.");
    }

    public function payOff(Request $request, Pattern $pattern): RedirectResponse
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(fn () => $this->ownership->payOff($pattern, $data['reference'] ?? null, $data['notes'] ?? null));

        return back()->with('success', "{$pattern->code} is paid off and now belongs to {$pattern->ownerCustomer?->name}.");
    }

    public function returnToCustomer(Request $request, Pattern $pattern): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:2000']],
            ['reference.required' => 'Enter the delivery note or gate pass the pattern left on.']);
        DB::transaction(fn () => $this->ownership->returnToCustomer($pattern, $data['reference'], $data['notes'] ?? null));

        return redirect()->route('patterns.show', $pattern)->with('success', "{$pattern->code} returned to {$pattern->ownerCustomer?->name} and archived.");
    }

    public function scrap(Request $request, Pattern $pattern): RedirectResponse
    {
        $data = $request->validate([
            'reference' => [$pattern->isCustomers() ? 'required' : 'nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['reference.required' => "Enter the reference of the customer's written consent to scrap their pattern."]);
        $waiting = DB::transaction(fn () => $this->ownership->scrap($pattern, $data['reference'] ?? '', $data['notes'] ?? null));
        if ($waiting) {
            return back()->with('warning', $waiting);
        }

        return redirect()->route('patterns.show', $pattern)->with('success', "{$pattern->code} scrapped and archived.");
    }

    public function rules(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (PatternOwnershipRules::RULES as $key => [, , $options]) {
            $rules[$key] = $options === null ? ['required', 'numeric', 'min:0', 'max:1000000'] : ['required', Rule::in(array_keys($options))];
        }
        foreach ($request->validate($rules) as $key => $value) {
            PatternOwnershipRules::set($key, (string) $value);
        }

        return back()->with('success', 'Pattern ownership rules saved.');
    }
}
