<?php

namespace App\Services\Patterns;

use App\Models\AuditLog;
use App\Models\Pattern;
use App\Models\PatternRevisionCavity;
use App\Models\ProductRevision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Everything recorded about a pattern in the audit trail, in one list: the pattern itself, its revisions (with layout
 * corrections and their reasons), the rows of their cavity maps, including removed ones, and its photos and files.
 */
class PatternHistory
{
    /** Fields that only link records together; they say nothing to a reader. */
    private const HIDDEN = ['id', 'pattern_id', 'pattern_revision_id', 'attachable_id', 'attachable_type', 'disk', 'path', 'sha256', 'custom_fields',
        'copied_from_revision_id', 'uploaded_by_id', 'sequence', 'needs_review'];

    /**
     * @return Collection<int, array{at: Carbon, who: ?string, what: string, action: string, changes: array<string, array{old?: mixed, new?: mixed}>, reason: ?string}>
     */
    public function for(Pattern $pattern, int $limit = 60): Collection
    {
        $revisions = $pattern->revisions()->pluck('code', 'id');
        $cavities = PatternRevisionCavity::whereIn('pattern_revision_id', $revisions->keys())->get(['id', 'cavity_number', 'pattern_revision_id'])->keyBy('id');

        $entries = AuditLog::query()
            ->where(function (Builder $q) use ($pattern, $revisions, $cavities) {
                $q->where(fn ($q) => $q->where('entity_type', 'Pattern')->where('entity_id', $pattern->id))
                    ->orWhere(fn ($q) => $q->where('entity_type', 'PatternRevision')->whereIn('entity_id', $revisions->keys()))
                    ->orWhere(fn ($q) => $q->where('entity_type', 'PatternRevisionCavity')->whereIn('entity_id', $cavities->keys()))
                    // Removed cavity rows are found by the revision they belonged to.
                    ->orWhere(function ($q) use ($revisions) {
                        $q->where('entity_type', 'PatternRevisionCavity')->where(function ($q) use ($revisions) {
                            foreach ($revisions->keys() as $id) {
                                $q->orWhereRaw('changes::text like ?', ['%'.$id.'%']);
                            }
                        });
                    })
                    ->orWhere(fn ($q) => $q->where('entity_type', 'Attachment')->whereRaw('changes::text like ?', ['%'.$pattern->id.'%']));
            })
            ->orderByDesc('occurred_at')->orderByDesc('id')->limit($limit)->get();

        $products = $this->productLabels($entries);

        return $entries->map(function (AuditLog $e) use ($revisions, $cavities, $products) {
            $set = $e->changeSet();

            return [
                'at' => $e->occurred_at, 'who' => $e->user_name, 'action' => $e->action, 'reason' => $e->reason,
                'what' => $this->what($e, $set, $revisions, $cavities),
                'changes' => collect($set)->except(self::HIDDEN)->mapWithKeys(function ($v, $field) use ($products) {
                    $show = fn ($x) => $field === 'product_revision_id' && $x !== null ? ($products[$x] ?? 'another product') : $x;

                    return [$this->label($field) => array_map($show, $v)];
                })->all(),
            ];
        });
    }

    /** What an entry is about, in words: "Pattern", "Revision P-0123-R1", "Cavity 2 of P-0123-R1", "File front.jpg". */
    private function what(AuditLog $e, array $set, Collection $revisions, Collection $cavities): string
    {
        return match ($e->entity_type) {
            'Pattern' => 'Pattern',
            'PatternRevision' => 'Revision '.($revisions[$e->entity_id] ?? $e->entity_label),
            'PatternRevisionCavity' => (function () use ($e, $set, $revisions, $cavities) {
                $cavity = $cavities[$e->entity_id] ?? null;
                $number = $cavity?->cavity_number ?? ($set['cavity_number']['new'] ?? $set['cavity_number']['old'] ?? '?');
                $revisionId = $cavity?->pattern_revision_id ?? ($set['pattern_revision_id']['new'] ?? $set['pattern_revision_id']['old'] ?? null);

                return 'Cavity '.$number.($revisionId && isset($revisions[$revisionId]) ? ' of '.$revisions[$revisionId] : '');
            })(),
            'Attachment' => ($set['category']['new'] ?? null) === 'PHOTO' ? 'Photo '.($set['original_name']['new'] ?? '') : 'File '.($set['original_name']['new'] ?? $e->entity_label),
            default => $e->entity_type,
        };
    }

    /** "casting_weight_kg" → "Casting weight kg". */
    private function label(string $field): string
    {
        return ['mark_code' => 'Engraved mark', 'cavities_per_mould' => 'Cavities on the layout', 'layers_per_mould' => 'Flask sections',
            'product_revision_id' => 'Product', 'is_current' => 'Current', 'superseded_at' => 'Superseded', 'change_description' => 'What changed',
            'pour_weight_kg' => 'Poured weight kg', 'poured_weight_kg' => 'Poured weight kg', 'cavity_mark' => 'Cavity mark',
            'mould_arrangement' => 'Arrangement', 'deleted_at' => 'Archived'][$field] ?? Str::ucfirst(str_replace('_', ' ', $field));
    }

    /** @return array<string, string> product revision id => "VALVE-1 rev A" */
    private function productLabels(Collection $entries): array
    {
        $ids = $entries->flatMap(fn (AuditLog $e) => array_filter([$e->changeSet()['product_revision_id']['old'] ?? null, $e->changeSet()['product_revision_id']['new'] ?? null]))->unique()->values();

        return ProductRevision::with('product')->whereIn('id', $ids)->get()->mapWithKeys(fn ($r) => [$r->id => ($r->product?->code ?? '?').' rev '.$r->revision])->all();
    }
}
