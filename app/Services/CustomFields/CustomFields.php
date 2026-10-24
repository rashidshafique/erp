<?php

namespace App\Services\CustomFields;

use App\Models\CustomFieldDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * User-defined descriptive fields. Definitions are rows; values live in one jsonb column
 * on the owning table, validated against the definition. Never used for measured quality data.
 */
class CustomFields
{
    /** entity key => [label, table] */
    public const ENTITIES = [
        'heat' => ['Heat', 'heats'], 'pattern' => ['Pattern', 'patterns'], 'product' => ['Product', 'products'],
        'sales_order' => ['Sales order', 'sales_orders'], 'moulding_run' => ['Moulding run', 'moulding_runs'], 'pour' => ['Pour', 'pours'],
        'casting_lot' => ['Casting lot', 'casting_lots'], 'ncr' => ['Nonconformance report', 'nonconformance_reports'], 'inspection' => ['Inspection', 'quality_inspections'],
    ];

    public const ENTITY_CLASSES = ['heat' => \App\Models\Heat::class, 'pattern' => \App\Models\Pattern::class, 'product' => \App\Models\Product::class, 'sales_order' => \App\Domains\Sales\Models\SalesOrder::class, 'moulding_run' => \App\Models\MouldingRun::class, 'pour' => \App\Models\Pour::class, 'casting_lot' => \App\Models\CastingLot::class, 'ncr' => \App\Models\NonconformanceReport::class, 'inspection' => \App\Models\QualityInspection::class];

    public const TYPES = ['TEXT' => 'Text', 'NUMBER' => 'Number', 'BOOLEAN' => 'Yes / no', 'DATE' => 'Date', 'SELECT' => 'Choice list'];

    /** @return Collection<int, CustomFieldDefinition> */
    public function definitions(string $entity, bool $onlyActive = true): Collection
    {
        return CustomFieldDefinition::where('entity_type', $entity)->when($onlyActive, fn ($q) => $q->where('is_active', true))->orderBy('sort_order')->orderBy('label')->get();
    }

    public function table(string $entity): string
    {
        return self::ENTITIES[$entity][1] ?? throw new \InvalidArgumentException("Unknown entity {$entity}.");
    }

    public function values(string $entity, string $id): array
    {
        $raw = DB::table($this->table($entity))->where('id', $id)->value('custom_fields');

        return $raw ? (json_decode($raw, true) ?: []) : [];
    }

    /** Validate input against the active definitions and store the result; blanks are removed. */
    public function save(string $entity, string $id, array $input): array
    {
        $defs = $this->definitions($entity);
        $store = $this->values($entity, $id);   // keeps values of deactivated fields
        $errors = [];
        foreach ($defs as $d) {
            $raw = $input[$d->field_key] ?? null;
            $blank = $raw === null || $raw === '' || ($d->data_type === 'BOOLEAN' && $raw === '');
            if ($blank) {
                if ($d->is_required) {
                    $errors["cf.{$d->field_key}"] = "{$d->label} is required.";
                }
                unset($store[$d->field_key]);

                continue;
            }
            try {
                $store[$d->field_key] = $this->cast($d, $raw);
            } catch (\InvalidArgumentException $e) {
                $errors["cf.{$d->field_key}"] = "{$d->label}: ".$e->getMessage();
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $before = $this->values($entity, $id);
        DB::table($this->table($entity))->where('id', $id)->update(['custom_fields' => json_encode((object) $store)]);
        $diff = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($store))) as $k) {
            if (($before[$k] ?? null) !== ($store[$k] ?? null)) {
                $diff['custom_fields.'.$k] = ['old' => $before[$k] ?? null, 'new' => $store[$k] ?? null];
            }
        }
        if ($diff) {
            app(\App\Services\Audit\Audit::class)->record('UPDATED', class_basename(self::ENTITY_CLASSES[$entity] ?? $entity), $id, null, $diff);
        }

        return $store;
    }

    private function cast(CustomFieldDefinition $d, mixed $raw): mixed
    {
        switch ($d->data_type) {
            case 'NUMBER':
                if (! is_numeric($raw)) {
                    throw new \InvalidArgumentException('must be a number.');
                }
                $v = (float) $raw;
                if ($d->min_value !== null && $v < $d->min_value) {
                    throw new \InvalidArgumentException("must be at least {$d->min_value}.");
                }
                if ($d->max_value !== null && $v > $d->max_value) {
                    throw new \InvalidArgumentException("must be at most {$d->max_value}.");
                }

                return $v;
            case 'BOOLEAN':
                return in_array($raw, [true, 1, '1', 'true', 'yes'], true);
            case 'DATE':
                if (! strtotime((string) $raw)) {
                    throw new \InvalidArgumentException('is not a valid date.');
                }

                return date('Y-m-d', strtotime((string) $raw));
            case 'SELECT':
                if (! in_array($raw, $d->options ?? [], true)) {
                    throw new \InvalidArgumentException('is not one of the allowed choices.');
                }

                return (string) $raw;
            default:
                return mb_substr(trim((string) $raw), 0, 500);
        }
    }

    public function display(CustomFieldDefinition $d, mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return match ($d->data_type) {
            'BOOLEAN' => $value ? 'Yes' : 'No',
            'NUMBER' => rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.').($d->unit ? ' '.$d->unit : ''),
            default => (string) $value,
        };
    }
}
