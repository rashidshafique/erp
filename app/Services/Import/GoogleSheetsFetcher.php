<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Http;

/**
 * Reads a Google Sheet that is shared as "anyone with the link can view" through its CSV export address.
 * No Google account or key is needed, and nothing private can be read: a sheet that is not shared comes back as a sign-in page.
 */
class GoogleSheetsFetcher
{
    /** Turn any pasted Google Sheets address into the export address, or fail with advice. */
    public static function exportUrl(string $url): string
    {
        $url = trim($url);
        if (! preg_match('#^https://docs\.google\.com/spreadsheets/d/([A-Za-z0-9_-]{20,})#', $url, $m)) {
            throw new ImportException('That is not a Google Sheets address. It should start with https://docs.google.com/spreadsheets/d/…');
        }
        $gid = null;
        if (preg_match('/[#?&]gid=(\d+)/', $url, $g)) {
            $gid = $g[1];
        }

        return 'https://docs.google.com/spreadsheets/d/'.$m[1].'/export?format=csv'.($gid !== null ? '&gid='.$gid : '');
    }

    /** @return string the sheet as CSV text */
    public function fetch(string $url): string
    {
        $export = self::exportUrl($url);
        try {
            $r = Http::timeout(30)->withOptions(['allow_redirects' => ['max' => 4, 'protocols' => ['https']]])->get($export);
        } catch (\Throwable $e) {
            throw new ImportException('Google Sheets could not be reached from this server. Download the sheet as CSV or Excel and upload that, or paste the cells instead.');
        }
        $type = strtolower((string) $r->header('Content-Type'));
        if (in_array($r->status(), [401, 403, 404], true) || str_contains($type, 'text/html')) {
            throw new ImportException('The sheet is private or the address is wrong. In Google Sheets choose Share → General access → "Anyone with the link" (Viewer), then try again. Or use File → Download → CSV and upload that.');
        }
        if (! $r->successful()) {
            throw new ImportException('Google Sheets answered with an error ('.$r->status().'). Try again, or download the sheet as CSV and upload it.');
        }
        $body = $r->body();
        if (strlen($body) > config('imports.max_upload_kb') * 1024) {
            throw new ImportException('The sheet is too large to import in one go.');
        }
        if (trim($body) === '') {
            throw new ImportException('The sheet is empty.');
        }

        return $body;
    }
}
