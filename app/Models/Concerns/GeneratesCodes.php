<?php

namespace App\Models\Concerns;

use App\Services\Codes\CodeGenerator;

/**
 * Gives a record its code when none is typed, and tooling its engraving mark. Set $codeKind on the model.
 */
trait GeneratesCodes
{
    public static function bootGeneratesCodes(): void
    {
        static::creating(function ($model) {
            $gen = app(CodeGenerator::class);
            if (blank($model->code)) {
                $model->code = $gen->code($model->codeKind);
            }
            if (in_array($model->codeKind, CodeGenerator::TOOL_KINDS, true) && blank($model->mark_code)) {
                $model->mark_code = $gen->mark();
            }
        });
        static::created(function ($model) {
            if (in_array($model->codeKind, CodeGenerator::TOOL_KINDS, true) && filled($model->mark_code)) {
                app(CodeGenerator::class)->register($model->codeKind, (string) $model->getKey(), $model->mark_code);
            }
        });
    }

    public function ensureMark(): string
    {
        if (blank($this->mark_code)) {
            $this->mark_code = app(CodeGenerator::class)->ensureMark($this->codeKind, $this->getTable(), (string) $this->getKey());
        }

        return $this->mark_code;
    }
}
