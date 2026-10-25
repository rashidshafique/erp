<?php

namespace App\Domains\Core\Tax;

/**
 * A country's tax pack: how its tariff code is written, its regions, the tax statuses and registration numbers a party can have,
 * the authorities that collect its taxes, and the starting taxes and rules loaded when the country is chosen. Rates and rules
 * themselves are kept as editable data, because they change with every budget; a pack only supplies the starting set.
 */
interface Jurisdiction
{
    /** Two-letter country code. */
    public function code(): string;

    public function name(): string;

    /** What the customs tariff code is called on documents: PCT code, HS code, HSN code. */
    public function tariffLabel(): string;

    /** How many digits the tariff code has, and after which digit the dot goes (null for none). */
    public function tariffDigits(): int;

    public function tariffSplit(): ?int;

    /** @return array<string, string> code => name, the regions a service can be supplied in */
    public function regions(): array;

    /** @return array<string, string> code => label, the tax statuses a customer or supplier can have */
    public function partyStatuses(): array;

    /** @return array<string, string> key => label, the registration numbers a customer, supplier or the company can hold */
    public function registrationFields(): array;

    /**
     * The authorities that collect tax.
     *
     * @return list<array{code: string, name: string, level: string, region: ?string, registration: string}>
     */
    public function authorities(): array;

    /**
     * The starting taxes, each a rate collected by an authority.
     *
     * @return list<array{code: string, name: string, authority: string, kind: string, rate: float, base: string, effective_from: string, note?: string}>
     */
    public function defaultComponents(): array;

    /**
     * The starting rules: when they apply and which taxes they add.
     *
     * @return list<array{name: string, priority: int, side: string, conditions: array<string, mixed>, components: list<string>}>
     */
    public function defaultRules(): array;
}
