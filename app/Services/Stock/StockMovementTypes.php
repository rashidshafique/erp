<?php

namespace App\Services\Stock;

use InvalidArgumentException;

/**
 * The movement types the ledger knows. `effect` is what a movement does to the quantity held: +1 adds, -1 takes away, 0
 * only changes where it sits. The reconciliation and location balances read the effect from here, so a module that registers
 * a type is counted without either being changed.
 */
class StockMovementTypes
{
    /** @var array<string, array{label: string, effect: int}> */
    private array $types = [
        'RECEIPT' => ['label' => 'Receipt', 'effect' => 1],
        'ISSUE' => ['label' => 'Issue', 'effect' => -1],
        'TRANSFER' => ['label' => 'Transfer', 'effect' => 0],
    ];

    public function register(string $key, string $label, int $effect): void
    {
        if (! in_array($effect, [-1, 0, 1], true)) {
            throw new InvalidArgumentException('A movement type adds (1), takes away (-1) or only moves (0).');
        }
        $this->types[$key] = ['label' => $label, 'effect' => $effect];
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function label(string $key): string
    {
        return $this->types[$key]['label'] ?? $key;
    }

    public function effect(string $key): int
    {
        return $this->types[$key]['effect'] ?? throw new InvalidArgumentException("Unknown movement type {$key}.");
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->types);
    }

    /** The types that add to the quantity held. @return list<string> */
    public function adding(): array
    {
        return array_keys(array_filter($this->types, fn ($t) => $t['effect'] > 0));
    }

    /** The types that take from the quantity held. @return list<string> */
    public function removing(): array
    {
        return array_keys(array_filter($this->types, fn ($t) => $t['effect'] < 0));
    }

    /** The types that put stock into a place: adds and moves. @return list<string> */
    public function arrivingAtPlace(): array
    {
        return array_keys(array_filter($this->types, fn ($t) => $t['effect'] >= 0));
    }

    /** The types that take stock out of a place: removals and moves. @return list<string> */
    public function leavingPlace(): array
    {
        return array_keys(array_filter($this->types, fn ($t) => $t['effect'] <= 0));
    }
}
