<?php

namespace App\Domains\Enquiry\Models;

use App\Models\Customer;
use App\Models\Concerns\GeneratesCodes;
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

    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'due_on' => 'date', 'closed_at' => 'datetime'];
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
