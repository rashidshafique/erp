<?php

namespace App\Domains\Development\Models;

use App\Models\Pattern;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One tool a development makes or changes: a pattern, core box, gauge, fixture or other. Its status feeds the development's gates. */
class PatternDevelopmentTool extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'quoted_cost' => 'float', 'actual_cost' => 'float'];
    }

    /** @return string The status name from config, e.g. "Trial passed". */
    public function statusName(): string
    {
        return config('pattern_developments.tool_statuses.'.$this->status, $this->status);
    }

    /** Whether the tool has reached the status a gate asks for; a tool marked not needed always has. */
    public function reached(string $required): bool
    {
        if ($this->status === 'NOT_NEEDED') {
            return true;
        }
        $order = array_flip(config('pattern_developments.tool_progress'));

        return isset($order[$this->status]) && $order[$this->status] >= $order[$required];
    }

    public function development(): BelongsTo
    {
        return $this->belongsTo(PatternDevelopment::class, 'pattern_development_id');
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }
}
