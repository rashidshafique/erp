<?php

namespace App\Services\Import;

use App\Models\PatternRevision;
use App\Models\PatternRevisionCavity;
use Illuminate\Support\Facades\DB;

/**
 * Imports the cavity map of patterns: one row per cavity, saying which product revision (or component of it) the cavity makes.
 * A row is the same cavity when its pattern, pattern revision and cavity number match. A pattern revision the pattern does not
 * have yet is added; products and their revisions must already exist. The checks follow the rules of the cavity map screen.
 */
class PatternCavityRows implements ImportRowHandler
{
    /** Fields copied onto the cavity record. */
    private const ATTRS = ['product_revision_id', 'cavity_role', 'component_label', 'pieces_per_product', 'casting_weight_kg', 'poured_weight_kg', 'notes'];

    public function check(array $def, array $rows, array $mapping, array $options): array
    {
        $mode = $options['mode'] ?? 'UPSERT';
        $fields = $def['fields'];

        $patterns = $this->lookup(DB::table('patterns')->whereNull('deleted_at')->get(['id', 'code', 'name', 'mark_code']), true);
        $products = $this->lookup(DB::table('products')->whereNull('deleted_at')->get(['id', 'code', 'name']), false);
        $patternRevisions = [];
        foreach (DB::table('pattern_revisions')->get(['id', 'pattern_id', 'revision', 'cavities_per_mould']) as $r) {
            $patternRevisions[$r->pattern_id][strtoupper($r->revision)] = $r;
        }
        $productRevisions = [];
        $productRevisionById = [];
        foreach (DB::table('product_revisions')->get(['id', 'product_id', 'revision', 'status']) as $r) {
            $productRevisions[$r->product_id][strtoupper($r->revision)] = $r;
            $productRevisionById[$r->id] = $r;
        }
        $cavities = [];
        foreach (DB::table('pattern_revision_cavities')->get() as $c) {
            $cavities[$c->pattern_revision_id.'|'.$c->cavity_number] = $c;
        }
        $usedOnMoulds = [];
        foreach (DB::table('mould_cavities')->select('pattern_revision_id', 'cavity_number')->distinct()->get() as $m) {
            $usedOnMoulds[$m->pattern_revision_id.'|'.$m->cavity_number] = true;
        }
        $productLabel = function (string $productRevisionId) use ($productRevisionById, $products): string {
            $r = $productRevisionById[$productRevisionId];

            return $products['byId'][$r->product_id]->code.' rev '.$r->revision;
        };

        $out = [];
        $unknown = [];
        $seen = [];
        $addedRevisions = [];

        foreach ($rows as [$n, $cells]) {
            $notes = [];
            [$parsed, $errors] = FieldParser::row($fields, $mapping, $cells, $options, $unknown);
            $number = $parsed['cavity_number'];

            // The pattern and the revision of it.
            $pattern = $this->find($patterns, $parsed['pattern'], 'pattern', $errors);
            $patternRevision = null;
            $newRevision = null;
            if ($pattern) {
                $own = $patternRevisions[$pattern->id] ?? [];
                $rev = strtoupper((string) $parsed['pattern_revision']);
                if ($rev !== '') {
                    $patternRevision = $own[$rev] ?? null;
                    $newRevision = $patternRevision ? null : $rev;
                } elseif (count($own) === 1) {
                    $patternRevision = reset($own);
                } elseif ($own === []) {
                    $newRevision = '0';
                } else {
                    $errors[] = "Pattern {$pattern->code} has revisions ".implode(', ', array_keys($own)).'. Say which one this cavity is on.';
                }
            }

            // The product revision the cavity makes.
            $productRevision = null;
            $product = $this->find($products, $parsed['product'], 'product', $errors);
            if ($product) {
                $own = $productRevisions[$product->id] ?? [];
                $rev = strtoupper((string) $parsed['product_revision']);
                if ($rev !== '') {
                    $productRevision = $own[$rev] ?? null;
                    if (! $productRevision) {
                        $errors[] = "Product {$product->code} has no revision $rev. Add it to the product first.";
                    }
                } else {
                    $current = array_filter($own, fn ($r) => $r->status !== 'OBSOLETE');
                    if (count($current) === 1) {
                        $productRevision = reset($current);
                    } elseif ($current === []) {
                        $errors[] = "Product {$product->code} has no current revision. Add one to the product first.";
                    } else {
                        $errors[] = "Product {$product->code} has revisions ".implode(', ', array_keys($current)).'. Say which one this cavity makes.';
                    }
                }
                if ($productRevision && $productRevision->status === 'OBSOLETE') {
                    $errors[] = "Product {$product->code} revision {$productRevision->revision} is obsolete and cannot be linked to a pattern cavity.";
                    $productRevision = null;
                }
            }

            if ($parsed['pattern'] === null) {
                $errors[] = 'Pattern is required.';
            }
            if ($number === null) {
                $errors[] = 'Cavity number is required.';
            }

            // The same cavity: existing, or earlier in this file.
            $found = $patternRevision && $number !== null ? ($cavities[$patternRevision->id.'|'.$number] ?? null) : null;
            $revisionLabel = $patternRevision->revision ?? $newRevision;
            if ($pattern && $revisionLabel !== null && $number !== null) {
                $key = $pattern->id.'|'.strtoupper($revisionLabel).'|'.$number;
                if (isset($seen[$key])) {
                    $errors[] = "Same pattern revision and cavity number as row {$seen[$key]}.";
                } else {
                    $seen[$key] = $n;
                }
            }

            if ($errors) {
                $action = 'ERROR';
            } elseif ($found && $mode === 'CREATE') {
                $action = 'SKIP';
                $notes[] = 'This cavity already has a product; this import only adds new cavities.';
            } elseif (! $found && $mode === 'UPDATE') {
                $action = 'SKIP';
                $notes[] = 'This cavity is not in the map yet; this import only updates existing cavities.';
            } else {
                $action = $found ? 'UPDATE' : 'CREATE';
            }

            if ($action === 'CREATE') {
                foreach (['cavity_role', 'pieces_per_product'] as $key) {
                    if ($parsed[$key] === null) {
                        [$parsed[$key]] = FieldParser::parse($key, $fields[$key], (string) $fields[$key]['default'], $options, []);
                    }
                }
                if ($parsed['product'] === null) {
                    $errors[] = 'Product is required for a new cavity.';
                }
            }

            // The rules of the cavity map, on the values the cavity will have after the import.
            if (in_array($action, ['CREATE', 'UPDATE'], true)) {
                $role = $parsed['cavity_role'] ?? $found?->cavity_role;
                $label = $role === 'WHOLE' ? null : ($parsed['component_label'] ?? $found?->component_label);
                $casting = $parsed['casting_weight_kg'] ?? $found?->casting_weight_kg;
                $poured = $parsed['poured_weight_kg'] ?? $found?->poured_weight_kg;
                $limit = $patternRevision?->cavities_per_mould;
                if ($limit !== null && $number > (int) $limit) {
                    $errors[] = "Pattern {$pattern->code} revision {$patternRevision->revision} has $limit cavities per mould; cavity $number does not exist.";
                }
                if ($role === 'PART' && blank($label)) {
                    $errors[] = 'Name the component this cavity makes: it makes a part, not a whole casting.';
                }
                foreach (['casting_weight_kg' => $casting, 'poured_weight_kg' => $poured] as $key => $kg) {
                    if ($kg !== null && (float) $kg <= 0) {
                        $errors[] = $fields[$key]['label'].' must be above 0.';
                    }
                }
                if ($casting !== null && $poured !== null && (float) $poured < (float) $casting) {
                    $errors[] = 'Poured weight (casting plus gating and risers) cannot be below the casting weight.';
                }
                if ($found && $productRevision && $productRevision->id !== $found->product_revision_id && isset($usedOnMoulds[$found->pattern_revision_id.'|'.$number])) {
                    $errors[] = "Cavity $number is already used on a mould, so its product cannot change. Add a new pattern revision instead.";
                }
                if ($errors) {
                    $action = 'ERROR';
                }
            }

            $attrs = array_filter([
                'product_revision_id' => $productRevision?->id,
                'cavity_role' => $parsed['cavity_role'],
                'component_label' => $parsed['component_label'],
                'pieces_per_product' => $parsed['pieces_per_product'],
                'casting_weight_kg' => $parsed['casting_weight_kg'],
                'poured_weight_kg' => $parsed['poured_weight_kg'],
                'notes' => $parsed['notes'],
            ], fn ($v) => $v !== null);
            if (($attrs['cavity_role'] ?? null) === 'WHOLE' && ($found?->component_label !== null || isset($attrs['component_label']))) {
                $attrs['component_label'] = null;
            }

            $changes = [];
            if ($action === 'UPDATE') {
                foreach ($attrs as $key => $new) {
                    $old = $found->{$key};
                    $same = in_array($key, ['casting_weight_kg', 'poured_weight_kg'], true)
                        ? $old !== null && abs((float) $old - (float) $new) < 0.00005
                        : (string) $old === (string) $new && ($old === null) === ($new === null);
                    if ($same) {
                        unset($attrs[$key]);
                    } elseif ($key === 'product_revision_id') {
                        $changes['product'] = [$productLabel($old), $productLabel($new)];
                    } else {
                        $changes[$key] = [$old ?? '—', $new ?? '—'];
                    }
                }
                if (! $changes) {
                    $action = 'UNCHANGED';
                }
            }

            $extra = [];
            if ($newRevision !== null && $action === 'CREATE') {
                $status = $parsed['revision_status'] ?? $fields['revision_status']['default'];
                $extra = ['pattern_id' => $pattern->id, 'revision' => $newRevision, 'status' => $status];
                if (! isset($addedRevisions[$pattern->id.'|'.$newRevision])) {
                    $addedRevisions[$pattern->id.'|'.$newRevision] = true;
                    $notes[] = "Adds pattern revision $newRevision ($status).";
                }
            } elseif ($patternRevision) {
                $extra = ['pattern_revision_id' => $patternRevision->id];
            }

            $makes = $productRevision ? $productLabel($productRevision->id) : ($found ? $productLabel($found->product_revision_id) : null);
            $role = $parsed['cavity_role'] ?? $found?->cavity_role;
            $part = $role === 'PART' ? ($parsed['component_label'] ?? $found?->component_label) : null;
            $out[] = [
                'n' => $n, 'action' => $action, 'id' => $found?->id, 'errors' => $errors, 'notes' => $notes, 'attrs' => $attrs + ['cavity_number' => $number],
                'extra' => $extra, 'changes' => $changes,
                'code' => $pattern ? $pattern->code.' rev '.($revisionLabel ?? '?').' · cavity '.($number ?? '?') : null,
                'name' => $makes ? $makes.($part ? " · part: $part" : '') : null,
                'display' => null,
            ];
        }

        return [$out, $unknown];
    }

    public function save(array &$row): void
    {
        $revisionId = $row['extra']['pattern_revision_id'] ?? null;
        if (! $revisionId) {
            // Several rows may add the same revision; the first one creates it.
            $revisionId = PatternRevision::firstOrCreate(
                ['pattern_id' => $row['extra']['pattern_id'], 'revision' => $row['extra']['revision']],
                ['status' => $row['extra']['status']]
            )->id;
        }
        if ($row['action'] === 'CREATE') {
            $cavity = PatternRevisionCavity::create(['pattern_revision_id' => $revisionId] + $row['attrs']);
        } else {
            $cavity = PatternRevisionCavity::findOrFail($row['id']);
            $cavity->update(array_intersect_key($row['attrs'], array_flip(self::ATTRS)));
        }
        $row['id'] = $cavity->id;
    }

    /**
     * @return array{byCode: array, byMark: array, byName: array, byId: array}
     */
    private function lookup(iterable $records, bool $hasMark): array
    {
        $out = ['byCode' => [], 'byMark' => [], 'byName' => [], 'byId' => []];
        foreach ($records as $r) {
            $out['byId'][$r->id] = $r;
            $out['byCode'][strtoupper($r->code)] = $r;
            $out['byName'][mb_strtolower((string) $r->name)][] = $r;
            if ($hasMark && $r->mark_code) {
                $out['byMark'][strtoupper($r->mark_code)] = $r;
            }
        }

        return $out;
    }

    /** Finds a pattern or product by its code, then its engraving mark, then its name, adding an error when it cannot. */
    private function find(array $lookup, ?string $ref, string $what, array &$errors): ?object
    {
        if ($ref === null) {
            return null;
        }
        $hit = $lookup['byCode'][strtoupper($ref)] ?? $lookup['byMark'][strtoupper($ref)] ?? null;
        if ($hit) {
            return $hit;
        }
        $named = $lookup['byName'][mb_strtolower($ref)] ?? [];
        if (count($named) === 1) {
            return $named[0];
        }
        $errors[] = $named
            ? "More than one $what is called '$ref'. Use its code."
            : ucfirst($what)." '$ref' was not found. Import the {$what}s first, or check the spelling.";

        return null;
    }
}
