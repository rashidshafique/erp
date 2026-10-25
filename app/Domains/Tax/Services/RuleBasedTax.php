<?php

namespace App\Domains\Tax\Services;

use App\Domains\Core\Contracts\TaxCalculator;
use App\Domains\Core\FlatRateTax;
use App\Domains\Core\Tax\TaxAmount;
use App\Domains\Core\Tax\TaxContext;
use App\Domains\Core\Tax\TaxLine;
use App\Domains\Core\Tax\TaxResult;
use App\Domains\Tax\Models\PartyTaxProfile;
use App\Domains\Tax\Models\TaxAuthority;
use App\Domains\Tax\Models\TaxComponent;
use App\Domains\Tax\Models\TaxRule;
use App\Support\TaxJurisdiction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Taxes a sales document by the country's rules. Every active rule in force on the document date whose conditions hold for a
 * line adds its taxes, at the rates in force that day. Charged taxes come first, then the withheld ones, so a withheld tax can be
 * a share of a charged one or of the value with tax. A tax of an authority the company is not registered with is left off (once
 * any registration is recorded). A line no rule matches keeps the percent chosen on it.
 *
 * Purchases keep the percent typed from the supplier's document: that is the input tax actually charged.
 */
class RuleBasedTax implements TaxCalculator
{
    public function __construct(private FlatRateTax $flat) {}

    public function calculate(TaxContext $context, array $lines): TaxResult
    {
        if ($context->side !== TaxContext::SALE) {
            return $this->flat->calculate($context, $lines);
        }
        $setup = $this->setupFor($context);

        return new TaxResult(array_map(fn (TaxLine $line) => $this->taxLine($line, $setup)['taxes'], array_values($lines)));
    }

    public function issueChecks(TaxContext $context, array $lines): array
    {
        $found = ['blocks' => [], 'warnings' => []];
        if ($context->side !== TaxContext::SALE) {
            return $found;
        }
        $add = function (string $rule, string $message) use (&$found) {
            $level = TaxRules::get($rule);
            if ($level !== 'OFF') {
                $found[$level === 'ENFORCED' ? 'blocks' : 'warnings'][] = $message;
            }
        };
        $setup = $this->setupFor($context);
        if ($setup['components']->isEmpty()) {
            return $found;   // no country taxes set up yet: invoices work as without the module
        }
        $taxed = false;
        $label = TaxJurisdiction::tariffLabel();
        foreach (array_values($lines) as $i => $line) {
            $result = $this->taxLine($line, $setup);
            $taxed = $taxed || collect($result['taxes'])->contains(fn (TaxAmount $t) => ! $t->withheld && $t->amount > 0);
            if ($line->supplyType === TaxLine::GOODS && blank($line->tariffCode)) {
                $add('tariff_code', 'Line '.($i + 1)." has no {$label}.");
            }
            foreach ($result['unregistered'] as $authority) {
                $add('unregistered_authority', 'Line '.($i + 1)." would be taxed by {$authority->name}, which you are not registered with, so it carries none of its tax.");
            }
        }
        $registrations = array_filter($setup['profile']?->registrations ?? []);
        if ($taxed && $registrations === []) {
            $add('buyer_registration', 'The buyer has no registration number on file (set it on the business partner page).');
        }

        return $found;
    }

    public function registrations(?string $partyId): array
    {
        $pack = TaxJurisdiction::pack();
        $fields = $pack?->registrationFields() ?? [];
        $company = TaxSetup::company()['registrations'];
        $profile = $partyId ? PartyTaxProfile::firstWhere('party_id', $partyId) : null;
        $named = fn (array $numbers) => collect($numbers)->filter()->mapWithKeys(fn ($v, $k) => [explode(' (', $fields[$k] ?? $k)[0] => $v])->all();
        $party = $named($profile?->registrations ?? []);
        if ($party === [] && $partyId && ($number = DB::table('parties')->where('id', $partyId)->value('tax_number'))) {
            $party = ['Tax no.' => $number];
        }

        return ['seller' => $named($company), 'party' => $party];
    }

    /**
     * Everything a document's lines are taxed against: rates and rules in force on its date, the other party's profile, the
     * place of supply, and the authorities the company is registered with.
     *
     * @return array{components: Collection<string, TaxComponent>, rules: Collection<int, TaxRule>, profile: ?PartyTaxProfile, region: ?string, side: string, registered: Collection<int, string>}
     */
    private function setupFor(TaxContext $context): array
    {
        $code = TaxJurisdiction::code();
        $profile = $context->partyId ? PartyTaxProfile::firstWhere('party_id', $context->partyId) : null;
        $date = $context->date->toDateString();

        return [
            'components' => TaxComponent::with('authority')->where('jurisdiction', $code)->on($context->date)->get()->keyBy('code'),
            'rules' => TaxRule::where('jurisdiction', $code)->where('is_active', true)->whereIn('side', ['BOTH', $context->side])
                ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
                ->orderBy('priority')->orderBy('name')->get(),
            'profile' => $profile,
            'region' => $context->placeOfSupply ?: $profile?->region ?: TaxSetup::company()['region'],
            'side' => $context->side,
            'registered' => TaxAuthority::where('jurisdiction', $code)->where('is_registered', true)->pluck('id'),
        ];
    }

    /**
     * The taxes on one line, and the authorities whose tax was left off because the company is not registered with them.
     *
     * @return array{taxes: list<TaxAmount>, unregistered: list<TaxAuthority>}
     */
    private function taxLine(TaxLine $line, array $setup): array
    {
        $codes = $setup['rules']->filter(fn (TaxRule $rule) => $this->matches($rule->conditions ?? [], $line, $setup))
            ->flatMap(fn (TaxRule $rule) => $rule->components ?? [])->unique()->values();
        $components = $codes->map(fn ($c) => $setup['components'][$c] ?? null)->filter();
        if ($components->isEmpty()) {
            return ['taxes' => $this->flat->flat($line->net, $line->percent), 'unregistered' => []];
        }

        $unregistered = [];
        if ($setup['registered']->isNotEmpty()) {
            $components = $components->reject(function (TaxComponent $c) use ($setup, &$unregistered) {
                $out = $c->kind === 'CHARGED' && ! $setup['registered']->contains($c->tax_authority_id);
                if ($out) {
                    $unregistered[$c->tax_authority_id] = $c->authority;
                }

                return $out;
            });
        }

        $amounts = [];
        foreach ([$components->where('kind', 'CHARGED'), $components->where('kind', 'WITHHELD')] as $group) {
            foreach ($group as $c) {
                $base = match ($c->base) {
                    'NET' => $line->net,
                    'GROSS' => $line->net + array_sum(array_map(fn (TaxAmount $t) => $t->withheld ? 0 : $t->amount, $amounts)),
                    default => isset($amounts[$c->base]) ? $amounts[$c->base]->amount : null,
                };
                if ($base === null) {
                    continue;
                }
                $amounts[$c->code] = new TaxAmount($c->code, $c->name, round($base, 2), (float) $c->rate, round($base * (float) $c->rate / 100, 2), $c->kind === 'WITHHELD', $c->authority?->code);
            }
        }

        return ['taxes' => array_values($amounts), 'unregistered' => array_values($unregistered)];
    }

    /** Whether every condition of a rule holds for a line. */
    private function matches(array $conditions, TaxLine $line, array $setup): bool
    {
        $profile = $setup['profile'];
        $digits = preg_replace('/[\s.]/', '', (string) $line->tariffCode);

        return (! isset($conditions['supply_type']) || $conditions['supply_type'] === $line->supplyType)
            && (! isset($conditions['regions']) || in_array($setup['region'], $conditions['regions'], true))
            && (! isset($conditions['party_status']) || in_array($profile?->tax_status, $conditions['party_status'], true))
            && (! isset($conditions['withholding_agent']) || (bool) $conditions['withholding_agent'] === (bool) $profile?->is_withholding_agent)
            && (! isset($conditions['inventory_classes']) || in_array($line->inventoryClass, $conditions['inventory_classes'], true))
            && (! isset($conditions['code_prefix']) || ($digits !== '' && str_starts_with($digits, $conditions['code_prefix'])));
    }
}
