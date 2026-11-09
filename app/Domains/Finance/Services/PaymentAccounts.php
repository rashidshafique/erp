<?php

namespace App\Domains\Finance\Services;

use App\Domains\Core\ModuleRegistry;
use App\Domains\Ledger\Models\BankAccount;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\Vouchers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * Where money comes in or goes out on a receipt or supplier payment (design 24d "Into"): a cash or bank account of the general
 * ledger, picked on the form. The method (cash, cheque, transfer…) is only a description, with the instrument number beside it.
 * Without the ledger there are no accounts and the field is left off.
 */
class PaymentAccounts
{
    /** Methods offered on the form; anything else can be typed. */
    public const METHODS = ['Cash', 'Cheque', 'Bank transfer', 'IBFT', 'RTGS', 'Pay order', 'Demand draft', 'Card', 'Mobile wallet'];

    public static function enabled(): bool
    {
        return app(ModuleRegistry::class)->isEnabled('ledger');
    }

    /**
     * The cash and bank accounts money can go through, banks with their bank details.
     *
     * @return Collection<int, array{id: string, label: string, cash: bool}>
     */
    public static function options(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }
        $banks = BankAccount::where('is_active', true)->get()->keyBy('account_id');
        $extra = LedgerAccount::postable()->whereIn('id', $banks->keys())->get();

        return Vouchers::counterAccounts()->merge($extra)->unique('id')->sortBy('code')->values()
            ->map(fn (LedgerAccount $a) => [
                'id' => $a->id, 'cash' => Vouchers::isCash($a),
                'label' => $a->label().(($b = $banks->get($a->id)) && $b->label() !== $a->name ? ' · '.$b->label() : ''),
            ]);
    }

    /**
     * Account id => "22-01-03 Meezan current", for showing where money went.
     *
     * @return array<string, string>
     */
    public static function names(): array
    {
        static $names = null;

        return $names ??= self::enabled() ? LedgerAccount::query()->get(['id', 'code', 'name'])->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all() : [];
    }

    /** The account last used on this table (receipts or supplier_payments), else the first bank, else the first account. */
    public static function preferred(string $table): ?string
    {
        $options = self::options();
        $last = DB::table($table)->whereNotNull('account_id')->orderByDesc('created_at')->value('account_id');

        return $options->firstWhere('id', $last)['id'] ?? $options->firstWhere('cash', false)['id'] ?? $options->first()['id'] ?? null;
    }

    /**
     * The fixed methods followed by any other method already typed on this table, most used first.
     *
     * @return list<string>
     */
    public static function methods(string $table): array
    {
        $used = DB::table($table)->whereNotNull('method')->where('method', '<>', '')
            ->selectRaw('method, count(*) as n')->groupBy('method')->orderByDesc('n')->limit(20)->pluck('method');
        $known = array_map('mb_strtolower', self::METHODS);

        return array_values(array_merge(self::METHODS, $used->reject(fn ($m) => in_array(mb_strtolower(trim($m)), $known, true))->unique()->all()));
    }

    /**
     * Validation for the picked account: one of the options. Left empty (an import, an older client), the ledger fills it from the
     * method when it posts; without the ledger it is dropped.
     *
     * @return list<string|In>
     */
    public static function rule(): array
    {
        return self::enabled() ? ['nullable', 'uuid', Rule::in(self::options()->pluck('id')->all())] : ['exclude'];
    }
}
