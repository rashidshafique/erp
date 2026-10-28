<?php

namespace App\Services\Stock;

/**
 * The kinds of place stock can be kept, by key. An external kind is a place that is not ours, such as a subcontractor's works:
 * the stock is still ours but sits with someone else, so it is counted apart from what our own stores hold. A module adds its
 * own kinds from its service provider.
 */
class StockLocationTypes
{
    /** @var array<string, array{label: string, external: bool}> */
    private array $types = [
        'STORE' => ['label' => 'Store', 'external' => false],
        'YARD' => ['label' => 'Yard', 'external' => false],
        'BIN' => ['label' => 'Bin or rack', 'external' => false],
        'LINE_SIDE' => ['label' => 'Line side', 'external' => false],
        'QUARANTINE' => ['label' => 'Quarantine', 'external' => false],
        'SCRAP' => ['label' => 'Scrap yard', 'external' => false],
    ];

    public function register(string $key, string $label, bool $external = false): void
    {
        $this->types[$key] = ['label' => $label, 'external' => $external];
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function label(string $key): string
    {
        return $this->types[$key]['label'] ?? $key;
    }

    public function isExternal(string $key): bool
    {
        return $this->types[$key]['external'] ?? false;
    }

    /** @return array<string, string> Key => label. */
    public function options(): array
    {
        return array_map(fn (array $type) => $type['label'], $this->types);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->types);
    }
}
