<?php

namespace App\Domains\Development\Models;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryItem;
use App\Models\Concerns\GeneratesCodes;
use App\Models\Customer;
use App\Models\Pattern;
use App\Models\Product;
use App\Models\ProductRevision;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A new or replacement pattern worked through its stages with the customer. The stages are set by the type, in config/pattern_developments.php. */
class PatternDevelopment extends Model
{
    use GeneratesCodes, HasUuids;

    public string $codeKind = 'pattern_development';

    public const STATUSES = ['OPEN' => 'Open', 'DONE' => 'Done', 'CANCELLED' => 'Cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'quoted_tooling_cost' => 'float', 'actual_cost' => 'float'];
    }

    /** @return array<string, mixed> The type's name, stages, copies and note. */
    public function typeInfo(): array
    {
        return config('pattern_developments.types.'.$this->type);
    }

    /** @return list<string> */
    public function stages(): array
    {
        return $this->typeInfo()['stages'];
    }

    public function stageName(): string
    {
        return $this->stages()[$this->stage_index] ?? '';
    }

    public function nextStageName(): ?string
    {
        return $this->stages()[$this->stage_index + 1] ?? null;
    }

    /** The part's draft revision that this development's last stage, Approved, also approves; none for a type that ends otherwise. */
    public function draftRevision(): ?ProductRevision
    {
        if (last($this->stages()) !== 'Approved') {
            return null;
        }

        return $this->product?->revisions()->where('status', 'DRAFT')->latest()->first();
    }

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function enquiryItem(): BelongsTo
    {
        return $this->belongsTo(EnquiryItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PatternDevelopmentEvent::class)->latest();
    }
}
