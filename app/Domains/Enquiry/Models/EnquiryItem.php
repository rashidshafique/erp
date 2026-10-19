<?php

namespace App\Domains\Enquiry\Models;

use App\Models\CastingAnalysis;
use App\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryItem extends Model
{
    use HasUuids;

    public const FEASIBILITY = ['PENDING' => 'Not assessed', 'OK' => 'Feasible', 'CONDITIONAL' => 'Feasible with conditions', 'NOT_FEASIBLE' => 'Not feasible'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['heat_treatment' => 'boolean', 'ndt' => 'boolean', 'weight_kg' => 'float', 'machining_cost' => 'float', 'other_cost' => 'float', 'tooling_cost' => 'float', 'target_price' => 'float'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();   // archived products still show on old records
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(CastingAnalysis::class, 'casting_analysis_id');
    }
}
