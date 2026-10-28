<?php

namespace App\Domains\Enquiry\Models;

use App\Domains\Development\Models\PatternDevelopment;
use App\Models\Customer;
use App\Models\Concerns\GeneratesCodes;
use App\Models\Pattern;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enquiry extends Model
{
    use GeneratesCodes, HasUuids;

    public string $codeKind = 'enquiry';

    public const SOURCES = [
        'REPEAT' => 'Repeat of a part we make',
        'SAMPLE' => 'Customer sample, no drawing',
        'DRAWING' => 'Drawing or CAD model',
        'CUSTOMER_TOOL' => 'Customer supplies the pattern or tooling',
        'CONCEPT' => 'Concept only',
        'TRANSFER' => 'Transfer from another foundry',
        'CHANGE' => 'Change to a part we make',
    ];

    /** Where an enquiry stands, as the list (design 14a) and the record's stage bar (14b) show it. */
    public const STAGES = [
        'DRAFT' => 'Draft', 'RECEIVED' => 'Received', 'FEASIBILITY' => 'Feasibility', 'QUOTING' => 'Quoting', 'QUOTED' => 'Quoted', 'ON_HOLD' => 'On hold',
        'WON' => 'Won', 'LOST' => 'Lost', 'DECLINED' => 'Declined', 'CANCELLED' => 'Cancelled',
    ];

    /** The steps of the stage bar on the record (design 14b). */
    public const STAGE_STEPS = ['RECEIVED' => 'Received', 'FEASIBILITY' => 'Feasibility', 'QUOTING' => 'Quoting', 'QUOTED' => 'Quoted', 'CLOSED' => 'Won / lost'];

    /** How an enquiry closes (design 14c). */
    public const OUTCOMES = [
        'WON' => ['Won', 'Customer PO received'],
        'LOST' => ['Lost', 'Customer chose another supplier'],
        'DECLINED' => ['Declined', 'We chose not to quote'],
    ];

    /** Why it was lost or declined. The counts feed the lost-and-declined summary on the list. */
    public const CLOSE_REASONS = [
        'PRICE' => 'Price', 'PATTERN_LEAD' => 'Pattern lead time', 'DELIVERY_LEAD' => 'Delivery lead time', 'QUALITY' => 'Quality or approval',
        'CAPABILITY' => 'Outside capability', 'CAPACITY' => 'No capacity', 'NO_RESPONSE' => 'No response', 'PROJECT_CANCELLED' => 'Project cancelled',
    ];

    /** The tooling and pattern decision on the record (design 14b). */
    public const PATTERN_ROUTES = [
        'EXISTING' => ['Existing pattern', 'Use a pattern already in the register'],
        'NEW' => ['New pattern', 'Development needed · in-house or subcontract'],
        'CUSTOMER' => ['Customer builds or supplies', 'We advise and inspect'],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'due_on' => 'date', 'closed_at' => 'datetime', 'follow_up_on' => 'date', 'follow_up_sent_at' => 'datetime', 'winning_price' => 'float'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(EnquiryQuestion::class)->orderByDesc('asked_on')->orderByDesc('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EnquiryEvent::class)->orderByDesc('happened_on')->orderByDesc('created_at');
    }

    public function developments(): HasMany
    {
        return $this->hasMany(PatternDevelopment::class);
    }

    /**
     * Where it stands: a draft until sent; on hold while the customer's account is on credit hold; quoting once a draft quotation
     * exists; then quoted; and won, lost, declined or cancelled once closed. Expects quotations and customer to be loaded.
     */
    public function stage(): string
    {
        return match (true) {
            $this->status === 'NEW' => 'DRAFT',
            ! $this->isOpen() => $this->status,
            (bool) $this->customer?->credit_hold => 'ON_HOLD',
            $this->status === 'QUOTED' => 'QUOTED',
            $this->quotations->contains('status', 'DRAFT') => 'QUOTING',
            default => 'FEASIBILITY',
        };
    }

    /** Which step of the stage bar it has reached (0 for Received, 4 once closed). */
    public function stageStep(): int
    {
        return match ($this->stage()) {
            'DRAFT' => -1, 'FEASIBILITY', 'ON_HOLD' => 1, 'QUOTING' => 2, 'QUOTED' => 3, 'WON', 'LOST', 'DECLINED', 'CANCELLED' => 4, default => 0,
        };
    }

    /** What a year of the parts is worth: each part's yearly quantity at its latest quoted price, else at the customer's target price. */
    public function annualValue(): ?float
    {
        $lines = $this->quotations->whereNotIn('status', ['SUPERSEDED', 'REJECTED', 'EXPIRED'])->sortByDesc('version')->first()?->lines ?? collect();
        $total = null;
        foreach ($this->items as $item) {
            $price = $lines->firstWhere('enquiry_item_id', $item->id)?->unit_price ?? $item->target_price;
            if ($item->annual_qty && $price) {
                $total = ($total ?? 0) + $item->annual_qty * (float) $price;
            }
        }

        return $total;
    }

    /** Days from today to the reply-by date; negative when it has passed. */
    public function daysToReply(): ?int
    {
        return $this->due_on ? (int) today()->diffInDays($this->due_on, false) : null;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EnquiryItem::class)->orderBy('line_no');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->orderByDesc('version');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['NEW', 'FEASIBILITY', 'QUOTED'], true);
    }
}
