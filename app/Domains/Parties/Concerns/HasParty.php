<?php

namespace App\Domains\Parties\Concerns;

use App\Domains\Parties\Models\Party;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Makes a model a role of a business partner. Name, contacts, addresses, tax number and the active flag are read
 * from, and saved to, the party, so `$customer->name` and `Customer::create(['name' => ...])` keep working.
 */
trait HasParty
{
    public function initializeHasParty(): void
    {
        $this->with[] = 'party';
        if ($this->fillable !== []) {
            $this->fillable[] = 'party_id';
        }
    }

    public static function bootHasParty(): void
    {
        static::saving(fn ($model) => $model->savePartyFields());
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    public function getAttribute($key)
    {
        if (in_array($key, Party::FIELDS, true) && ! array_key_exists($key, $this->attributes)) {
            return $this->party?->{$key};
        }

        return parent::getAttribute($key);
    }

    /** Moves party fields set on this model onto its party, creating the party when the role has none yet. */
    protected function savePartyFields(): void
    {
        $fields = array_intersect_key($this->attributes, array_flip(Party::FIELDS));
        if ($fields === [] && $this->party_id) {
            return;
        }
        $this->attributes = array_diff_key($this->attributes, $fields);
        $party = $this->party_id ? ($this->party ?? Party::withTrashed()->find($this->party_id)) : null;
        if ($party) {
            $party->fill($fields)->save();
        } else {
            $party = Party::create($fields);
            $this->party_id = $party->id;
        }
        $this->setRelation('party', $party);
    }

    /** Orders by the party's name. */
    public function scopeOrderedByName(Builder $query): Builder
    {
        return $query->orderBy(Party::select('name')->whereColumn('parties.id', $this->qualifyColumn('party_id'))->limit(1));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('party', fn ($q) => $q->where('active', true));
    }

    /** Matches the role's own code or the party's name. */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $like = '%'.trim($term).'%';

        return $query->where(fn ($w) => $w->where($this->qualifyColumn('code'), 'ilike', $like)->orWhereHas('party', fn ($q) => $q->where('name', 'ilike', $like)));
    }
}
