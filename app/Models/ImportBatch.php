<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['mapping' => 'array', 'options' => 'array', 'counts' => 'array', 'report' => 'array', 'committed_at' => 'datetime'];
    }

    public function def(): array
    {
        return static::definition($this->entity);
    }

    /** An entity's import definition, with list fields whose values come from an editable list filled in. */
    public static function definition(string $entity): ?array
    {
        $def = config('imports.entities.'.$entity);
        foreach ($def['fields'] ?? [] as $key => $field) {
            if (isset($field['enum_from'])) {
                $def['fields'][$key]['enum'] = $field['enum_from']::allNames();
            }
        }

        return $def;
    }
}
