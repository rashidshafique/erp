<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use App\Services\Codes\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reads the stored file, applies the column mapping, checks every row and, when asked, writes the records.
 * The preview and the final import run the same code, so what the preview shows is what the import does.
 */
class ImportProcessor
{
    public function __construct(private SpreadsheetReader $reader)
    {
    }

    /** @return array{headers: array<int,string>, rows: array<int, array{0:int,1:array}>, sheets: array} */
    public function table(ImportBatch $b): array
    {
        $disk = Storage::disk($b->file_disk);
        if (! $b->file_path || ! $disk->exists($b->file_path)) {
            throw new ImportException('The uploaded data is no longer stored. Start the import again.');
        }
        $local = $disk->path($b->file_path);
        $rows = $this->reader->rows($local, $b->file_ext, (int) $b->sheet_index);
        $hi = max(0, (int) $b->header_row - 1);
        $headers = array_map('strval', $rows[$hi] ?? []);
        $data = [];
        foreach ($rows as $i => $r) {
            if ($i <= $hi || ! array_filter($r, fn ($v) => $v !== '')) {
                continue;
            }
            $data[] = [$i + 1, $r];
        }

        return ['headers' => $headers, 'rows' => $data];
    }

    /**
     * With $commit the rows from $offset on are written until the time budget is used up; 'next' is where to carry on (null when done)
     * and 'window' is the slice of rows handled in this call. Large files are therefore imported over several short requests.
     *
     * @return array{rows: array, counts: array, unknown: array, headers: array, window: array, next: ?int}
     */
    public function run(ImportBatch $b, bool $commit = false, int $offset = 0, float $budgetSeconds = 20.0): array
    {
        $def = $b->def();
        $opt = $b->options ?? [];
        $mapping = $b->mapping ?? [];
        $t = $this->table($b);

        if (count($t['rows']) > config('imports.max_rows')) {
            throw new ImportException('The file has more than '.config('imports.max_rows').' rows.');
        }

        $handler = isset($def['handler']) ? app($def['handler']) : null;
        if ($handler) {
            [$out, $unknown] = $handler->check($def, $t['rows'], $mapping, $opt);
        } else {
            [$out, $unknown] = $this->check($def, $t['rows'], $mapping, $opt);
        }

        $counts = ['CREATE' => 0, 'UPDATE' => 0, 'UNCHANGED' => 0, 'SKIP' => 0, 'ERROR' => 0, 'TOTAL' => count($out)];
        foreach ($out as $r) {
            $counts[$r['action']]++;
        }

        $next = null;
        $window = [0, count($out)];
        if ($commit) {
            if ($offset === 0 && ! empty($opt['stop_on_error']) && $counts['ERROR'] > 0) {
                throw new ImportException("Nothing was imported because {$counts['ERROR']} row(s) have errors and you chose to stop on errors.");
            }
            [$out, $next] = $this->write($def, $out, $offset, microtime(true) + $budgetSeconds, $handler);
            $window = [$offset, $next ?? count($out)];
        }

        return ['rows' => $out, 'counts' => $counts, 'unknown' => $unknown, 'headers' => $t['headers'], 'window' => $window, 'next' => $next];
    }

    /**
     * Checks the rows of an entity that is one record per row, found by its code, name or engraving mark.
     *
     * @param  array<int, array{0:int, 1:array}>  $rows
     * @return array{0: array<int, array>, 1: array<string, array<string, int>>}  the checked rows, unrecognised values
     */
    private function check(array $def, array $rows, array $mapping, array $opt): array
    {
        $mode = $opt['mode'] ?? 'UPSERT';
        $matchBy = $opt['match_by'] ?? 'code';
        $fields = $def['fields'];
        $virtual = $def['virtual'] ?? [];
        $kind = $def['kind'];
        $isTool = ! empty($def['tool']);
        $table = $def['table'];
        $model = $def['model'];
        $revisionDef = $def['revisions'] ?? null;

        // What already exists.
        $cols = ['id', 'code', 'name', 'deleted_at'] + ($isTool ? [4 => 'mark_code'] : []);
        $byCode = $byName = $byMark = [];
        foreach (DB::table($table)->get($cols) as $r) {
            $rec = ['id' => $r->id, 'trashed' => $r->deleted_at !== null];
            $byCode[strtoupper($r->code)] = $rec;
            $byName[mb_strtolower((string) $r->name)][] = $rec;
            if ($isTool && $r->mark_code) {
                $byMark[strtoupper($r->mark_code)] = $rec;
            }
        }
        $markOwner = [];
        if ($isTool) {
            foreach (DB::table('tool_marks')->get(['tool_type', 'tool_id', 'mark_code']) as $m) {
                $markOwner[strtoupper($m->mark_code)] = [$m->tool_type, $m->tool_id];
            }
        }
        $coreByCode = $coreByName = [];
        $boxKey = [];
        if ($kind === 'core_box') {
            foreach (DB::table('core_definitions')->whereNull('deleted_at')->get(['id', 'code', 'name']) as $c) {
                $coreByCode[strtoupper($c->code)] = $c->id;
                $coreByName[mb_strtolower($c->name)][] = $c->id;
            }
            foreach (DB::table('core_boxes')->whereNull('deleted_at')->get(['id', 'core_definition_id', 'revision']) as $x) {
                $boxKey[$x->core_definition_id.'|'.strtoupper($x->revision)] = $x->id;
            }
        }

        $out = [];
        $unknown = [];
        $seen = [];
        $seenMarks = [];
        $seenBox = [];
        $matchedIds = [];

        foreach ($rows as [$n, $cells]) {
            $notes = [];
            [$parsed, $errors] = FieldParser::row($fields, $mapping, $cells, $opt, $unknown);

            // Match against what exists.
            $matchVal = $parsed[$matchBy] ?? null;
            $found = null;
            $display = $parsed['code'] ?? $parsed['name'] ?? null;
            if ($matchVal !== null && $matchVal !== '') {
                $mk = $matchBy === 'name' ? mb_strtolower((string) $matchVal) : strtoupper((string) $matchVal);
                if ($matchBy === 'name') {
                    $cands = $byName[$mk] ?? [];
                    if (count($cands) > 1) {
                        $errors[] = "More than one record is called '$matchVal'. Match on the code instead.";
                    } else {
                        $found = $cands[0] ?? null;
                    }
                } elseif ($matchBy === 'mark_code') {
                    $found = $byMark[$mk] ?? null;
                } else {
                    $found = $byCode[$mk] ?? null;
                }
                $sk = $matchBy.':'.$mk;
                if (isset($seen[$sk])) {
                    $errors[] = "Same $matchBy as row {$seen[$sk]}.";
                } else {
                    $seen[$sk] = $n;
                }
            }
            if ($found && $found['trashed']) {
                $errors[] = 'A record with this '.$matchBy.' exists in the deleted items, so it cannot be reused.';
                $found = null;
            }

            // Code given while matching on something else must not belong to another record.
            if ($matchBy !== 'code' && ! empty($parsed['code'])) {
                $c = $byCode[strtoupper($parsed['code'])] ?? null;
                if ($c && (! $found || $c['id'] !== $found['id'])) {
                    $errors[] = "Code {$parsed['code']} is already used by another record.";
                }
            }
            // Marks are unique across all tooling.
            if ($isTool && ! empty($parsed['mark_code'])) {
                $mark = $parsed['mark_code'];
                $own = $markOwner[$mark] ?? null;
                if ($own && (! $found || $own[1] !== $found['id'])) {
                    $errors[] = "Engraving mark $mark is already on another tool.";
                } elseif (isset($seenMarks[$mark])) {
                    $errors[] = "Engraving mark $mark is also on row {$seenMarks[$mark]}.";
                } else {
                    $seenMarks[$mark] = $n;
                }
            }

            // Core box: the core it makes.
            $coreId = null;
            if ($kind === 'core_box' && ! empty($parsed['core_definition'])) {
                $ref = (string) $parsed['core_definition'];
                $coreId = $coreByCode[strtoupper($ref)] ?? null;
                if (! $coreId) {
                    $cands = $coreByName[mb_strtolower($ref)] ?? [];
                    if (count($cands) === 1) {
                        $coreId = $cands[0];
                    } elseif (count($cands) > 1) {
                        $errors[] = "More than one core is called '$ref'. Use the core's code.";
                    } else {
                        $errors[] = "Core '$ref' was not found. Import the cores first, or check the spelling.";
                    }
                }
            }

            // Decide the action.
            if ($errors) {
                $action = 'ERROR';
            } elseif ($found && $mode === 'CREATE') {
                $action = 'SKIP';
                $notes[] = 'Already exists; this import only adds new records.';
            } elseif (! $found && $mode === 'UPDATE') {
                $action = 'SKIP';
                $notes[] = 'Not found; this import only updates existing records.';
            } else {
                $action = $found ? 'UPDATE' : 'CREATE';
            }

            if ($action === 'CREATE') {
                foreach ($fields as $key => $f) {
                    if ($f['type'] !== 'ref' && ($parsed[$key] ?? null) === null && isset($f['default']) && ! in_array($key, $virtual, true)) {
                        [$dv] = FieldParser::parse($key, $f, (string) $f['default'], $opt, []);
                        $parsed[$key] = $dv;
                    }
                }
                foreach ($fields as $key => $f) {
                    if (! empty($f['required']) && ($parsed[$key] ?? null) === null) {
                        $errors[] = $f['label'].' is required for a new record.';
                    }
                }
                if ($errors) {
                    $action = 'ERROR';
                }
            }

            // Core box revision must be unique per core.
            if ($kind === 'core_box' && $coreId && in_array($action, ['CREATE', 'UPDATE'], true)) {
                $rev = strtoupper((string) ($parsed['revision'] ?? ($action === 'UPDATE' ? '' : 'A')));
                if ($rev !== '') {
                    $k = $coreId.'|'.$rev;
                    $other = $boxKey[$k] ?? null;
                    if (($other && (! $found || $other !== $found['id'])) || isset($seenBox[$k])) {
                        $errors[] = "That core already has a box with revision $rev.";
                        $action = 'ERROR';
                    } else {
                        $seenBox[$k] = $n;
                    }
                }
            }

            $attrs = [];
            foreach ($fields as $key => $f) {
                if (in_array($key, $virtual, true) || $f['type'] === 'ref') {
                    continue;
                }
                if (($parsed[$key] ?? null) !== null) {
                    $attrs[$key] = $parsed[$key];
                }
            }
            if ($coreId) {
                $attrs['core_definition_id'] = $coreId;
            }
            $extra = [];
            if ($revisionDef && ! empty($parsed['revision'])) {
                $extra = ['revision' => strtoupper((string) $parsed['revision']), 'status' => $parsed['revision_status'] ?? ($fields['revision_status']['default'] ?? 'DRAFT')];
            }

            if ($found) {
                $matchedIds[$found['id']] = true;
            }
            $out[] = [
                'n' => $n, 'action' => $action, 'id' => $found['id'] ?? null, 'code' => $parsed['code'] ?? null, 'name' => $parsed['name'] ?? null,
                'display' => $display, 'errors' => $errors, 'notes' => $notes, 'attrs' => $attrs, 'extra' => $extra, 'changes' => [],
            ];
        }

        // Work out what an update would change.
        $existing = [];
        if ($matchedIds) {
            foreach (array_chunk(array_keys($matchedIds), 1000) as $ids) {
                foreach ($model::whereIn('id', $ids)->get() as $rec) {
                    $existing[$rec->id] = $rec;
                }
            }
        }
        $revisions = [];
        if ($revisionDef && $matchedIds) {
            $owner = $revisionDef['owner_key'];
            foreach ($revisionDef['model']::whereIn($owner, array_keys($matchedIds))->get([$owner, 'revision']) as $r) {
                $revisions[$r->{$owner}][strtoupper($r->revision)] = true;
            }
        }
        foreach ($out as &$row) {
            if ($row['action'] !== 'UPDATE') {
                continue;
            }
            $rec = $existing[$row['id']] ?? null;
            if (! $rec) {
                $row['action'] = 'ERROR';
                $row['errors'][] = 'The record disappeared during the check.';
                continue;
            }
            $row['code'] = $rec->code;
            $row['name'] = $rec->name;
            $row['display'] = $rec->code;
            foreach ($row['attrs'] as $key => $new) {
                $f = $fields[$key] ?? null;
                $old = $rec->getAttribute($key);
                if (! $this->same($f['type'] ?? 'string', $f['scale'] ?? 4, $old, $new)) {
                    $row['changes'][$key] = [$this->show($old), $this->show($new)];
                } else {
                    unset($row['attrs'][$key]);
                }
            }
            $addsRevision = $row['extra'] && ! isset($revisions[$row['id']][$row['extra']['revision']]);
            if ($row['extra'] && ! $addsRevision) {
                $row['extra'] = [];
            }
            if ($addsRevision) {
                $row['notes'][] = 'Adds revision '.$row['extra']['revision'].' ('.$row['extra']['status'].').';
            }
            if (! $row['changes'] && ! $addsRevision) {
                $row['action'] = 'UNCHANGED';
            }
        }
        unset($row);

        return [$out, $unknown];
    }

    /** @return array{0: array, 1: ?int} the rows with their outcomes, and the index to resume from (null when all are done) */
    private function write(array $def, array $rows, int $offset, float $deadline, ?ImportRowHandler $handler): array
    {
        $save = $handler ? fn (array &$r) => $handler->save($r) : fn (array &$r) => $this->save($def, $r);
        $total = count($rows);
        $i = $offset;
        // Small transactions: one huge transaction would exhaust the database's lock table on a big file.
        while ($i < $total) {
            $end = min($total, $i + 100);
            DB::transaction(function () use (&$rows, $i, $end, $save) {
                for ($k = $i; $k < $end; $k++) {
                    $r = &$rows[$k];
                    if (! in_array($r['action'], ['CREATE', 'UPDATE'], true)) {
                        unset($r);
                        continue;
                    }
                    try {
                        DB::transaction(function () use (&$r, $save) {
                            $save($r);
                        });
                    } catch (\Throwable $e) {
                        $r['action'] = 'ERROR';
                        $r['errors'][] = 'Could not be saved: '.mb_substr($e->getMessage(), 0, 200);
                    }
                    unset($r);
                }
            });
            $i = $end;
            if ($i < $total && microtime(true) > $deadline) {
                return [$rows, $i];
            }
        }

        return [$rows, null];
    }

    /** Writes one checked row of a code-matched entity, and the revision it adds. */
    private function save(array $def, array &$r): void
    {
        $model = $def['model'];
        if ($r['action'] === 'CREATE') {
            $rec = new $model();
            $rec->fill($r['attrs']);
            $rec->save();
        } else {
            $rec = $model::findOrFail($r['id']);
            if ($r['attrs']) {
                $rec->fill($r['attrs'])->save();
                if (! empty($def['tool']) && isset($r['attrs']['mark_code'])) {
                    app(CodeGenerator::class)->register($def['kind'], (string) $rec->getKey(), $r['attrs']['mark_code']);
                }
            }
        }
        if ($r['extra']) {
            $revisionDef = $def['revisions'];
            $copied = [];
            foreach ($revisionDef['copy'] ?? [] as $to => $from) {
                $copied[$to] = $rec->{$from};
            }
            $revisionDef['model']::firstOrCreate(
                [$revisionDef['owner_key'] => $rec->id, 'revision' => $r['extra']['revision']],
                ['status' => $r['extra']['status']] + $copied
            );
        }
        $r['code'] = $rec->code;
        $r['name'] = $rec->name;
        $r['id'] = $rec->getKey();
    }

    private function same(string $type, int $scale, mixed $old, mixed $new): bool
    {
        if ($old === null) {
            return false;
        }

        return match ($type) {
            'decimal' => abs((float) $old - (float) $new) < 0.5 * 10 ** -$scale,
            'int' => (int) $old === (int) $new,
            'bool' => (bool) $old === (bool) $new,
            'date' => (string) ($old instanceof \DateTimeInterface ? $old->format('Y-m-d') : substr((string) $old, 0, 10)) === (string) $new,
            default => trim((string) $old) === trim((string) $new),
        };
    }

    private function show(mixed $v): string
    {
        if ($v === null) {
            return '—';
        }
        if (is_bool($v)) {
            return $v ? 'yes' : 'no';
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }

        return (string) $v;
    }
}
