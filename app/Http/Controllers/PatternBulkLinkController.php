<?php

namespace App\Http\Controllers;

use App\Models\Pattern;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Services\Patterns\RevisionLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Links the patterns that have no cavity linked to a product, many at once: one row per pattern with the product it makes,
 * how many cavities it has and the product revision. Every cavity of the row is linked to that product.
 */
class PatternBulkLinkController extends Controller
{
    /** At most this many rows on one screen; the rest come up once these are linked. */
    private const ROWS = 100;

    public function create(): View
    {
        $patterns = Pattern::unlinked()->with('currentRevision')->orderBy('code')->get();
        $products = Product::with(['revisions' => fn ($q) => $q->orderByDesc('created_at')])->orderBy('name')->orderBy('code')->get();
        $byName = $products->groupBy(fn (Product $product) => $this->normalized($product->name));

        $rows = $patterns->take(self::ROWS)->map(function (Pattern $pattern) use ($byName) {
            [$baseName, $pieces] = $this->splitPieces($pattern->name);
            $matches = $byName->get($this->normalized($pattern->name)) ?? $byName->get($this->normalized($baseName)) ?? collect();

            return [
                'pattern' => $pattern,
                'product_id' => $matches->count() === 1 ? $matches->first()->id : null,
                'alternatives' => $matches->count() > 1 ? $matches->count() : 0,
                'cavities' => $pieces ?? $pattern->currentRevision?->cavities_per_mould ?? 1,
            ];
        });

        return view('patterns.bulk-link', [
            'rows' => $rows,
            'patterns' => $patterns,
            'products' => $products,
            'revisions' => $products->mapWithKeys(fn (Product $product) => [$product->id => $this->revisionChoices($product)]),
            'remaining' => max(0, $patterns->count() - self::ROWS),
        ]);
    }

    /**
     * Links every row that has both a pattern and a product. Rows without a product are skipped. Every row is checked
     * first; nothing is saved if one fails.
     */
    public function store(Request $request, RevisionLayout $layout): RedirectResponse
    {
        $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.pattern_id' => ['nullable', 'uuid', Rule::exists('patterns', 'id')->whereNull('deleted_at')],
            'rows.*.product_id' => ['nullable', 'uuid', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'rows.*.cavities' => ['nullable', 'integer', 'min:1', 'max:200'],
            'rows.*.product_revision_id' => ['nullable', 'uuid'],
        ], [
            'rows.*.pattern_id.exists' => 'Choose a pattern from the list.',
            'rows.*.product_id.exists' => 'Choose a product from the list.',
            'rows.*.cavities.min' => 'At least 1 cavity.',
            'rows.*.cavities.max' => 'At most 200 cavities.',
        ]);

        $rows = collect($request->input('rows'))->filter(fn ($row) => filled($row['product_id'] ?? null));
        if ($rows->isEmpty()) {
            return back()->withInput()->with('warning', 'Choose a product for at least one pattern.');
        }
        $this->assertLinkable($rows);

        $linked = DB::transaction(function () use ($rows, $layout) {
            foreach ($rows as $row) {
                $pattern = Pattern::findOrFail($row['pattern_id']);
                $revision = $pattern->currentRevision()->first()
                    ?? $pattern->addRevision(['change_description' => 'First revision, recorded when linking products in bulk']);
                $productRevision = filled($row['product_revision_id'] ?? null)
                    ? ProductRevision::findOrFail($row['product_revision_id'])
                    : Product::findOrFail($row['product_id'])->linkableRevision();
                $count = (int) ($row['cavities'] ?? 1);

                $layout->save($revision, ['cavities_per_mould' => $count], 'Cavity count set when linking products in bulk.');
                for ($n = 1; $n <= $count; $n++) {
                    $revision->cavityMap()->create(['cavity_number' => $n, 'product_revision_id' => $productRevision->id, 'cavity_role' => 'WHOLE', 'pieces_per_product' => 1]);
                }
            }

            return $rows->count();
        });

        return redirect()->route('patterns.bulk-link')->with('success', "{$linked} ".Str::plural('pattern', $linked).' linked to a product.');
    }

    /**
     * Every row to save names a pattern, once, that is still unlinked, and a product revision of its product that is not obsolete.
     *
     * @param  Collection<int|string, array<string, mixed>>  $rows
     */
    private function assertLinkable(Collection $rows): void
    {
        $errors = [];
        $seen = [];
        $stillUnlinked = Pattern::unlinked()->whereIn('id', $rows->pluck('pattern_id')->filter())->pluck('id')->flip();
        foreach ($rows as $i => $row) {
            $patternId = $row['pattern_id'] ?? null;
            if (blank($patternId)) {
                $errors["rows.{$i}.pattern_id"] = 'Choose the pattern this product is made on.';
            } elseif (isset($seen[$patternId])) {
                $errors["rows.{$i}.pattern_id"] = 'This pattern is already on another row.';
            } elseif (! isset($stillUnlinked[$patternId])) {
                $errors["rows.{$i}.pattern_id"] = 'This pattern is already linked to a product; change it on its cavity map.';
            }
            $seen[$patternId] = true;

            if (filled($row['product_revision_id'] ?? null)) {
                $revision = ProductRevision::where('product_id', $row['product_id'])->find($row['product_revision_id']);
                if (! $revision) {
                    $errors["rows.{$i}.product_revision_id"] = 'Choose a revision of this product.';
                } elseif ($revision->status === 'OBSOLETE') {
                    $errors["rows.{$i}.product_revision_id"] = 'An obsolete product revision cannot be linked to a pattern cavity.';
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The revisions of a product to pick from, newest first, and the one preselected: the latest approved, else the latest
     * that is not obsolete. Obsolete ones are listed but cannot be picked.
     *
     * @return array{list: list<array{id: string, label: string, obsolete: bool}>, preselected: ?string}
     */
    private function revisionChoices(Product $product): array
    {
        $live = $product->revisions->where('status', '!=', 'OBSOLETE');

        return [
            'list' => $product->revisions->map(fn (ProductRevision $r) => ['id' => $r->id, 'label' => "Rev {$r->revision} · {$r->status}", 'obsolete' => $r->status === 'OBSOLETE'])->values()->all(),
            'preselected' => ($live->firstWhere('status', 'APPROVED') ?? $live->first())?->id,
        ];
    }

    /**
     * A pattern name ending in a piece count, like "Outlet 6pc", split into the product name and the count.
     *
     * @return array{0: string, 1: ?int}
     */
    private function splitPieces(string $name): array
    {
        return preg_match('/^(.*\S)\s+(\d+)\s*pcs?\.?$/i', trim($name), $m) && (int) $m[2] >= 1 && (int) $m[2] <= 200
            ? [$m[1], (int) $m[2]]
            : [$name, null];
    }

    private function normalized(?string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $name)));
    }
}
