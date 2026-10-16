<?php

namespace App\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CastingAnalysis extends Model
{
    use GeneratesCodes, HasUuids;

    public string $codeKind = 'casting_analysis';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'result' => 'array', 'recommendation' => 'array', 'viewer' => 'array', 'score' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(IronGrade::class, 'iron_grade_id');
    }
}
