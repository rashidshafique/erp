<?php

namespace App\Domains\Ledger\Http\Controllers;

use App\Domains\Ledger\Models\LedgerAccount;
use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The chart of accounts (design 19a) and its sections list (19m); both are Livewire screens. Export writes the chart as CSV. */
class AccountController extends Controller
{
    public function index(): View
    {
        return view('ledger::accounts.index');
    }

    public function sections(): View
    {
        return view('ledger::accounts.sections');
    }

    public function export(): StreamedResponse
    {
        $tree = LedgerAccount::tree(today()->toDateString());

        return response()->streamDownload(function () use ($tree) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Name', 'Level', 'Report line', 'Description', 'Control', 'Deduction and charge', 'Active', 'Balance '.today()->toDateString(), 'Dr/Cr']);
            foreach ($tree as $a) {
                $level = match (strlen($a->code)) { 2 => 'Category', 5 => 'Section', default => 'Account' };
                fputcsv($out, [$a->code, $a->name, $level, $a->report_line, $a->description, $a->control, $a->is_charge ? 'Yes' : '', $a->is_active ? 'Yes' : 'No', number_format(abs($a->net), 2, '.', ''), $a->net > 0 ? 'Dr' : ($a->net < 0 ? 'Cr' : '')]);
            }
            fclose($out);
        }, 'chart-of-accounts-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }
}
