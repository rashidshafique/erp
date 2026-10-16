<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tamper-evident change log. Each row carries the hash of the row before it, so removing or editing
 * history breaks the chain (see `php artisan audit:verify`). The database also refuses UPDATE, DELETE
 * and TRUNCATE on the table.
 */
class Audit
{
    /** Models that are not part of the business record. */

    /** Attributes never written to the log. */
    private const HIDDEN = ['password', 'remember_token', 'updated_at', 'created_at', 'deleted_at', 'custom_fields_hash'];

    private static ?string $batch = null;

    public static function batch(): string
    {
        return self::$batch ??= (string) Str::uuid();
    }

    public static function newBatch(): void
    {
        self::$batch = null;
    }

    public function onModelEvent(string $action, Model $model): void
    {
        if ($model instanceof AuditLog) {
            return;
        }
        if ($action === 'UPDATED') {
            $changed = array_diff_key($model->getChanges(), array_flip(self::HIDDEN));
            if ($changed === []) {
                return;
            }
            $changes = [];
            foreach ($changed as $k => $new) {
                $changes[$k] = ['old' => $this->clip($model->getOriginal($k)), 'new' => $this->clip($new)];
            }
        } elseif ($action === 'CREATED') {
            $changes = array_map(fn ($v) => ['new' => $this->clip($v)], array_diff_key($model->getAttributes(), array_flip(self::HIDDEN)));
        } else {
            $changes = array_map(fn ($v) => ['old' => $this->clip($v)], array_diff_key($model->getOriginal(), array_flip(self::HIDDEN)));
        }
        $this->record($action, class_basename($model), (string) $model->getKey(), $this->label($model), $changes);
    }

    /** For writes made outside Eloquent (query builder). */
    public function record(string $action, string $entityType, ?string $entityId, ?string $label, array $changes = [], ?string $reason = null): void
    {
        $user = Auth::user();
        $request = app()->bound('request') ? request() : null;
        $json = $changes === [] ? null : json_encode($this->sortKeys($changes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        $at = now()->utc();
        $row = [
            'occurred_at' => $at->format('Y-m-d H:i:s.u').'+00', 'user_id' => $user?->getAuthIdentifier(), 'user_name' => $user?->name,
            'action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId, 'entity_label' => $label !== null ? mb_substr($label, 0, 200) : null,
            'changes' => $json, 'reason' => $reason, 'batch_id' => self::batch(),
            'ip_address' => $request && ! app()->runningInConsole() ? $request->ip() : null,
            'route_name' => app()->runningInConsole() ? 'console' : $request?->route()?->getName(),
        ];
        DB::transaction(function () use ($row, $at) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['audit-chain']);
            $prev = DB::table('audit_logs')->orderByDesc('id')->value('hash') ?? str_repeat('0', 64);
            $row['prev_hash'] = $prev;
            $row['hash'] = self::hashRow($prev, $at->format('Y-m-d\TH:i:s.u'), $row);
            DB::table('audit_logs')->insert($row);
        });
    }

    public static function hashRow(string $prev, string $at, array $r): string
    {
        return hash('sha256', implode('|', [$prev, $at, $r['user_id'] ?? '', $r['action'], $r['entity_type'], $r['entity_id'] ?? '', $r['entity_label'] ?? '', $r['changes'] ?? '', $r['reason'] ?? '', $r['batch_id'] ?? '']));
    }

    private function label(Model $m): ?string
    {
        foreach (['lot_number', 'heat_number', 'pour_number', 'ncr_number', 'inspection_number', 'run_number', 'list_number', 'packing_number', 'dispatch_number', 'assembly_number', 'production_order_number', 'order_number', 'sample_reference', 'code', 'name', 'title', 'email'] as $k) {
            if (! empty($m->getAttribute($k)) && is_scalar($m->getAttribute($k))) {
                return (string) $m->getAttribute($k);
            }
        }

        return null;
    }

    private function clip(mixed $v): mixed
    {
        if (is_array($v) || is_object($v)) {
            $v = json_encode($v);
        }
        if (is_string($v) && mb_strlen($v) > 500) {
            return '['.mb_strlen($v).' characters]';
        }

        return $v;
    }

    private function sortKeys(array $a): array
    {
        ksort($a);
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $a[$k] = $this->sortKeys($v);
            }
        }

        return $a;
    }
}
