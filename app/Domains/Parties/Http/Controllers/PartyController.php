<?php

namespace App\Domains\Parties\Http\Controllers;

use App\Domains\Parties\Models\Party;
use App\Domains\Purchasing\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PartyController extends Controller
{
    public function index(Request $request): View
    {
        $parties = Party::query()->with(['customer', 'supplier'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('code', 'ilike', '%'.trim($request->query('q')).'%')->orWhere('name', 'ilike', '%'.trim($request->query('q')).'%')))
            ->when($request->query('role') === 'customer', fn ($q) => $q->has('customer'))
            ->when($request->query('role') === 'supplier', fn ($q) => $q->has('supplier'))
            ->when($request->query('role') === 'both', fn ($q) => $q->has('customer')->has('supplier'))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('parties::index', ['parties' => $parties]);
    }

    public function create(): View
    {
        return view('parties::form', ['party' => new Party(['active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $party = Party::create($this->validated($request) + ['active' => true]);
        foreach (array_keys(Party::ROLES) as $role) {
            if ($request->boolean("role_{$role}")) {
                $this->giveRole($party, $role);
            }
        }

        return redirect()->route('parties.show', $party)->with('success', "{$party->code} added.");
    }

    public function show(Party $party): View
    {
        $party->load(['customer', 'supplier']);

        return view('parties::show', ['party' => $party, 'roles' => Party::ROLES]);
    }

    public function edit(Party $party): View
    {
        return view('parties::form', ['party' => $party]);
    }

    public function update(Request $request, Party $party): RedirectResponse
    {
        $party->update($this->validated($request, $party) + ['active' => $request->boolean('active')]);

        return redirect()->route('parties.show', $party)->with('success', "{$party->code} saved.");
    }

    public function addRole(Party $party, string $role): RedirectResponse
    {
        $this->giveRole($party, $role);

        return redirect()->route('parties.show', $party)->with('success', "{$party->name} is now a ".strtolower(Party::ROLES[$role]).'.');
    }

    /** Creates the role record for a party that does not hold it yet. A supplier starts pending approval. */
    private function giveRole(Party $party, string $role): void
    {
        if ($party->{$role}()->exists()) {
            return;
        }
        if ($role === 'customer') {
            $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($party->name)), 0, 4)) ?: 'CUST';
            $code = $base;
            for ($n = 1; Customer::withTrashed()->where('code', $code)->exists(); $n++) {
                $code = $base.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            }
            Customer::create(['party_id' => $party->id, 'code' => $code]);
        } else {
            Supplier::create(['party_id' => $party->id, 'approval_status' => 'PENDING', 'review_interval_days' => 365]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Party $party = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:160'],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:60'],
        ]);
    }
}
