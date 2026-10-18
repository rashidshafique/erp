<?php

namespace App\Services\Quality;

use Illuminate\Support\Carbon;

/**
 * Reads results pasted or uploaded from a spectrometer's export: a header row, then one sample per line, separated by commas,
 * tabs or semicolons. Columns are matched by name (C, Si, Mn, S, P, Cr, Cu, Mo, Mg or the full element name), so any
 * instrument's export works as long as the headings say what they are.
 */
class ChemistryImport
{
    private const ALIASES = [
        'carbon_percent' => ['c', 'carbon'], 'silicon_percent' => ['si', 'silicon'], 'manganese_percent' => ['mn', 'manganese'],
        'sulphur_percent' => ['s', 'sulphur', 'sulfur'], 'phosphorus_percent' => ['p', 'phosphorus'], 'chromium_percent' => ['cr', 'chromium'],
        'copper_percent' => ['cu', 'copper'], 'molybdenum_percent' => ['mo', 'molybdenum'], 'magnesium_percent' => ['mg', 'magnesium'],
        'sample_reference' => ['sample', 'sampleid', 'sampleno', 'samplename', 'id', 'ref', 'reference', 'samplereference'],
        'tested_at' => ['time', 'date', 'datetime', 'testedat', 'timestamp', 'analysistime', 'analysisdate'],
        'tested_by' => ['operator', 'testedby', 'analyst'],
        'sample_stage' => ['stage', 'samplestage'],
        'instrument_reference' => ['instrument', 'machine', 'spectro', 'spectrometer'],
    ];

    /**
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function parse(string $text): array
    {
        $lines = array_values(array_filter(preg_split('/\R/u', trim($text)), fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            return ['rows' => [], 'errors' => ['Paste a header row and at least one sample row.']];
        }
        $delimiter = collect(["\t", ';', ','])->sortByDesc(fn ($d) => substr_count($lines[0], $d))->first();
        $headers = array_map(fn ($h) => preg_replace('/[^a-z]/', '', strtolower(trim($h, " \t\"'%()"))), str_getcsv($lines[0], $delimiter));
        $map = [];
        foreach ($headers as $i => $h) {
            foreach (self::ALIASES as $column => $names) {
                if (in_array($h, $names, true) && ! in_array($column, $map, true)) {
                    $map[$i] = $column;
                }
            }
        }
        if (! collect($map)->contains(fn ($c) => str_ends_with($c, '_percent'))) {
            return ['rows' => [], 'errors' => ['No element columns found. The header row needs headings such as C, Si, Mn, S, P.']];
        }

        $rows = [];
        $errors = [];
        foreach (array_slice($lines, 1) as $n => $line) {
            $cells = str_getcsv($line, $delimiter);
            $row = [];
            foreach ($map as $i => $column) {
                $value = trim($cells[$i] ?? '');
                if ($value === '') {
                    continue;
                }
                if (str_ends_with($column, '_percent')) {
                    $value = str_replace(',', '.', $value);
                    if (! is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
                        $errors[] = 'Row '.($n + 1).": '{$value}' is not a percentage for ".str_replace('_percent', '', $column).'.';

                        continue 2;
                    }
                    $row[$column] = (float) $value;
                } elseif ($column === 'tested_at') {
                    try {
                        $row[$column] = Carbon::parse($value);
                    } catch (\Throwable) {
                        $errors[] = 'Row '.($n + 1).": '{$value}' is not a date and time.";

                        continue 2;
                    }
                } else {
                    $row[$column] = $value;
                }
            }
            if (collect($row)->keys()->contains(fn ($c) => str_ends_with($c, '_percent'))) {
                $rows[] = $row;
            }
        }

        return ['rows' => $rows, 'errors' => $errors];
    }
}
