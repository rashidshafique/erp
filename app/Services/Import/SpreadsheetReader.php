<?php

namespace App\Services\Import;

use XMLReader;
use ZipArchive;

/**
 * Reads CSV (comma, semicolon, tab or pipe) and Excel .xlsx files into rows of text, without any extra package.
 * Dates in Excel cells come back as yyyy-mm-dd. Old .xls and .ods files are refused with a clear message.
 */
class SpreadsheetReader
{
    public function __construct(private int $maxRows = 20000, private int $maxCols = 200)
    {
    }

    /** Sheet names of a workbook; a CSV has one. */
    public function sheets(string $path, string $ext): array
    {
        if ($ext !== 'xlsx') {
            return ['Data'];
        }
        $zip = $this->openZip($path);
        $names = array_column($this->workbookSheets($zip), 'name');
        $zip->close();

        return $names ?: ['Sheet1'];
    }

    /** @return array<int, array<int, string>> rows, each padded to the same width */
    public function rows(string $path, string $ext, int $sheet = 0): array
    {
        $rows = $ext === 'xlsx' ? $this->xlsxRows($path, $sheet) : $this->csvRows($path);
        $width = 0;
        foreach ($rows as $r) {
            $width = max($width, count($r));
        }
        if ($width > $this->maxCols) {
            throw new ImportException("The sheet has $width columns; the limit is {$this->maxCols}. Remove the empty columns and try again.");
        }
        foreach ($rows as &$r) {
            $r = array_pad($r, $width, '');
        }

        return $rows;
    }

    // ---- CSV -----------------------------------------------------------------------------------------------

    public function csvRows(string $path): array
    {
        $text = (string) file_get_contents($path);
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        } elseif (str_starts_with($text, "\xFF\xFE") || str_starts_with($text, "\xFE\xFF")) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-16');
        }
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');   // what Excel on Windows writes
        }
        if (str_contains(substr($text, 0, 4096), "\0")) {
            throw new ImportException('This does not look like a CSV or Excel (.xlsx) file.');
        }
        $text = preg_replace('/^sep=.\R/', '', $text);   // Excel's delimiter hint line
        $delim = $this->sniff($text);
        $h = fopen('php://temp', 'r+');
        fwrite($h, $text);
        rewind($h);
        $rows = [];
        while (($r = fgetcsv($h, 0, $delim, '"', '')) !== false) {
            // A blank line stays as an empty row so the row numbers match what a spreadsheet shows.
            $rows[] = $r === [null] ? [] : array_map(fn ($v) => trim((string) $v), $r);
            if (count($rows) > $this->maxRows + 50) {
                fclose($h);
                throw new ImportException("The file has more than {$this->maxRows} rows. Split it into smaller files.");
            }
        }
        fclose($h);

        return $rows;
    }

    private function sniff(string $text): string
    {
        $best = ',';
        $bestScore = -1;
        $lines = array_slice(preg_split('/\R/', substr($text, 0, 20000)), 0, 8);
        foreach ([',', ';', "\t", '|'] as $d) {
            $counts = [];
            foreach ($lines as $l) {
                if ($l === '') {
                    continue;
                }
                $counts[] = count(str_getcsv($l, $d, '"', ''));
            }
            if (! $counts) {
                continue;
            }
            $first = $counts[0];
            $consistent = count(array_filter($counts, fn ($c) => $c === $first));
            $score = $first > 1 ? $consistent * 100 + $first : 0;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $d;
            }
        }

        return $best;
    }

    // ---- XLSX ----------------------------------------------------------------------------------------------

    private function openZip(string $path): ZipArchive
    {
        $head = (string) file_get_contents($path, false, null, 0, 8);
        if (str_starts_with($head, "\xD0\xCF\x11\xE0")) {
            throw new ImportException('This is an old Excel (.xls) file. Open it in Excel and save it as .xlsx or .csv, then upload that.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true || $zip->locateName('xl/workbook.xml') === false) {
            throw new ImportException('This is not a valid Excel (.xlsx) file.');
        }

        return $zip;
    }

    private function workbookSheets(ZipArchive $zip): array
    {
        $wb = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels = [];
        $relXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relXml) {
            foreach (simplexml_load_string($relXml)->Relationship as $r) {
                $rels[(string) $r['Id']] = ltrim((string) $r['Target'], '/');
            }
        }
        $out = [];
        $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($wb->sheets->sheet ?? [] as $s) {
            $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = $rels[$rid] ?? null;
            if ($target && ! str_starts_with($target, 'xl/')) {
                $target = 'xl/'.$target;
            }
            $out[] = ['name' => (string) $s['name'], 'path' => $target, 'hidden' => (string) $s['state'] !== ''];
        }

        return $out;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $out = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! $xml) {
            return $out;
        }
        $x = new XMLReader();
        $x->XML($xml);
        $cur = null;
        $inT = false;
        $skip = 0;
        while ($x->read()) {
            if ($x->nodeType === XMLReader::ELEMENT) {
                if ($x->name === 'si') {
                    $cur = '';
                } elseif ($x->name === 'rPh' && ! $x->isEmptyElement) {
                    $skip++;   // phonetic hints are not part of the text
                } elseif ($x->name === 't' && $skip === 0) {
                    $inT = ! $x->isEmptyElement;
                }
            } elseif ($x->nodeType === XMLReader::TEXT || $x->nodeType === XMLReader::SIGNIFICANT_WHITESPACE) {
                if ($inT && $cur !== null) {
                    $cur .= $x->value;
                }
            } elseif ($x->nodeType === XMLReader::END_ELEMENT) {
                if ($x->name === 't') {
                    $inT = false;
                } elseif ($x->name === 'rPh') {
                    $skip--;
                } elseif ($x->name === 'si') {
                    $out[] = $cur;
                    $cur = null;
                }
            }
        }
        $x->close();

        return $out;
    }

    /** Style index => is a date or time format. */
    private function dateStyles(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');
        if (! $xml) {
            return [];
        }
        $s = simplexml_load_string($xml);
        $custom = [];
        foreach ($s->numFmts->numFmt ?? [] as $f) {
            $custom[(int) $f['numFmtId']] = (string) $f['formatCode'];
        }
        $builtin = array_merge(range(14, 22), range(27, 36), range(45, 47), range(50, 58));
        $out = [];
        $i = 0;
        foreach ($s->cellXfs->xf ?? [] as $xf) {
            $id = (int) $xf['numFmtId'];
            if (isset($custom[$id])) {
                $code = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', $custom[$id]);
                $out[$i] = (bool) preg_match('/[dmyhs]/i', $code) && ! preg_match('/^[#0.,%\s?\/]*$/', $code);
            } else {
                $out[$i] = in_array($id, $builtin, true);
            }
            $i++;
        }

        return $out;
    }

    private function xlsxRows(string $path, int $sheet): array
    {
        $zip = $this->openZip($path);
        $sheets = $this->workbookSheets($zip);
        $target = $sheets[$sheet]['path'] ?? null;
        $xml = $target ? $zip->getFromName($target) : false;
        if ($xml === false) {
            $zip->close();
            throw new ImportException('That sheet could not be found in the workbook.');
        }
        $strings = $this->sharedStrings($zip);
        $dates = $this->dateStyles($zip);
        $zip->close();

        $x = new XMLReader();
        $x->XML($xml);
        $rows = [];
        $row = null;
        $rowNo = 0;
        while ($x->read()) {
            if ($x->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            if ($x->name === 'row') {
                $rowNo = (int) ($x->getAttribute('r') ?: $rowNo + 1);
                $row = [];
                if ($x->isEmptyElement) {
                    $rows[$rowNo] = [];
                }
            } elseif ($x->name === 'c' && $row !== null) {
                $ref = (string) $x->getAttribute('r');
                $type = (string) $x->getAttribute('t');
                $style = (int) $x->getAttribute('s');
                $col = $ref !== '' ? $this->colIndex($ref) : count($row);
                $value = '';
                if (! $x->isEmptyElement) {
                    $depth = $x->depth;
                    $v = null;
                    $inline = '';
                    while ($x->read() && ! ($x->nodeType === XMLReader::END_ELEMENT && $x->name === 'c' && $x->depth === $depth)) {
                        if ($x->nodeType === XMLReader::ELEMENT && $x->name === 'v') {
                            $v = $x->readString();
                        } elseif ($x->nodeType === XMLReader::ELEMENT && $x->name === 't') {
                            $inline .= $x->readString();
                        }
                    }
                    $value = match ($type) {
                        's' => $strings[(int) $v] ?? '',
                        'inlineStr' => $inline,
                        'b' => $v === '1' ? 'TRUE' : 'FALSE',
                        'e' => '',
                        'str' => (string) $v,
                        default => $this->number((string) $v, $dates[$style] ?? false),
                    };
                }
                $row[$col] = trim($value);
                $rows[$rowNo] = $row;
            }
            if (count($rows) > $this->maxRows + 50 || $rowNo > $this->maxRows + 5000) {
                $x->close();
                throw new ImportException("The sheet has more than {$this->maxRows} rows. Split it into smaller files.");
            }
        }
        $x->close();
        ksort($rows);
        $out = [];
        $prev = 0;
        foreach ($rows as $n => $r) {
            for ($gap = $prev + 1; $gap < $n && $gap <= $prev + 5; $gap++) {
                $out[] = [];
            }
            $width = $r ? max(array_keys($r)) + 1 : 0;
            $line = array_fill(0, $width, '');
            foreach ($r as $c => $val) {
                $line[$c] = $val;
            }
            $out[] = $line;
            $prev = $n;
        }
        // Blank rows are dropped later; keep row positions so "header is on row N" matches what Excel shows.
        return $out;
    }

    private function colIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/i', $ref, $m);
        $n = 0;
        foreach (str_split(strtoupper($m[1] ?? 'A')) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    private function number(string $v, bool $isDate): string
    {
        if ($v === '' || ! is_numeric($v)) {
            return $v;
        }
        if ($isDate) {
            $days = (float) $v;
            if ($days >= 1 && $days < 2958466) {
                $ts = (int) round(($days - 25569) * 86400);
                $whole = floor($days) == $days;

                return gmdate($whole ? 'Y-m-d' : 'Y-m-d H:i', $ts);
            }
        }
        $f = (float) $v;
        if (floor($f) == $f && abs($f) < 1e15) {
            return (string) (int) $f;
        }

        return rtrim(rtrim(sprintf('%.10F', $f), '0'), '.');
    }
}
