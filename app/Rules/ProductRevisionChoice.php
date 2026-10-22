<?php

namespace App\Rules;

use App\Models\ProductRevision;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A product revision picked from a list that also offers products with no revision yet, as "product:<id>". Such a product
 * gets its first revision when the record is saved (ProductRevision::fromChoice).
 */
class ProductRevisionChoice implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isProduct = is_string($value) && str_starts_with($value, ProductRevision::PRODUCT_CHOICE);
        $id = $isProduct ? substr($value, strlen(ProductRevision::PRODUCT_CHOICE)) : $value;
        $exists = is_string($id) && Str::isUuid($id)
            && DB::table($isProduct ? 'products' : 'product_revisions')->where('id', $id)->when($isProduct, fn ($q) => $q->whereNull('deleted_at'))->exists();
        if (! $exists) {
            $fail('Choose a product from the list.');
        }
    }
}
