<?php

namespace App\Domains\Ledger\Services;

use App\Support\Formats;

/**
 * An amount in words for a voucher (design 19h): "Rupees eleven lakh seventy-one thousand eight hundred only". Rupee amounts use
 * lakh and crore, as cheques and vouchers in Pakistan do; any other currency uses thousand, million and billion.
 */
class AmountInWords
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    public static function say(float $amount): string
    {
        $currency = trim((string) (Formats::all()['currency'] ?? ''));
        $rupees = $currency === '' || in_array(mb_strtolower($currency), ['rs', 'rs.', 'pkr', '₨'], true);
        $whole = (int) floor(abs($amount) + 0.0000001);
        $paisa = (int) round((abs($amount) - $whole) * 100);
        $words = $whole === 0 ? 'zero' : ($rupees ? self::southAsian($whole) : self::western($whole));
        $unit = $rupees ? 'Rupees' : ($currency !== '' ? $currency : '');
        $cents = $paisa ? ' and '.self::below1000($paisa).($rupees ? ' paisa' : ' cents') : '';

        return trim($unit.' '.$words.$cents.' only');
    }

    private static function below1000(int $n): string
    {
        $out = [];
        if ($n >= 100) {
            $out[] = self::ONES[intdiv($n, 100)].' hundred';
            $n %= 100;
        }
        if ($n >= 20) {
            $out[] = self::TENS[intdiv($n, 10)].($n % 10 ? '-'.self::ONES[$n % 10] : '');
        } elseif ($n > 0) {
            $out[] = self::ONES[$n];
        }

        return implode(' ', $out);
    }

    private static function southAsian(int $n): string
    {
        $parts = [];
        foreach ([[10000000, 'crore'], [100000, 'lakh'], [1000, 'thousand']] as [$size, $name]) {
            if ($n >= $size) {
                $count = intdiv($n, $size);
                $parts[] = ($size === 10000000 && $count >= 100 ? self::southAsian($count) : self::below1000($count)).' '.$name;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = self::below1000($n);
        }

        return implode(' ', $parts);
    }

    private static function western(int $n): string
    {
        $parts = [];
        foreach ([[1000000000, 'billion'], [1000000, 'million'], [1000, 'thousand']] as [$size, $name]) {
            if ($n >= $size) {
                $parts[] = self::below1000(intdiv($n, $size) % 1000).' '.$name;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = self::below1000($n);
        }

        return implode(' ', $parts);
    }
}
