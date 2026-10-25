<?php

namespace App\Domains\Sales\Models;

use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasUuids;

    public const STATUSES = [
        'DRAFT' => 'Draft', 'UNDER_REVIEW' => 'Under review', 'CONFIRMED' => 'Confirmed', 'IN_PRODUCTION' => 'In production',
        'ON_HOLD' => 'On hold', 'DISPATCHED' => 'Dispatched', 'CANCELLED' => 'Cancelled',
    ];

    /** Statuses of an order not yet finished: everything before it is dispatched or cancelled. */
    public const OPEN = ['DRAFT', 'UNDER_REVIEW', 'CONFIRMED', 'IN_PRODUCTION', 'ON_HOLD'];

    protected $fillable = [
        'order_number',
        'customer_id',
        'order_date',
        'requested_delivery_date',
        'status',
        'customer_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'requested_delivery_date' => 'date'];
    }

    /** Next order number of the year. Call inside a transaction; takes the same lock as manual order entry. */
    public static function nextNumber(): string
    {
        \Illuminate\Support\Facades\DB::select('SELECT pg_advisory_xact_lock(?)', [2026101202]);
        $prefix = 'SO-'.now()->format('Y').'-';
        $last = static::where('order_number', 'like', $prefix.'%')->pluck('order_number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function contractReviews(): HasMany
    {
        return $this->hasMany(ContractReview::class);
    }

    public function currentReview(): ?ContractReview
    {
        return $this->contractReviews()->where('status', '!=', 'SUPERSEDED')->orderByDesc('review_number')->first();
    }

    /**
     * True when the order was edited, or a line or delivery was added or changed, after the review was run.
     * Status changes do not count.
     */
    public function changedSince(ContractReview $review): bool
    {
        $since = $review->evaluated_at;

        return AuditLog::where('entity_type', 'SalesOrder')->where('entity_id', $this->id)->where('action', 'EDITED')->where('occurred_at', '>', $since)->exists()
            || $this->lines()->where('updated_at', '>', $since)->exists()
            || SalesOrderLineDelivery::whereIn('sales_order_line_id', $this->lines()->select('id'))->where('updated_at', '>', $since)->exists();
    }

    public function hasApprovedReview(): bool
    {
        return $this->contractReviews()->where('status', 'APPROVED')->exists();
    }
}
