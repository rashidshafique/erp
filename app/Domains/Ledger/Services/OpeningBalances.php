<?php

namespace App\Domains\Ledger\Services;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\ReceiptAllocation;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPaymentAllocation;
use App\Domains\Finance\Models\WithholdingCertificateAllocation;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Models\AppSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Opening balances at go-live (design 19f). Receivables and payables come from the invoices and bills dated before go-live that
 * are still open on that day, per customer and supplier; every other account is typed. The difference sits in opening balance
 * equity until it is moved by a JV. Locking posts it all as one opening voucher (OB-26-0001) dated the day before go-live.
 * Once a go-live date is set, documents dated before it stay out of the ledger: their open part is in the opening voucher.
 */
class OpeningBalances
{
    public const GO_LIVE = 'ledger.go_live';

    public function __construct(private Ledger $ledger) {}

    public static function goLive(): ?string
    {
        return AppSetting::getValue(self::GO_LIVE) ?: null;
    }

    public static function setGoLive(string $date): void
    {
        AppSetting::setValue(self::GO_LIVE, Carbon::parse($date)->toDateString(), 'The day the books start in this system; documents before it are in the opening balances');
    }

    /** Documents dated before go-live are carried in the opening voucher, not posted one by one. */
    public static function beforeGoLive(string $date): bool
    {
        $live = self::goLive();

        return $live !== null && Carbon::parse($date)->toDateString() < $live;
    }

    public function journal(): ?Journal
    {
        return Journal::where('voucher_type', 'OB')->where('kind', 'POST')->whereDoesntHave('reversal')->latest()->first();
    }

    /** @return Collection<int, \App\Domains\Ledger\Models\OpeningBalance> */
    public function typed(): Collection
    {
        return \App\Domains\Ledger\Models\OpeningBalance::with('account')->get()->sortBy(fn ($b) => $b->account->code)->values();
    }

    /**
     * Receivables and payables open on go-live, per customer and supplier, from documents dated before it that never reached the ledger.
     *
     * @return array{receivables: array{account: LedgerAccount, count: int, parties: array<string, float>}, payables: array{account: LedgerAccount, count: int, parties: array<string, float>}}
     */
    public function control(string $goLive): array
    {
        $posted = fn (string $type) => Journal::where('source_type', $type)->where('kind', 'POST')->select('source_id');
        $customers = [];
        $count = 0;
        foreach (Invoice::where('status', 'ISSUED')->where('kind', '<>', 'CREDIT_NOTE')->where('invoice_date', '<', $goLive)->whereNotIn('id', $posted('invoice'))->get() as $inv) {
            $paid = (float) ReceiptAllocation::where('invoice_id', $inv->id)->whereHas('receipt', fn ($q) => $q->where('received_on', '<', $goLive))->sum('amount');
            $credited = (float) $inv->creditNotes()->where('status', 'ISSUED')->where('invoice_date', '<', $goLive)->sum('total');
            $settled = (float) WithholdingCertificateAllocation::where('invoice_id', $inv->id)->whereHas('certificate', fn ($q) => $q->where('status', 'ACTIVE')->where('certificate_date', '<', $goLive))->sum('amount');
            $open = round((float) $inv->total - $paid - $credited - $settled, 2);
            if ($open != 0.0) {
                $customers[$inv->customer_id] = round(($customers[$inv->customer_id] ?? 0) + $open, 2);
                $count++;
            }
        }
        $suppliers = [];
        $bills = 0;
        foreach (SupplierBill::where('status', 'POSTED')->where('kind', '<>', 'CREDIT_NOTE')->where('bill_date', '<', $goLive)->whereNotIn('id', $posted('supplier_bill'))->get() as $bill) {
            $paid = (float) SupplierPaymentAllocation::where('supplier_bill_id', $bill->id)->whereHas('payment', fn ($q) => $q->where('paid_on', '<', $goLive))->sum('amount');
            $credited = (float) $bill->creditNotes()->where('status', 'POSTED')->where('bill_date', '<', $goLive)->sum(DB::raw('total - tax_withheld'));
            $open = round($bill->payableToSupplier() - $paid - $credited, 2);
            if ($open != 0.0) {
                $suppliers[$bill->supplier_id] = round(($suppliers[$bill->supplier_id] ?? 0) + $open, 2);
                $bills++;
            }
        }

        return [
            'receivables' => ['account' => LedgerSettings::account('receivables'), 'count' => $count, 'parties' => $customers],
            'payables' => ['account' => LedgerSettings::account('payables'), 'count' => $bills, 'parties' => $suppliers],
        ];
    }

    /**
     * Every line of the opening voucher: typed balances, receivables and payables from open documents, and the difference.
     *
     * @return array{rows: list<array{account: LedgerAccount, debit: float, credit: float, source: string, kind: string}>, lines: list<array<string, mixed>>, difference: float, debit: float, credit: float, control: array<string, mixed>}
     */
    public function draft(string $goLive): array
    {
        $control = $this->control($goLive);
        $rows = [];
        $lines = [];
        foreach ($this->typed() as $b) {
            $rows[] = ['account' => $b->account, 'debit' => (float) $b->debit, 'credit' => (float) $b->credit, 'source' => $b->note ? 'Typed · '.$b->note : 'Typed', 'kind' => 'typed', 'id' => $b->id];
            $lines[] = ['account_id' => $b->account_id, 'debit' => (float) $b->debit, 'credit' => (float) $b->credit, 'description' => 'Opening balance'];
        }
        foreach (['receivables' => ['customer', 'open sales invoices', 'debit'], 'payables' => ['supplier', 'open supplier invoices', 'credit']] as $key => [$party, $what, $side]) {
            $c = $control[$key];
            $total = round(array_sum($c['parties']), 2);
            if ($c['count'] === 0) {
                continue;
            }
            $net = $side === 'debit' ? $total : -$total;
            $rows[] = ['account' => $c['account'], 'debit' => max($net, 0), 'credit' => max(-$net, 0), 'source' => $c['count'].' '.$what, 'kind' => $key, 'id' => null];
            foreach ($c['parties'] as $id => $amount) {
                $v = $side === 'debit' ? $amount : -$amount;
                $lines[] = ['account_id' => $c['account']->id, 'debit' => max($v, 0), 'credit' => max(-$v, 0), 'party_type' => $party, 'party_id' => $id, 'description' => 'Open at go-live'];
            }
        }
        $debit = round(array_sum(array_column($rows, 'debit')), 2);
        $credit = round(array_sum(array_column($rows, 'credit')), 2);
        $difference = round($debit - $credit, 2);
        if ($difference != 0.0) {
            $equity = LedgerSettings::account('opening_equity');
            $rows[] = ['account' => $equity, 'debit' => max(-$difference, 0), 'credit' => max($difference, 0), 'source' => 'Difference, posted here until you move it', 'kind' => 'difference', 'id' => null];
            $lines[] = ['account_id' => $equity->id, 'debit' => max(-$difference, 0), 'credit' => max($difference, 0), 'description' => 'Opening difference'];
        }

        return ['rows' => $rows, 'lines' => $lines, 'difference' => $difference, 'debit' => max($debit, $credit), 'credit' => max($debit, $credit), 'control' => $control];
    }

    /** Sets or clears one typed balance. Control accounts and the opening equity account cannot be typed. */
    public function type(string $accountId, float $debit, float $credit, ?string $note = null): void
    {
        $account = LedgerAccount::postable()->findOrFail($accountId);
        if ($account->control || $account->id === LedgerSettings::account('opening_equity')->id) {
            throw new InvalidArgumentException("{$account->code} {$account->name} comes from its documents and cannot be typed.");
        }
        if ($debit < 0 || $credit < 0) {
            throw new InvalidArgumentException('Amounts cannot be below zero.');
        }
        $net = round($debit - $credit, 2);
        if ($net == 0.0) {
            \App\Domains\Ledger\Models\OpeningBalance::where('account_id', $account->id)->delete();

            return;
        }
        \App\Domains\Ledger\Models\OpeningBalance::updateOrCreate(['account_id' => $account->id], ['debit' => max($net, 0), 'credit' => max(-$net, 0), 'note' => $note]);
    }

    /**
     * Reads a CSV of code, debit, credit (a header row is skipped) into typed balances.
     *
     * @return array{done: int, skipped: list<string>}
     */
    public function import(string $path): array
    {
        $done = 0;
        $skipped = [];
        $h = fopen($path, 'r');
        while (($row = fgetcsv($h)) !== false) {
            $code = trim((string) ($row[0] ?? ''));
            if ($code === '' || ! preg_match('/^\d\d-\d\d-\d\d$/', $code)) {
                if ($code !== '' && ! Str::contains(Str::lower($code), 'code')) {
                    $skipped[] = $code.': not an account code';
                }

                continue;
            }
            $account = LedgerAccount::where('code', $code)->first();
            if (! $account) {
                $skipped[] = $code.': no such account';

                continue;
            }
            try {
                $this->type($account->id, (float) str_replace(',', '', $row[1] ?? '0'), (float) str_replace(',', '', $row[2] ?? '0'), 'imported');
                $done++;
            } catch (InvalidArgumentException $e) {
                $skipped[] = $code.': '.$e->getMessage();
            }
        }
        fclose($h);

        return ['done' => $done, 'skipped' => $skipped];
    }

    /** Posts the opening voucher, dated the day before go-live. */
    public function lock(): Journal
    {
        $goLive = self::goLive() ?? throw new InvalidArgumentException('Set the go-live date first.');
        if ($this->journal()) {
            throw new InvalidArgumentException('The opening balances are already locked.');
        }
        $draft = $this->draft($goLive);
        if (count($draft['lines']) < 2) {
            throw new InvalidArgumentException('There is nothing to lock yet.');
        }

        return DB::transaction(fn () => $this->ledger->post(Carbon::parse($goLive)->subDay()->toDateString(), $draft['lines'], null, 'Opening balances at go-live', voucher: [
            'type' => 'OB', 'number' => Vouchers::next('OB', $goLive),
        ]));
    }
}
