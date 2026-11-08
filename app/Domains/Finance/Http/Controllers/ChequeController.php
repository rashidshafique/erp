<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Models\Cheque;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Services\ChequeService;
use App\Domains\Finance\Services\FinanceRules;
use App\Domains\Finance\Services\PaymentAccounts;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** The post-dated cheque registers: received from customers (Sales) and given to suppliers (Purchases). */
class ChequeController extends Controller
{
    /** Tabs of a register: what each shows. */
    public const TABS = ['due' => 'Due', 'later' => 'Not due yet', 'deposited' => 'Deposited', 'cleared' => 'Cleared', 'bounced' => 'Bounced'];

    public function index(Request $request, string $direction): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'due';
        $base = fn () => Cheque::where('direction', $direction);
        $filter = fn ($q, string $t) => match ($t) {
            'due' => $q->where('status', 'IN_HAND')->where('cheque_date', '<=', today()),
            'later' => $q->where('status', 'IN_HAND')->where('cheque_date', '>', today()),
            'deposited' => $q->where('status', 'DEPOSITED'),
            'cleared' => $q->where('status', 'CLEARED'),
            'bounced' => $q->where('status', 'BOUNCED'),
        };
        $counts = collect(self::TABS)->map(fn ($l, $t) => $filter($base(), $t)->count());
        $search = trim((string) $request->query('q'));
        $cheques = $filter($base(), $tab)->with(['customer', 'supplier'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('cheque_number', 'ilike', "%{$search}%")->orWhere('drawn_on', 'ilike', "%{$search}%")
                ->orWhereHas($direction === 'RECEIVED' ? 'customer' : 'supplier', fn ($p) => $p->where('name', 'ilike', "%{$search}%"))))
            ->orderBy($tab === 'cleared' || $tab === 'bounced' ? 'updated_at' : 'cheque_date', $tab === 'cleared' || $tab === 'bounced' ? 'desc' : 'asc')
            ->paginate(25)->withQueryString();
        $open = $base()->whereIn('status', ['IN_HAND', 'DEPOSITED']);

        return view('finance::cheques.index', [
            'direction' => $direction, 'tab' => $tab, 'counts' => $counts, 'cheques' => $cheques, 'search' => $search,
            'strip' => [
                'open' => (float) (clone $open)->sum('amount'), 'openCount' => (clone $open)->count(),
                'due' => (float) (clone $open)->where('cheque_date', '<=', today())->sum('amount'),
                'week' => (float) (clone $open)->whereBetween('cheque_date', [today()->addDay(), today()->addDays(7)])->sum('amount'),
            ],
            'steps' => FinanceRules::chequeSteps(), 'accounts' => PaymentAccounts::options(), 'names' => PaymentAccounts::names(),
        ]);
    }

    public function show(Cheque $cheque): View
    {
        $cheque->load(['customer', 'supplier', 'receipt.allocations.invoice', 'payment.allocations.bill']);
        $planned = collect($cheque->allocations ?? [])->map(fn ($amount, $id) => [
            'doc' => $cheque->isReceived() ? Invoice::find($id) : SupplierBill::find($id), 'amount' => (float) $amount,
        ])->filter(fn ($p) => $p['doc'])->values();

        return view('finance::cheques.show', ['cheque' => $cheque, 'planned' => $planned, 'steps' => FinanceRules::chequeSteps(),
            'accounts' => PaymentAccounts::options(), 'names' => PaymentAccounts::names()]);
    }

    public function deposit(Request $request, Cheque $cheque, ChequeService $service): RedirectResponse
    {
        $d = $request->validate(['date' => ['required', 'date'], 'account_id' => PaymentAccounts::rule()]);

        return $this->act(fn () => $service->deposit($cheque, $d['date'], $d['account_id'] ?? null), 'Cheque deposited.');
    }

    public function clear(Request $request, Cheque $cheque, ChequeService $service): RedirectResponse
    {
        $d = $request->validate(['date' => ['required', 'date'], 'account_id' => PaymentAccounts::rule()]);

        return $this->act(fn () => $service->clear($cheque, $d['date'], $d['account_id'] ?? null), 'Cheque cleared.');
    }

    public function bounce(Request $request, Cheque $cheque, ChequeService $service): RedirectResponse
    {
        $d = $request->validate(['date' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:300']]);

        return $this->act(fn () => $service->bounce($cheque, $d['date'], $d['reason'] ?? null), 'Cheque marked bounced.');
    }

    private function act(\Closure $do, string $done): RedirectResponse
    {
        try {
            $do();
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cheque' => $e->getMessage()]);
        }

        return back()->with('success', $done);
    }
}
