<?php

namespace App\Services\Production;

use App\Models\MouldingStation;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\ProductionOrder;
use App\Models\QualityDefect;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Turns rows copied from a spreadsheet (tab-separated) into rows of the bulk moulding-run grid. Columns, in order:
 * date, shift, station, pattern, revision, cavities, order, made, rejected, defect, remarks.
 * Trailing columns may be left off. Team and sand are not pasted: they come from the station. Codes are matched without regard to case; a heading row is skipped.
 */
class BulkRunPaste
{
    public const COLUMNS = ['run_date', 'shift', 'station', 'pattern', 'revision', 'cavity_numbers', 'order', 'moulds_made_qty', 'damaged_mould_qty', 'defect', 'notes'];

    /** Grid field each pasted column fills, for error keys. */
    private const FIELD = ['station' => 'moulding_station_id', 'pattern' => 'pattern_id', 'revision' => 'pattern_revision_id', 'order' => 'production_order_id', 'defect' => 'quality_defect_id'];

    /**
     * @return array{rows: array<string, array<string, mixed>>, errors: array<string, string>}
     */
    public function parse(string $text): array
    {
        $rows = [];
        $errors = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($text, "\r\n"));
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = array_map(fn ($c) => trim($c, " \t\"\u{a0}"), explode("\t", $line));
            if ($i === 0 && $this->isHeading($cells)) {
                continue;
            }
            $raw = array_combine(self::COLUMNS, array_pad(array_slice($cells, 0, count(self::COLUMNS)), count(self::COLUMNS), ''));
            $key = 'p'.Str::lower(Str::random(6));
            [$rows[$key], $rowErrors] = $this->row($raw);
            foreach ($rowErrors as $field => $message) {
                $errors["rows.{$key}.{$field}"] = 'Pasted line '.($i + 1).": {$message}";
            }
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /** @param array<int, string> $cells */
    private function isHeading(array $cells): bool
    {
        return isset($cells[0]) && $this->date($cells[0]) === null && preg_match('/date/i', $cells[0]) === 1;
    }

    /**
     * @param  array<string, string>  $raw
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function row(array $raw): array
    {
        $errors = [];
        $row = ['shift' => $raw['shift'] ?: null, 'cavity_numbers' => $raw['cavity_numbers'] ?: null, 'notes' => $raw['notes'] ?: null];

        $row['run_date'] = null;
        if ($raw['run_date'] !== '') {
            $row['run_date'] = $this->date($raw['run_date']);
            if ($row['run_date'] === null) {
                $errors['run_date'] = "“{$raw['run_date']}” is not a date. Use 2026-10-17 or 17/10/2026.";
            }
        }
        foreach (['moulds_made_qty', 'damaged_mould_qty'] as $field) {
            $n = str_replace([',', ' '], '', $raw[$field]);
            $row[$field] = $n === '' ? null : (ctype_digit($n) ? (int) $n : $n);
        }

        $lookups = [
            'station' => fn ($v) => MouldingStation::whereRaw('upper(code) = ?', [mb_strtoupper($v)])->value('id'),
            'pattern' => fn ($v) => Pattern::whereRaw('upper(code) = ?', [mb_strtoupper($v)])->value('id'),
            'order' => fn ($v) => ProductionOrder::whereRaw('upper(production_order_number) = ?', [mb_strtoupper($v)])->value('id'),
            'defect' => fn ($v) => QualityDefect::whereRaw('upper(code) = ?', [mb_strtoupper($v)])->value('id'),
        ];
        $label = ['station' => 'station', 'pattern' => 'pattern', 'order' => 'production order', 'defect' => 'defect code'];
        foreach ($lookups as $column => $find) {
            $row[self::FIELD[$column]] = null;
            if ($raw[$column] !== '') {
                $row[self::FIELD[$column]] = $find($raw[$column]);
                if (! $row[self::FIELD[$column]]) {
                    $errors[self::FIELD[$column]] = "No {$label[$column]} “{$raw[$column]}”.";
                }
            }
        }

        // The revision is matched within the pattern; left blank it is the pattern's current revision.
        $row['pattern_revision_id'] = null;
        if ($row['pattern_id']) {
            $revisions = PatternRevision::where('pattern_id', $row['pattern_id']);
            $row['pattern_revision_id'] = $raw['revision'] === ''
                ? (clone $revisions)->where('is_current', true)->value('id')
                : (clone $revisions)->where(fn ($q) => $q->whereRaw('upper(code) = ?', [mb_strtoupper($raw['revision'])])->orWhereRaw('upper(revision) = ?', [mb_strtoupper($raw['revision'])]))->value('id');
            if ($raw['revision'] !== '' && ! $row['pattern_revision_id']) {
                $errors['pattern_revision_id'] = "Pattern {$raw['pattern']} has no revision “{$raw['revision']}”.";
            }
        }

        return [$row, $errors];
    }

    /** A date written as 2026-10-17, 17/10/2026, 17-10-2026 or 17.10.2026, or null. */
    public function date(string $value): ?string
    {
        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'j.n.Y'] as $format) {
            try {
                $d = Carbon::createFromFormat('!'.$format, $value);
            } catch (\Throwable) {
                continue;
            }
            if ($d && $d->format($format) === $value) {
                return $d->toDateString();
            }
        }

        return null;
    }
}
