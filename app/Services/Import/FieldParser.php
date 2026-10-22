<?php

namespace App\Services\Import;

use Carbon\Carbon;

/** Turns one cell of text into the value a field stores, or says why it cannot. */
class FieldParser
{
    /** Words people use in place of the stored values. Only used when the target value exists in the field. */
    private const SYNONYMS = [
        'ACTIVE' => ['YES', 'LIVE', 'INUSE', 'CURRENT', 'AVAILABLE', 'OK', 'WORKING', 'INSERVICE'],
        'INACTIVE' => ['NO', 'DISABLED', 'ONHOLD', 'IDLE', 'UNUSED', 'SPARE', 'STORED'],
        'RETIRED' => ['SCRAPPED', 'OBSOLETE', 'DISPOSED', 'WITHDRAWN', 'DISCONTINUED', 'BROKEN', 'CLOSED', 'DAMAGED'],
        'APPROVED' => ['RELEASED', 'ISSUED', 'FINAL', 'OK', 'ACCEPTED'],
        'DRAFT' => ['NEW', 'PENDING', 'WIP', 'PROPOSED', 'INPROGRESS'],
        'OBSOLETE' => ['SUPERSEDED', 'OLD', 'WITHDRAWN', 'RETIRED', 'SCRAPPED'],
        'CASTING' => ['CASTINGS', 'CAST', 'IRONCASTING'],
        'CORE' => ['CORES', 'SANDCORE'],
        'COPE_DRAG' => ['COPEANDDRAG', 'COPEDRAG', 'COPEDRAGPAIR'],
        'MATCHPLATE' => ['MATCHPLATE', 'MATCHINGPLATE', 'PLATE'],
        'LOOSE_PATTERN' => ['LOOSE', 'LOOSEPATTERN', 'LOOSEPIECE'],
        'SPLIT_PATTERN' => ['SPLIT', 'SPLITPATTERN', 'TWOPIECE'],
        'STACK_PLATE' => ['STACK', 'STACKPLATE', 'STACKMOULD', 'STACKMOLD', 'STACKPATTERN'],
        'VERTICAL_PLATES' => ['VERTICAL', 'DISA', 'FLASKLESS', 'VERTICALPLATE'],
        'GATED' => ['GATEDPATTERN', 'GATEDPLATE'],
        'SHELL_PLATE' => ['SHELL', 'SHELLPLATE', 'SHELLPATTERN', 'SHELLMOULD', 'SHELLMOLD'],
        'WHOLE' => ['FULL', 'COMPLETE', 'WHOLECASTING', 'FULLCASTING'],
        'PART' => ['COMPONENT', 'SECTION', 'PIECE', 'PARTIAL'],
    ];

    private const TRUE_WORDS = ['1', 'YES', 'Y', 'TRUE', 'T', 'ON', 'ACTIVE', 'X', 'ENABLED', 'LIVE', 'OK'];

    private const FALSE_WORDS = ['0', 'NO', 'N', 'FALSE', 'F', 'OFF', 'INACTIVE', 'DISABLED', 'RETIRED', 'OBSOLETE', 'SCRAPPED', 'CLOSED'];

    public static function key(string $s): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));
    }

    /**
     * Reads every mapped field of one data row. A blank cell falls back to the mapping's fixed value; ref fields are
     * returned as the raw text for the caller to look up. Unrecognised list values are counted into $unknown.
     *
     * @param  array<string, array>  $fields
     * @param  array<string, array{col:?int, default:?string}>  $mapping
     * @param  array<int, string>  $cells
     * @param  array<string, array<string, int>>  $unknown
     * @return array{0: array<string, mixed>, 1: array<int, string>} parsed values, error messages
     */
    public static function row(array $fields, array $mapping, array $cells, array $options, array &$unknown): array
    {
        $valueMap = $options['value_map'] ?? [];
        $parsed = [];
        $errors = [];
        foreach ($fields as $key => $f) {
            $m = $mapping[$key] ?? null;
            $raw = '';
            if ($m && isset($m['col']) && $m['col'] !== null && $m['col'] !== '') {
                $raw = trim((string) ($cells[(int) $m['col']] ?? ''));
            }
            if ($raw === '' && $m && filled($m['default'] ?? null)) {
                $raw = trim((string) $m['default']);
            }
            if ($raw === '') {
                $parsed[$key] = null;

                continue;
            }
            if ($f['type'] === 'ref') {
                $parsed[$key] = $raw;

                continue;
            }
            [$val, $err, $isUnknown] = self::parse($key, $f, $raw, $options, $valueMap[$key] ?? []);
            if ($err) {
                $errors[] = $err;
                if ($isUnknown) {
                    $unknown[$key][$raw] = ($unknown[$key][$raw] ?? 0) + 1;
                }
            }
            $parsed[$key] = $val;
        }

        return [$parsed, $errors];
    }

    /**
     * @return array{0: mixed, 1: ?string, 2: bool} value, error message, whether the error is an unrecognised list value
     */
    public static function parse(string $field, array $def, string $raw, array $options, array $valueMap = []): array
    {
        $raw = trim($raw);
        $label = $def['label'];
        switch ($def['type']) {
            case 'string':
            case 'text':
                if (isset($def['max']) && mb_strlen($raw) > $def['max']) {
                    return [null, "$label is longer than {$def['max']} characters.", false];
                }

                return [$raw, null, false];
            case 'mark':
                $m = strtoupper($raw);
                if (! preg_match('/^[A-Z0-9]{3,20}$/', $m)) {
                    return [null, "$label must be 3 to 20 letters or digits, no spaces ('$raw').", false];
                }

                return [$m, null, false];
            case 'decimal':
                $n = self::number($raw, ! empty($options['decimal_comma']));
                if ($n === null) {
                    return [null, "$label '$raw' is not a number.", false];
                }
                if (isset($def['min']) && $n < $def['min']) {
                    return [null, "$label cannot be below {$def['min']}.", false];
                }

                return [round($n, $def['scale'] ?? 4), null, false];
            case 'int':
                $n = self::number($raw, ! empty($options['decimal_comma']));
                if ($n === null || floor($n) != $n) {
                    return [null, "$label '$raw' is not a whole number.", false];
                }
                if (isset($def['min']) && $n < $def['min']) {
                    return [null, "$label cannot be below {$def['min']}.", false];
                }
                if (isset($def['max']) && $n > $def['max']) {
                    return [null, "$label cannot be above {$def['max']}.", false];
                }

                return [(int) $n, null, false];
            case 'bool':
                $k = self::key($raw);
                if (isset($valueMap[strtolower($raw)])) {
                    return [$valueMap[strtolower($raw)] === '1', null, false];
                }
                if (in_array($k, self::TRUE_WORDS, true)) {
                    return [true, null, false];
                }
                if (in_array($k, self::FALSE_WORDS, true)) {
                    return [false, null, false];
                }

                return [null, "$label '$raw' is not yes or no. Say what it means under \"Values we do not recognise\" at the top.", true];
            case 'date':
                $d = self::date($raw, $options['date_order'] ?? 'DMY');

                return $d ? [$d, null, false] : [null, "$label '$raw' is not a date. Use yyyy-mm-dd or the order chosen under Options.", false];
            case 'enum':
                $mapped = $valueMap[strtolower($raw)] ?? null;
                if ($mapped !== null && $mapped !== '') {
                    return $mapped === '__SKIP__' ? [null, null, false] : [$mapped, null, false];
                }
                $k = self::key($raw);
                foreach ($def['enum'] as $v) {
                    if (self::key($v) === $k) {
                        return [$v, null, false];
                    }
                }
                foreach ($def['enum'] as $v) {
                    if (in_array($k, self::SYNONYMS[$v] ?? [], true)) {
                        return [$v, null, false];
                    }
                }

                return [null, "$label '$raw' is not one of ".implode(', ', $def['enum']).'. Say what it means under \"Values we do not recognise\" at the top.', true];
            default:
                return [$raw, null, false];
        }
    }

    public static function number(string $raw, bool $decimalComma): ?float
    {
        $s = preg_replace('/[\s\x{00A0}]/u', '', $raw);
        $s = preg_replace('/^(PKR|Rs\.?|USD|\$|€|£)/i', '', $s);
        $s = preg_replace('/(kg|mm|pcs|nos)$/i', '', $s);
        if ($s === '' || $s === '-') {
            return null;
        }
        if ($decimalComma) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            // 1,234.5 has thousands commas; a lone "2,5" is far more likely a decimal comma, so refuse and let the person choose.
            if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) {
                $s = str_replace(',', '', $s);
            }
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $s) || preg_match('/^-?\.\d+$/', $s) ? (float) $s : null;
    }

    public static function date(string $raw, string $order): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $formats = ['Y-m-d', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y/m/d', 'd-M-Y', 'd M Y', 'j F Y', 'M j, Y', 'F j, Y'];
        $formats = array_merge($formats, $order === 'MDY'
            ? ['m/d/Y', 'm-d-Y', 'm.d.Y', 'm/d/y', 'm/d/Y H:i']
            : ['d/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd/m/Y H:i']);
        foreach ($formats as $f) {
            try {
                $c = Carbon::createFromFormat('!'.$f, $raw);
                $errs = Carbon::getLastErrors();
                if ($c && (! $errs || (($errs['warning_count'] ?? 0) === 0 && ($errs['error_count'] ?? 0) === 0)) && $c->year >= 1900 && $c->year <= 2200) {
                    return $c->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }
}
