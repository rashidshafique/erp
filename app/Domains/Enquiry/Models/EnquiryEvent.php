<?php

namespace App\Domains\Enquiry\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of an enquiry's log (design 14b): an email or call in or out, a meeting, a note, or something the system did. */
class EnquiryEvent extends Model
{
    use HasUuids;

    public const KINDS = ['EMAIL' => 'Email', 'CALL' => 'Call', 'MEETING' => 'Meeting', 'NOTE' => 'Note', 'SYSTEM' => 'System'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['happened_on' => 'date'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    /** Writes a line the system adds, such as sent to feasibility or closed as lost. */
    public static function system(Enquiry $enquiry, string $text): self
    {
        return $enquiry->events()->create(['kind' => 'SYSTEM', 'text' => $text, 'user_name' => auth()->user()?->name, 'happened_on' => today()]);
    }
}
