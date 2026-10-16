<?php

namespace App\Services\Import;

use App\Models\ImportMappingTemplate;

/** Suggests which column feeds which field, from the column names, or from a saved mapping. */
class ColumnMapper
{
    public static function norm(string $s): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $s));
    }

    /**
     * @param  array<int,string>  $headers
     * @return array<string, array{col:?int, default:?string, auto:bool}>
     */
    public static function suggest(string $entity, array $headers): array
    {
        $fields = config("imports.entities.$entity.fields");
        $norm = array_map([self::class, 'norm'], $headers);
        $taken = [];
        $out = [];
        // Exact name or label first, then aliases, then a column name that contains the label.
        foreach (['exact', 'alias', 'contains'] as $pass) {
            foreach ($fields as $key => $f) {
                if (isset($out[$key])) {
                    continue;
                }
                $names = match ($pass) {
                    'exact' => [self::norm($key), self::norm($f['label'])],
                    'alias' => array_map([self::class, 'norm'], $f['aliases'] ?? []),
                    default => [],
                };
                foreach ($norm as $i => $h) {
                    if ($h === '' || isset($taken[$i])) {
                        continue;
                    }
                    $hit = $pass === 'contains'
                        ? (strlen($h) >= 4 && strlen(self::norm($f['label'])) >= 4 && (str_contains($h, self::norm($f['label'])) || str_contains(self::norm($f['label']), $h)))
                        : in_array($h, $names, true);
                    if ($hit) {
                        $out[$key] = ['col' => $i, 'default' => null, 'auto' => true];
                        $taken[$i] = true;
                        break;
                    }
                }
            }
        }
        foreach ($fields as $key => $f) {
            $out[$key] ??= ['col' => null, 'default' => null, 'auto' => false];
        }

        return $out;
    }

    /** A saved mapping applied to a new file: columns are found again by their header name. */
    public static function fromTemplate(ImportMappingTemplate $t, array $headers): array
    {
        $norm = array_map([self::class, 'norm'], $headers);
        $out = [];
        foreach (config("imports.entities.{$t->entity}.fields") as $key => $f) {
            $m = $t->mapping[$key] ?? null;
            $col = null;
            if ($m && filled($m['header'] ?? null)) {
                $i = array_search(self::norm($m['header']), $norm, true);
                $col = $i === false ? null : $i;
            }
            $out[$key] = ['col' => $col, 'default' => $m['default'] ?? null, 'auto' => false];
        }

        return $out;
    }
}
