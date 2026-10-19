<?php

namespace App\Services\Production;

use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\PatternRevision;
use App\Models\ProductionOrder;
use App\Models\SandSystem;
use App\Services\PatternCavityMap;
use App\Services\Patterns\PatternOwnership;
use App\Support\FlaskRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saves the rows of the bulk moulding-run grid: many runs entered or corrected at once. Every row is checked first; if any
 * row fails, nothing is saved and each message is keyed rows.{row}.{field} so the grid can show it on that row.
 */
class BulkRunEntry
{
    /** A new row with none of these filled is ignored. Team and sand are not entered: they come from the station. */
    private const CONTENT = ['pattern_id', 'pattern_revision_id', 'moulds_made_qty', 'damaged_mould_qty', 'cavity_numbers', 'notes'];

    public function __construct(private PatternCavityMap $map, private WorkListService $workLists, private RunRevisionPrefill $prefill) {}

    /**
     * @param  array<string|int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $header  defaults for blank date, shift and station
     * @return array{created: int, updated: int, warnings: array<int, string>}
     */
    public function save(array $rows, array $header = []): array
    {
        $checked = [];
        $errors = [];
        $warnings = [];
        $seen = [];
        $position = 0;
        foreach ($rows as $key => $row) {
            $row = $this->withHeader($row, $header);
            if (empty($row['id']) && ! $this->hasContent($row)) {
                continue;
            }
            $position++;
            [$clean, $rowErrors, $warning] = $this->check($row, $position);
            if (! $rowErrors) {
                $dupeKey = implode('|', [$clean['run_date'], mb_strtolower((string) $clean['shift']), $clean['moulding_station_id'], $clean['pattern_revision_id']]);
                if (empty($row['confirm_duplicate'])) {
                    if (isset($seen[$dupeKey])) {
                        $rowErrors['confirm_duplicate'] = "Same date, shift, station, pattern and revision as row {$seen[$dupeKey]}. Tick “Save anyway” if this really is a second run.";
                    } elseif ($other = $this->existingDuplicate($clean, $row['id'] ?? null, $rows)) {
                        $rowErrors['confirm_duplicate'] = "Run {$other} already has this date, shift, station, pattern and revision. Tick “Save anyway” if this really is a second run.";
                    }
                }
                $seen[$dupeKey] ??= $position;
            }
            foreach ($rowErrors as $field => $message) {
                $errors["rows.{$key}.{$field}"] = "Row {$position}: {$message}";
            }
            if ($warning) {
                $warnings[] = "Row {$position}: {$warning}";
            }
            $checked[] = [$row['id'] ?? null, $clean];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($checked, $warnings) {
            $created = $updated = 0;
            foreach ($checked as [$id, $clean]) {
                if ($id) {
                    $run = MouldingRun::lockForUpdate()->findOrFail($id);
                    $run->fill($clean);
                    if ($run->isDirty()) {
                        $run->save();
                        $updated++;
                    }
                } else {
                    MouldingRun::create($clean + ['run_number' => MouldingRun::nextRunNumber(), 'status' => 'COMPLETED', 'completed_at' => now()]);
                    $created++;
                }
            }

            return ['created' => $created, 'updated' => $updated, 'warnings' => $warnings];
        });
    }

    /** @param array<string, mixed> $row */
    private function withHeader(array $row, array $header): array
    {
        $row = array_map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v, $row);
        foreach (['run_date', 'shift', 'moulding_station_id'] as $field) {
            if (blank($row[$field] ?? null) && filled($header[$field] ?? null)) {
                $row[$field] = $header[$field];
            }
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    private function hasContent(array $row): bool
    {
        foreach (self::CONTENT as $field) {
            if (filled($row[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * One row's checks.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: ?string}
     */
    private function check(array $row, int $position): array
    {
        if (blank($row['pattern_revision_id'] ?? null) && filled($row['pattern_id'] ?? null) && Str::isUuid($row['pattern_id'])) {
            $row['pattern_revision_id'] = $this->prefill->forRow(
                $row['pattern_id'], $row['run_date'] ?? null, $row['shift'] ?? null,
                Str::isUuid($row['moulding_station_id'] ?? null) ? $row['moulding_station_id'] : null,
                Str::isUuid($row['production_order_id'] ?? null) ? $row['production_order_id'] : null,
            );
        }
        $v = Validator::make($row, [
            'id' => ['nullable', 'uuid', Rule::exists('moulding_runs', 'id')],
            'run_date' => ['required', 'date'],
            'shift' => ['nullable', 'string', 'max:30'],
            'moulding_station_id' => ['nullable', 'uuid', Rule::exists('moulding_stations', 'id')],
            'pattern_id' => ['required', 'uuid', Rule::exists('patterns', 'id')->whereNull('deleted_at')],
            'pattern_revision_id' => ['required', 'uuid', Rule::exists('pattern_revisions', 'id')],
            'cavity_numbers' => ['nullable', 'string', 'max:200'],
            'production_order_id' => ['nullable', 'uuid', Rule::exists('production_orders', 'id')->whereNull('deleted_at')],
            'moulds_made_qty' => ['required', 'integer', 'min:0', 'max:100000'],
            'damaged_mould_qty' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'quality_defect_id' => ['nullable', 'uuid', Rule::exists('quality_defects', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'pattern_id.required' => 'Choose the pattern.',
            'pattern_revision_id.required' => 'Choose the pattern revision.',
            'moulds_made_qty.required' => 'Enter the moulds made.',
            'run_date.required' => 'Enter the date.',
        ]);
        $errors = array_map(fn ($m) => $m[0], $v->errors()->toArray());
        if ($errors) {
            return [[], $errors, null];
        }
        $d = $v->validated();

        $revision = PatternRevision::with('cavityMap')->find($d['pattern_revision_id']);
        if ($revision->pattern_id !== $d['pattern_id']) {
            return [[], ['pattern_revision_id' => "{$revision->code} is not a revision of the chosen pattern."], null];
        }

        $made = (int) $d['moulds_made_qty'];
        $rejected = (int) ($d['damaged_mould_qty'] ?? 0);
        if ($rejected > $made) {
            $errors['damaged_mould_qty'] = "Rejected moulds ({$rejected}) cannot be more than moulds made ({$made}).";
        }

        [$cavities, $cavityError] = $this->cavities($d['cavity_numbers'] ?? null, $revision);
        if ($cavityError) {
            $errors['cavity_numbers'] = $cavityError;
        }

        $existing = filled($d['id'] ?? null) ? MouldingRun::withCount('mouldAssemblies')->find($d['id']) : null;
        $orderId = $d['production_order_id'] ?? $existing?->production_order_id ?? $this->workLists->openOrdersFor($revision->id)->first()?->id;
        if (! $orderId) {
            $errors['production_order_id'] = 'No open order needs this pattern. Choose the production order.';
        } else {
            try {
                $this->map->assertPatternMakesOrderProduct($revision->id, $orderId);
            } catch (ValidationException) {
                $errors['production_order_id'] = "{$revision->code} has no cavity for the product on the chosen order.";
            }
            try {
                $ownershipWarning = app(PatternOwnership::class)->checkUse($revision, ProductionOrder::find($orderId));
            } catch (ValidationException $e) {
                $errors['pattern_id'] = collect($e->errors())->flatten()->first();
            }
        }

        if ($existing && $existing->mould_assemblies_count > 0) {
            if ($existing->pattern_revision_id !== $revision->id || ($existing->cavity_numbers ?? []) != ($cavities ?? [])) {
                $errors['pattern_revision_id'] = "Moulds from {$existing->run_number} are already assembled, so its pattern revision and cavities cannot change.";
            }
            if ($made < $existing->mould_assemblies_count) {
                $errors['moulds_made_qty'] = "{$existing->mould_assemblies_count} moulds from {$existing->run_number} are already assembled, so moulds made cannot be less.";
            }
        }

        // Team and sand follow the station; a saved run keeps its own (set on its page) while its station stays the same.
        $station = filled($d['moulding_station_id'] ?? null) ? MouldingStation::with('defaultTeam')->find($d['moulding_station_id']) : null;
        $sameStation = $existing && $existing->moulding_station_id === $station?->id;
        $team = $sameStation ? $existing->team : $station?->defaultTeam;
        $clean = [
            'run_date' => $d['run_date'],
            'shift' => $d['shift'] ?? null,
            'moulding_station_id' => $d['moulding_station_id'] ?? null,
            'moulding_team_id' => $team?->id,
            'team_name' => $sameStation ? $existing->team_name : $team?->name,
            'pattern_revision_id' => $revision->id,
            'cavity_numbers' => $cavities,
            'production_order_id' => $orderId,
            'sand_system' => $sameStation ? $existing->sand_system : ($station?->defaultSand() ?? SandSystem::names()[0] ?? null),
            'moulds_made_qty' => $made,
            'damaged_mould_qty' => $rejected,
            'accepted_mould_qty' => max(0, $made - $rejected),
            'quality_defect_id' => $d['quality_defect_id'] ?? null,
            'notes' => $d['notes'] ?? null,
        ];
        $warning = $revision->isSuperseded() ? "{$revision->code} is superseded; the run was saved against it as entered." : null;
        $warning = trim(($warning ?? '').' '.($ownershipWarning ?? '')) ?: null;
        $flaskWarning = $this->flaskCapacity($revision, $station, $made);
        if ($flaskWarning) {
            $warning = trim(($warning ?? '').' '.$flaskWarning);
        }

        return [$clean, $errors, $warning];
    }

    /**
     * Reads "1, 2" or "1-3" into cavity numbers and checks each is on the revision's cavity map.
     *
     * @return array{0: ?array<int, int>, 1: ?string}
     */
    public function cavities(?string $text, PatternRevision $revision): array
    {
        if (blank($text)) {
            return [null, null];
        }
        $numbers = [];
        foreach (preg_split('/[\s,;]+/', trim($text)) as $part) {
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $m) && (int) $m[1] <= (int) $m[2] && (int) $m[2] - (int) $m[1] < 200) {
                $numbers = array_merge($numbers, range((int) $m[1], (int) $m[2]));
            } elseif (ctype_digit($part)) {
                $numbers[] = (int) $part;
            } elseif ($part !== '') {
                return [null, "“{$part}” is not a cavity number. Use numbers such as 1, 2 or 1-4."];
            }
        }
        $numbers = array_values(array_unique($numbers));
        sort($numbers);
        $mapped = $revision->cavityMap->pluck('cavity_number')->all();
        if (! $mapped) {
            return [null, "{$revision->code} has no cavity map yet, so cavities cannot be chosen. Map them on the pattern first, or leave this blank."];
        }
        $unknown = array_diff($numbers, $mapped);
        if ($unknown) {
            return [null, 'Cavit'.(count($unknown) === 1 ? 'y ' : 'ies ').implode(', ', $unknown).' '.(count($unknown) === 1 ? 'is' : 'are')." not on the {$revision->code} layout (mapped: ".implode(', ', $mapped).').'];
        }

        return [$numbers, null];
    }

    /** A saved run, not part of this submission, with the same date, shift, station and revision. */
    private function existingDuplicate(array $clean, ?string $id, array $rows): ?string
    {
        $submitted = array_filter(array_column($rows, 'id'));

        return MouldingRun::whereDate('run_date', $clean['run_date'])
            ->where(fn ($q) => $clean['shift'] === null ? $q->whereNull('shift') : $q->whereRaw('lower(shift) = ?', [mb_strtolower($clean['shift'])]))
            ->where(fn ($q) => $clean['moulding_station_id'] === null ? $q->whereNull('moulding_station_id') : $q->where('moulding_station_id', $clean['moulding_station_id']))
            ->where('pattern_revision_id', $clean['pattern_revision_id'])
            ->where('status', '!=', 'CANCELLED')
            ->when($id, fn ($q) => $q->whereKeyNot($id))
            ->whereNotIn('id', $submitted ?: ['00000000-0000-0000-0000-000000000000'])
            ->value('run_number');
    }

    /** A note when there are fewer free sets of flasks than moulds entered; flaskless moulds and untracked patterns need none. */
    private function flaskCapacity(PatternRevision $revision, ?MouldingStation $station, int $made): ?string
    {
        $flasks = app(FlaskManager::class);
        if ($made < 1 || FlaskRules::get('run_claims') === 'COUNT' || $flasks->isFlaskless($station, $revision) || ! $revision->flaskSizes()->exists()) {
            return null;
        }
        $free = count($flasks->pick($flasks->sizesFor($revision, $station), $made, $flasks->layerTypes((int) ($revision->layers_per_mould ?: 2)))['sets']);

        return $free < $made ? "only {$free} of {$made} sets of flasks are free for {$revision->code}." : null;
    }
}
