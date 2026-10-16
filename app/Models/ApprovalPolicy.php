<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ApprovalPolicy extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'allow_self_approval' => 'boolean'];
}
