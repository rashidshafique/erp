<?php

namespace Tests\Support;

/** Builds a small real .xlsx file (shared strings, a date style, two sheets) so the reader is tested on the actual format. */
class XlsxFactory
{
    /** @param array<string, array<int, array<int, mixed>>> $sheets name => rows; a DateTimeInterface cell is written as an Excel date */
    public static function make(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xl').'.xlsx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $strings = [];
        $sheetXml = [];
        $i = 0;
        foreach ($sheets as $rows) {
            $x = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            foreach ($rows as $r => $row) {
                $x .= '<row r="'.($r + 1).'">';
                foreach ($row as $c => $v) {
                    $ref = chr(65 + $c).($r + 1);
                    if ($v === null || $v === '') {
                        continue;
                    }
                    if ($v instanceof \DateTimeInterface) {
                        $serial = ($v->getTimestamp() / 86400) + 25569;
                        $x .= "<c r=\"$ref\" s=\"1\"><v>$serial</v></c>";
                    } elseif (is_int($v) || is_float($v)) {
                        $x .= "<c r=\"$ref\"><v>$v</v></c>";
                    } else {
                        $idx = array_search($v, $strings, true);
                        if ($idx === false) {
                            $strings[] = $v;
                            $idx = count($strings) - 1;
                        }
                        $x .= "<c r=\"$ref\" t=\"s\"><v>$idx</v></c>";
                    }
                }
                $x .= '</row>';
            }
            $sheetXml[++$i] = $x.'</sheetData></worksheet>';
        }
        $names = array_keys($sheets);
        $wb = '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($names as $k => $n) {
            $wb .= '<sheet name="'.htmlspecialchars($n).'" sheetId="'.($k + 1).'" r:id="rId'.($k + 1).'"/>';
            $rels .= '<Relationship Id="rId'.($k + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($k + 1).'.xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.($k + 1).'.xml', $sheetXml[$k + 1]);
        }
        $zip->addFromString('xl/workbook.xml', $wb.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels.'</Relationships>');
        $si = '';
        foreach ($strings as $s) {
            $si .= '<si><t>'.htmlspecialchars((string) $s).'</t></si>';
        }
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$si.'</sst>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="14"/></cellXfs></styleSheet>');
        $zip->close();

        return $path;
    }
}
