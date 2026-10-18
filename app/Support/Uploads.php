<?php

namespace App\Support;

/**
 * What the server will really accept for an upload. The app's own limit is a setting, but PHP applies its own first
 * (upload_max_filesize and post_max_size), and a file over that never reaches Laravel's size check.
 */
class Uploads
{
    /** The largest file the app accepts, in KB: its own limit, or PHP's if that is lower. */
    public static function effectiveMaxKb(): int
    {
        return (int) min(self::appMaxKb(), self::phpFileLimitKb());
    }

    public static function appMaxKb(): int
    {
        return (int) config('foundry.attachments.max_kb');
    }

    /** The largest single file PHP lets through, in KB. */
    public static function phpFileLimitKb(): int
    {
        $limits = array_filter([self::iniKb('upload_max_filesize'), self::iniKb('post_max_size')], fn (int $kb) => $kb > 0);

        return $limits ? min($limits) : PHP_INT_MAX;
    }

    /** True when PHP is set lower than the app's own limit, so files the app allows would still be refused. */
    public static function serverLimitsApp(): bool
    {
        return self::phpFileLimitKb() < self::appMaxKb();
    }

    public static function megabytes(int $kb): string
    {
        $mb = $kb / 1024;

        return rtrim(rtrim(number_format($mb, $mb < 10 ? 1 : 0, '.', ''), '0'), '.').' MB';
    }

    /** Shown when PHP refused a file before the app saw it. */
    public static function failedMessage(): string
    {
        $limit = self::megabytes(self::effectiveMaxKb());

        return "The file could not be uploaded. Files can be at most {$limit} each"
            .(self::serverLimitsApp() ? ' on this server (its PHP upload limit is lower than the app setting; ask the administrator to raise upload_max_filesize and post_max_size)' : '')
            .'. Check the size and try again.';
    }

    /** Shown when the whole request was over PHP's post_max_size. */
    public static function tooLargeMessage(): string
    {
        $total = self::megabytes(self::iniKb('post_max_size') ?: self::effectiveMaxKb());

        return "Those files are too big to send together (the server accepts {$total} per upload in total). Upload fewer or smaller files, one or two at a time.";
    }

    private static function iniKb(string $setting): int
    {
        $value = trim((string) ini_get($setting));
        if ($value === '' || $value === '0' || $value === '-1') {
            return 0;
        }
        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024,
            'm' => $number * 1024,
            'k' => $number,
            default => $number / 1024,
        };
    }
}
