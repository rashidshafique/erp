<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['allow_self_approval' => 'boolean', 'consumed_at' => 'datetime'];

    public function decisions()
    {
        return $this->hasMany(ApprovalDecision::class)->orderBy('created_at');
    }

    public function actionLabel(): string
    {
        return config('approvals.actions')[$this->action_key] ?? $this->action_key;
    }
}
