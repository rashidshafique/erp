<?php

namespace App\Domains\Enquiry\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A question put to the customer about an enquiry, and their answer once it comes (design 14b). */
class EnquiryQuestion extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['asked_on' => 'date', 'answered_on' => 'date'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function isOpen(): bool
    {
        return blank($this->answer);
    }
}
