<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Services\TaxRegister;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxRegisterController extends Controller
{
    /** Output tax on invoices or input tax on bills over a period, per authority and line by line; also as a CSV for the return. */
    public function index(Request $request, TaxRegister $register): View|StreamedResponse
    {
        $side = $request->query('side') === 'purchases' ? 'purchases' : 'sales';
        $from = ($request->date('from') ?? today()->startOfMonth())->toDateString();
        $to = max($from, ($request->date('to') ?? today()->endOfMonth())->toDateString());
        $authority = $request->filled('authority') ? (string) $request->query('authority') : null;
        $all = $register->rows($side, $from, $to);
        $rows = $authority === null ? $all : $all->filter(fn ($r) => ($r['authority'] ?? '') === ($authority === '-' ? '' : $authority))->values();

        if ($request->query('export') === 'csv') {
            return $this->csv("tax-register-{$side}-{$from}-to-{$to}.csv", $rows->all());
        }

        return view('finance::tax-register.index', [
            'side' => $side, 'from' => $from, 'to' => $to, 'authority' => $authority, 'rows' => $rows, 'summary' => $register->summary($rows),
            'authorities' => $all->pluck('authority')->map(fn ($a) => $a ?? '-')->unique()->sort()->values(),
        ]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function csv(string $name, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Date', 'Document', 'Kind', 'Party', 'Registration numbers', 'Tariff code', 'Tax', 'Authority', 'Rate %', 'Charged or withheld', 'Value excluding tax', 'Tax amount']);
            foreach ($rows as $r) {
                $regs = collect($r['registrations'])->map(fn ($v, $k) => "{$k}: {$v}")->implode('; ');
                $line = [$r['date'], $r['number'], $r['kind'], $r['party'], $regs, $r['tariff'], $r['label'], $r['authority'], $r['rate'], $r['withheld'] ? 'Withheld' : 'Charged', $r['base'], $r['amount']];
                fputcsv($f, array_map(fn ($v) => is_string($v) && preg_match('/^[=+@-]/', $v) ? "'".$v : $v, $line));
            }
            fclose($f);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
