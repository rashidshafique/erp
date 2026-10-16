<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Code 128 barcode as SVG. Code set B for general text, code set C (half the width) for all-digit strings of even length.
 * Readable by every handheld scanner. No external library needed.
 */
class Code128
{
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232',
    ];
    private const STOP = '2331112';
    private const START_B = 104;
    private const START_C = 105;

    /** The bar/space pattern as a string of 1 (bar) and 0 (space) modules. */
    public static function modules(string $text): string
    {
        if ($text === '') {
            throw new InvalidArgumentException('Nothing to encode.');
        }
        $codes = [];
        if (ctype_digit($text) && strlen($text) % 2 === 0) {
            $codes[] = self::START_C;
            foreach (str_split($text, 2) as $pair) {
                $codes[] = (int) $pair;
            }
        } else {
            $codes[] = self::START_B;
            foreach (str_split($text) as $ch) {
                $o = ord($ch);
                if ($o < 32 || $o > 126) {
                    throw new InvalidArgumentException('Only plain printable characters can be encoded.');
                }
                $codes[] = $o - 32;
            }
        }
        $sum = $codes[0];
        foreach (array_slice($codes, 1) as $i => $c) {
            $sum += $c * ($i + 1);
        }
        $codes[] = $sum % 103;

        $out = '';
        foreach ($codes as $c) {
            $out .= self::widths(self::PATTERNS[$c]);
        }

        return $out.self::widths(self::STOP);
    }

    private static function widths(string $w): string
    {
        $s = '';
        foreach (str_split($w) as $i => $n) {
            $s .= str_repeat($i % 2 === 0 ? '1' : '0', (int) $n);
        }

        return $s;
    }

    public static function svg(string $text, int $height = 56, int $module = 2, bool $showText = true): string
    {
        $bits = self::modules($text);
        $quiet = 10 * $module;
        $width = strlen($bits) * $module + 2 * $quiet;
        $bars = '';
        // run-length merge
        $x = $quiet;
        foreach (self::runs($bits) as [$bit, $len]) {
            if ($bit === '1') {
                $bars .= '<rect x="'.$x.'" y="0" width="'.($len * $module).'" height="'.$height.'"/>';
            }
            $x += $len * $module;
        }
        $total = $height + ($showText ? 16 : 0);
        $label = $showText ? '<text x="'.($width / 2).'" y="'.($height + 13).'" text-anchor="middle" font-family="monospace" font-size="13">'.htmlspecialchars($text, ENT_QUOTES).'</text>' : '';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$total.'" width="'.$width.'" height="'.$total.'" role="img" aria-label="Barcode '.htmlspecialchars($text, ENT_QUOTES).'"><rect width="100%" height="100%" fill="#fff"/><g fill="#000">'.$bars.'</g>'.$label.'</svg>';
    }

    /** @return array<int,array{0:string,1:int}> */
    private static function runs(string $bits): array
    {
        $runs = [];
        $len = strlen($bits);
        $i = 0;
        while ($i < $len) {
            $j = $i;
            while ($j < $len && $bits[$j] === $bits[$i]) {
                $j++;
            }
            $runs[] = [$bits[$i], $j - $i];
            $i = $j;
        }

        return $runs;
    }
}
