<?php

namespace App\Support;

use App\Domains\Core\ModuleRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * The search box in the top bar: finds records by code or name across the app, ranked so a clear winner can be opened directly.
 * Typing a short word such as "pat" or "prd" offers to search one type only.
 */
class GlobalSearch
{
    /**
     * key => label, table, code column, name SQL (null = none), show route (null = tool card), read permission (null = anyone),
     * words that pick the type.
     *
     * @var array<string, array{label: string, table: string, code: string, name: string|null, route: string|null, permission: string|null, words: list<string>}>
     */
    private const CORE_TYPES = [
        'product' => ['label' => 'Product', 'table' => 'products', 'code' => 'code', 'name' => 'name', 'route' => 'products.show', 'permission' => null, 'words' => ['prd', 'pro', 'prod', 'product', 'products']],
        'pattern' => ['label' => 'Pattern', 'table' => 'patterns', 'code' => 'code', 'name' => 'name', 'route' => 'patterns.show', 'permission' => null, 'words' => ['pat', 'patt', 'pattern', 'patterns']],
        'material' => ['label' => 'Material', 'table' => 'materials', 'code' => 'code', 'name' => 'name', 'route' => 'materials.show', 'permission' => null, 'words' => ['mat', 'mtl', 'material', 'materials']],
        'casting_lot' => ['label' => 'Casting lot', 'table' => 'casting_lots', 'code' => 'code', 'name' => null, 'route' => 'lots.show', 'permission' => null, 'words' => ['lot', 'lots']],
        'heat' => ['label' => 'Heat', 'table' => 'heats', 'code' => 'heat_number', 'name' => null, 'route' => 'heats.show', 'permission' => null, 'words' => ['heat', 'heats', 'ht']],
        'pour' => ['label' => 'Pour', 'table' => 'pours', 'code' => 'pour_number', 'name' => null, 'route' => 'pours.show', 'permission' => null, 'words' => ['pour', 'pours']],
        'flask' => ['label' => 'Flask', 'table' => 'flasks', 'code' => 'code', 'name' => null, 'route' => 'flasks.show', 'permission' => null, 'words' => ['flk', 'flask', 'flasks']],
        'core_box' => ['label' => 'Core box', 'table' => 'core_boxes', 'code' => 'code', 'name' => 'name', 'route' => null, 'permission' => null, 'words' => ['cbx', 'corebox']],
        'core_definition' => ['label' => 'Core', 'table' => 'core_definitions', 'code' => 'code', 'name' => 'name', 'route' => null, 'permission' => null, 'words' => ['cor', 'core', 'cores']],
    ];

    /**
     * Every searchable record type: the core ones plus those modules add.
     *
     * @return array<string, array{label: string, table: string, code: string, name: string|null, route: string|null, permission: string|null, words: list<string>}>
     */
    public static function types(): array
    {
        return self::CORE_TYPES + app(ModuleRegistry::class)->searchTypes();
    }

    /** A result this far ahead of the next one is opened directly on Enter. */
    private const CLEAR_LEAD = 25;

    /** The type a typed word picks, e.g. "pat" → pattern. Only the start of a word counts, and at least two letters. */
    public static function typeForWord(string $word): ?string
    {
        $word = strtolower(trim($word));
        if (strlen($word) < 2) {
            return null;
        }
        foreach (self::types() as $key => $type) {
            if (in_array($word, $type['words'], true)) {
                return $key;
            }
        }

        return null;
    }

    /** @return array<string, string> key => label, for the types this user may search. */
    public static function typesFor(?User $user): array
    {
        $types = [];
        foreach (self::types() as $key => $type) {
            if (self::mayRead($user, $type['permission'])) {
                $types[$key] = $type['label'];
            }
        }

        return $types;
    }

    /**
     * Best matches, highest score first.
     *
     * @return list<array{type: string, label: string, code: string, name: string|null, url: string, score: int}>
     */
    public static function search(string $query, ?string $onlyType, ?User $user, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $lower = mb_strtolower($query);
        $results = [];
        foreach (self::types() as $key => $type) {
            if (($onlyType !== null && $onlyType !== $key) || ! self::mayRead($user, $type['permission']) || ! Schema::hasTable($type['table'])) {
                continue;
            }
            $code = "lower({$type['code']})";
            $name = $type['name'] ? "lower({$type['name']})" : null;
            $rows = DB::table($type['table'])
                ->select('id', DB::raw("{$type['code']} as code"))
                ->when($type['name'], fn ($q) => $q->addSelect(DB::raw("{$type['name']} as name")))
                ->when(Schema::hasColumn($type['table'], 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->where(function ($q) use ($code, $name, $lower) {
                    $q->whereRaw("$code like ?", ['%'.$lower.'%']);
                    if ($name) {
                        $q->orWhereRaw("$name like ?", ['%'.$lower.'%']);
                    }
                })
                ->limit($limit)
                ->get();
            if (! $type['route'] && ! Route::has('tools.show')) {
                continue;
            }
            foreach ($rows as $row) {
                $results[] = [
                    'type' => $key, 'label' => $type['label'], 'code' => (string) $row->code, 'name' => $row->name ?? null,
                    'url' => $type['route'] ? route($type['route'], $row->id) : route('tools.show', [$key, $row->id]), 'score' => self::score($lower, (string) $row->code, $row->name ?? null),
                ];
            }
        }
        usort($results, fn ($a, $b) => [$b['score'], $a['code']] <=> [$a['score'], $b['code']]);

        return array_slice($results, 0, $limit);
    }

    /**
     * The one result to open on Enter, when it is clearly what was meant: an exact code, the only result, or far ahead of the rest.
     *
     * @param  list<array{score: int, url: string}>  $results
     */
    public static function clearWinner(array $results): ?string
    {
        if ($results === []) {
            return null;
        }
        $top = $results[0];
        if ($top['score'] >= 100 || count($results) === 1) {
            return $top['url'];
        }

        return $top['score'] >= 60 && $top['score'] - $results[1]['score'] >= self::CLEAR_LEAD ? $top['url'] : null;
    }

    private static function score(string $query, string $code, ?string $name): int
    {
        $code = mb_strtolower($code);
        $name = mb_strtolower((string) $name);

        return match (true) {
            $code === $query => 100,
            $name !== '' && $name === $query => 90,
            str_starts_with($code, $query) => 70,
            $name !== '' && str_starts_with($name, $query) => 60,
            $name !== '' && (bool) preg_match('/\b'.preg_quote($query, '/').'/u', $name) => 50,
            default => 30,
        };
    }

    private static function mayRead(?User $user, ?string $permission): bool
    {
        return $permission === null || ! config('foundry.require_login') || (bool) $user?->hasPermission($permission);
    }
}
