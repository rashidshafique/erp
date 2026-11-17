<?php

namespace App\Domains\Enquiry\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a quotation's log (design 30b): an email in or out, an approval, a note, or something the system did. */
class QuotationEvent extends Model
{
    use HasUuids;

    public const KINDS = ['EMAIL' => 'Email', 'APPROVAL' => 'Approval', 'NOTE' => 'Note', 'SYSTEM' => 'System'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['happened_on' => 'date'];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** Writes a line to a quotation's log. $direction is IN or OUT for an email. */
    public static function log(Quotation $quotation, string $kind, string $text, ?string $direction = null): self
    {
        return $quotation->events()->create(['kind' => $kind, 'direction' => $direction, 'text' => $text, 'user_name' => auth()->user()?->name, 'happened_on' => today()]);
    }
}
