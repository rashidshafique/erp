<?php

namespace App\Services\Stock;

use InvalidArgumentException;

/** The kinds of item the ledger can hold, by key. A module adds its own from its service provider. */
class StockItemTypes
{
    /** @var array<string, class-string<Stockable>> */
    private array $types = [];

    /** @param class-string<Stockable> $model */
    public function register(string $key, string $model): void
    {
        if (! is_subclass_of($model, Stockable::class)) {
            throw new InvalidArgumentException("{$model} does not implement Stockable.");
        }
        $this->types[$key] = $model;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /** @return class-string<Stockable> */
    public function modelFor(string $key): string
    {
        return $this->types[$key] ?? throw new InvalidArgumentException("Unknown stock item type {$key}.");
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->types);
    }

    public function find(string $key, string $id): ?Stockable
    {
        return $this->has($key) ? $this->modelFor($key)::find($id) : null;
    }
}
