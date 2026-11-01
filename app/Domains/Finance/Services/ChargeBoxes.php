<?php

namespace App\Domains\Finance\Services;

use App\Domains\Core\ModuleRegistry;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Models\AppSetting;
use Illuminate\Support\Collection;

/**
 * Charge and deduction boxes on sales documents (design 19n, 19o): how many of each appear and on which documents. Each box offers
 * the ledger accounts ticked "Deduction and charge" in the chart; a charge is added after tax and credits its account, a deduction
 * is taken off and debits it, and receivables take the net. Without the general ledger there are no accounts, so no boxes.
 */
class ChargeBoxes
{
    public const CHARGES = 'sales.charge_boxes';

    public const DEDUCTIONS = 'sales.deduction_boxes';

    public const DOCUMENTS = 'sales.charge_documents';

    /** @var array<string, string> document key => label, in the order the settings show them */
    public const DOCUMENT_LABELS = ['invoice' => 'Sales invoices', 'credit_note' => 'Credit notes', 'sales_order' => 'Sales orders', 'quote' => 'Quotes'];

    public const MAX = 3;

    public static function charges(): int
    {
        return max(0, min(self::MAX, (int) AppSetting::getValue(self::CHARGES, '2')));
    }

    public static function deductions(): int
    {
        return max(0, min(self::MAX, (int) AppSetting::getValue(self::DEDUCTIONS, '1')));
    }

    /** @return list<string> */
    public static function documents(): array
    {
        $saved = json_decode((string) AppSetting::getValue(self::DOCUMENTS, ''), true);

        return is_array($saved) ? array_values(array_intersect(array_keys(self::DOCUMENT_LABELS), $saved)) : ['invoice', 'credit_note', 'sales_order'];
    }

    /** @param list<string> $documents */
    public static function save(int $charges, int $deductions, array $documents): void
    {
        AppSetting::setValue(self::CHARGES, (string) max(0, min(self::MAX, $charges)), 'Charge boxes on sales documents');
        AppSetting::setValue(self::DEDUCTIONS, (string) max(0, min(self::MAX, $deductions)), 'Deduction boxes on sales documents');
        AppSetting::setValue(self::DOCUMENTS, json_encode(array_values(array_intersect(array_keys(self::DOCUMENT_LABELS), $documents))), 'Sales documents that show the charge and deduction boxes');
    }

    public static function ledgerOn(): bool
    {
        return class_exists(LedgerAccount::class) && app(ModuleRegistry::class)->isEnabled('ledger');
    }

    /** Whether a document shows the boxes: switched on for it, at least one box, and accounts to offer. */
    public static function showsOn(string $document): bool
    {
        return in_array($document, self::documents(), true) && self::charges() + self::deductions() > 0 && self::accounts()->isNotEmpty();
    }

    /** @return Collection<int, LedgerAccount> active accounts ticked "Deduction and charge" */
    public static function accounts(): Collection
    {
        return self::ledgerOn() ? LedgerAccount::postable()->where('is_charge', true)->with('parent')->orderBy('code')->get() : collect();
    }

    /** "Charge" for revenue and other income accounts, "Deduction" for sales deductions and expenses. Any ticked account can go in either box. */
    public static function usedAs(LedgerAccount $account): string
    {
        return $account->type === 'EXPENSE' || $account->parent?->report_line === 'Revenue deductions' ? 'Deduction' : 'Charge';
    }

    /**
     * Keeps the boxes that name an offered account and an amount above zero, with the account's code and name kept for the print.
     *
     * @param  array<int, array<string, mixed>>  $boxes
     * @return list<array{kind: string, account_id: string, code: ?string, name: string, amount: float}>
     */
    public static function clean(array $boxes): array
    {
        if ($boxes === [] || ! self::ledgerOn()) {
            return [];
        }
        $accounts = self::accounts()->keyBy('id');
        $out = [];
        foreach ($boxes as $b) {
            $amount = round((float) ($b['amount'] ?? 0), 2);
            $account = $accounts[$b['account_id'] ?? ''] ?? null;
            if ($amount <= 0 || ! $account || ! in_array($b['kind'] ?? '', ['charge', 'deduction'], true)) {
                continue;
            }
            $out[] = ['kind' => $b['kind'], 'account_id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'amount' => $amount];
        }

        return $out;
    }
}
