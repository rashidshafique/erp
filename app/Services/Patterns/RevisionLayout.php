<?php

namespace App\Services\Patterns;

use App\Models\MouldAssembly;
use App\Models\PatternRevision;
use App\Services\Audit\Audit;
use Illuminate\Validation\ValidationException;

/** Saving a pattern revision's mould layout, with the rule for revisions that moulds have already been assembled from. */
class RevisionLayout
{
    /** Moulds assembled with this revision's layout. */
    public function assembledMoulds(PatternRevision $revision): int
    {
        return MouldAssembly::whereHas('layers.cavities', fn ($q) => $q->where('pattern_revision_id', $revision->id))->count();
    }

    /**
     * Saves a revision's mould layout. Once moulds have been assembled with it, a change needs a reason, which is audited, and
     * those moulds keep the castings they recorded. Returns what happened, or null when nothing changed.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(PatternRevision $revision, array $data, ?string $reason): ?string
    {
        $revision->fill($data);
        // Filling in the flask sections a revision never recorded (2, or 1 for a flaskless line) is not a change of layout.
        $implicit = $revision->getOriginal('layers_per_mould') === null && $revision->isDirty('layers_per_mould')
            && (int) $revision->layers_per_mould === ($revision->mould_arrangement === 'VERTICAL' ? 1 : 2);
        if (! $revision->isDirty() || ($implicit && array_keys($revision->getDirty()) === ['layers_per_mould'])) {
            if ($implicit) {
                $revision->saveQuietly();
            }

            return null;
        }
        $assembled = $this->assembledMoulds($revision);
        if (! $assembled) {
            $revision->save();

            return "Mould layout of {$revision->code} saved.";
        }

        $reason = trim((string) $reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['correction_reason' => "{$assembled} ".($assembled === 1 ? 'mould was' : 'moulds were')." assembled with the old layout of {$revision->code}. Say why the layout is being corrected; those moulds keep the castings they recorded."]);
        }
        $changes = collect($revision->getDirty())->mapWithKeys(fn ($new, $field) => [$field => ['old' => $revision->getOriginal($field), 'new' => $new]])->all();
        $revision->saveQuietly();
        app(Audit::class)->record('UPDATED', 'PatternRevision', $revision->id, $revision->code, $changes, 'Layout corrected after '.$assembled.' assembled '.($assembled === 1 ? 'mould' : 'moulds').': '.$reason);

        return "Mould layout of {$revision->code} corrected. The {$assembled} ".($assembled === 1 ? 'mould' : 'moulds').' already assembled keep the castings they recorded.';
    }
}
