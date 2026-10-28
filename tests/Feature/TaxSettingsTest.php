<?php

namespace Tests\Feature;

use App\Domains\Tax\Models\PartyTaxProfile;
use App\Domains\Tax\Models\TaxAuthority;
use App\Domains\Tax\Models\TaxComponent;
use App\Domains\Tax\Models\TaxRule;
use App\Domains\Tax\Services\TaxSetup;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\TaxJurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaxSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
    }

    public function test_pakistans_starting_set_loads_goods_federally_and_services_by_province_and_is_not_replaced_unasked(): void
    {
        $this->get(route('finance.tax-year'))->assertOk()->assertSee('Load Pakistan defaults')->assertSee('STRN (FBR sales tax)');

        $this->post(route('tax.defaults'))->assertSessionHasNoErrors();
        $this->assertSame(['BRA', 'FBR', 'KPRA', 'PRA', 'SRB'], TaxAuthority::orderBy('code')->pluck('code')->all());
        $this->assertSame('FBR', TaxComponent::where('code', 'ST_GOODS')->sole()->authority->code);
        $this->assertSame('SRB', TaxComponent::where('code', 'ST_SERVICES_SD')->sole()->authority->code);
        $this->assertSame('PRA', TaxComponent::where('code', 'ST_SERVICES_PB')->sole()->authority->code);
        $this->assertSame(['ST_SERVICES_SD'], TaxRule::where('name', 'Services supplied in Sindh')->sole()->components);

        TaxRule::query()->update(['name' => 'Edited']);
        $this->post(route('tax.defaults'))->assertSessionHasErrors('defaults');
        $this->assertSame(0, TaxRule::where('name', '!=', 'Edited')->count());
        $this->post(route('tax.defaults'), ['replace' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, TaxRule::where('name', 'Edited')->count());

        $this->get(route('finance.tax-year'))->assertOk()->assertSee('Sindh sales tax on services')->assertSee('Services supplied in Punjab');
    }

    public function test_a_new_rate_starts_from_its_date_and_the_old_one_stays_for_earlier_documents(): void
    {
        app(TaxSetup::class)->loadDefaults(TaxJurisdiction::pack());
        $goods = TaxComponent::where('code', 'ST_GOODS')->sole();

        $this->post(route('tax.components.rate', $goods), ['rate' => 19, 'effective_from' => '2025-07-01'])->assertSessionHasNoErrors();

        $rateOn = fn (string $day) => (float) TaxComponent::where('code', 'ST_GOODS')->on(Carbon::parse($day))->sole()->rate;
        $this->assertSame([18.0, 19.0], [$rateOn('2025-06-30'), $rateOn('2025-07-01')]);
        $this->post(route('tax.components.rate', $goods), ['rate' => 17, 'effective_from' => '2025-01-01'])->assertSessionHasErrors('rate');
    }

    public function test_the_company_registration_and_registered_authorities_are_saved(): void
    {
        app(TaxSetup::class)->loadDefaults(TaxJurisdiction::pack());
        $srb = TaxAuthority::where('code', 'SRB')->sole();

        $this->put(route('tax.company.update'), ['jurisdiction' => 'PK', 'region' => 'SD', 'registrations' => ['NTN' => '1234567-8', 'STRN' => ''], 'registered' => [$srb->id]])->assertSessionHasNoErrors();

        $this->assertSame(['region' => 'SD', 'registrations' => ['NTN' => '1234567-8'], 'withholding_agent' => false], TaxSetup::company());
        $this->assertSame(['SRB'], TaxAuthority::where('is_registered', true)->pluck('code')->all());
    }

    public function test_a_rule_is_added_with_its_conditions(): void
    {
        app(TaxSetup::class)->loadDefaults(TaxJurisdiction::pack());

        $this->post(route('tax.rules.store'), ['name' => 'Zero-rated castings', 'priority' => 5, 'side' => 'SALE', 'supply_type' => 'GOODS', 'code_prefix' => '7325.10',
            'withholding_agent' => '', 'components' => ['ST_GOODS'], 'is_active' => 1])->assertSessionHasNoErrors();

        $this->assertEquals(['supply_type' => 'GOODS', 'code_prefix' => '732510'], TaxRule::where('name', 'Zero-rated castings')->sole()->conditions);
        $this->post(route('tax.rules.store'), ['name' => 'No taxes', 'priority' => 5, 'side' => 'SALE'])->assertSessionHasErrors('components');
    }

    public function test_a_partners_tax_details_are_shown_and_saved_from_its_page(): void
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);

        $this->get(route('parties.show', $customer->party_id))->assertOk()->assertSee('Edit tax details')->assertSee('Withholding agent');
        $this->put(route('tax.parties.update', $customer->party_id), ['tax_status' => 'REGISTERED', 'region' => 'PB', 'is_withholding_agent' => 1,
            'registrations' => ['NTN' => ' 7654321-0 ', 'STRN' => '', 'BOGUS' => 'x']])->assertSessionHasNoErrors();

        $profile = PartyTaxProfile::where('party_id', $customer->party_id)->sole();
        $this->assertSame(['REGISTERED', 'PB', true, ['NTN' => '7654321-0']], [$profile->tax_status, $profile->region, $profile->is_withholding_agent, $profile->registrations]);
        $this->get(route('parties.show', $customer->party_id))->assertSee('Registered for sales tax')->assertSee('7654321-0');
    }

    public function test_changing_tax_details_needs_the_tax_permission(): void
    {
        config(['foundry.require_login' => true]);
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $this->actingAs(User::factory()->create(['is_active' => true, 'must_change_password' => false]));

        $this->put(route('tax.parties.update', $customer->party_id), ['tax_status' => 'REGISTERED'])->assertForbidden();
        $this->post(route('tax.defaults'))->assertForbidden();
        $this->get(route('parties.show', $customer->party_id))->assertOk()->assertDontSee('Edit tax details');
    }
}
