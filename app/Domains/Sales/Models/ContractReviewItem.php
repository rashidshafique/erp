<?php

namespace App\Domains\Sales\Models;

use App\Models\Pattern;
use App\Models\PatternRevisionCavity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

class ContractReviewItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'contract_review_id',
        'sales_order_line_id',
        'check_code',
        'severity',
        'message',
        'acknowledgement',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ContractReview::class, 'contract_review_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }

    /**
     * Where to go to fix a finding: the product for its revision, specification or grade, and the patterns that make it for the
     * pattern check (or the pattern list when none is linked yet). Passing checks need no fix.
     *
     * @return list<array{label: string, url: string}>
     */
    public function fixLinks(): array
    {
        $product = $this->line?->product;
        if ($this->severity === 'PASS' || ! $product) {
            return [];
        }
        $productLink = ['label' => 'Product '.$product->code, 'url' => route('products.show', $product)];

        return match ($this->check_code) {
            'REVISION_STATUS', 'PRODUCT_SPEC', 'GRADE' => [$productLink],
            'PATTERN' => $this->patternLinks($productLink),
            default => [],
        };
    }

    /** @return list<array{label: string, url: string}> */
    private function patternLinks(array $productLink): array
    {
        $patterns = Pattern::whereIn('id', PatternRevisionCavity::query()
            ->whereHas('productRevision', fn ($q) => $q->where('product_id', $this->line->product_id))
            ->join('pattern_revisions', 'pattern_revisions.id', '=', 'pattern_revision_cavities.pattern_revision_id')->select('pattern_revisions.pattern_id'))
            ->orderBy('code')->get();
        $links = $patterns->map(fn (Pattern $p) => ['label' => 'Pattern '.$p->code, 'url' => route('patterns.show', $p)])->all();

        return $links ?: [$productLink] + (Route::has('patterns.index') ? [1 => ['label' => 'Patterns', 'url' => route('patterns.index')]] : []);
    }
}
