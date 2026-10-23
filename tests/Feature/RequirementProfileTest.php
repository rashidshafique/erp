<?php

namespace Tests\Feature;

use App\Domains\Compliance\Models\RequirementProfile;
use App\Domains\Compliance\Services\RequirementResolver;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementProfileTest extends TestCase
{
    use RefreshDatabase;

    private function profile(string $code): RequirementProfile
    {
        return RequirementProfile::where('code', $code)->firstOrFail();
    }

    public function test_asian_oem_profiles_are_seeded(): void
    {
        $this->assertEqualsCanonicalizing(['ISO9001', 'IATF16949', 'JP_OEM', 'KR_OEM', 'CN_OEM', 'IN_OEM', 'GCC_OILGAS', 'GCC_UTILITY', 'GCC_IMPORT', 'NA_AUTO', 'MENA_INDUSTRIAL'], RequirementProfile::pluck('code')->all());
        $this->assertTrue($this->profile('JP_OEM')->value('report_a3'));
        $this->assertSame('ASIA', $this->profile('JP_OEM')->region);
    }

    public function test_mena_profiles_are_offered_under_their_own_region_and_add_the_gulf_paperwork(): void
    {
        $customer = Customer::create(['code' => 'C1', 'name' => 'Gulf Valves']);

        $this->get(route('customer-requirements.edit', $customer))->assertOk()
            ->assertSeeInOrder(['Global', 'ISO 9001 base', 'Asia', 'Japanese OEM supplier', 'Middle East &amp; North Africa', 'GCC import paperwork', 'Gulf oil &amp; gas operator'], false)
            ->assertSee('Third-party inspection before dispatch');

        $this->put(route('customer-requirements.update', $customer), ['profiles' => [$this->profile('GCC_OILGAS')->id, $this->profile('GCC_IMPORT')->id]])->assertSessionHasNoErrors();
        $merged = app(RequirementResolver::class)->forCustomer($customer);

        $this->assertSame(['EN10204_32', true, true, true], [$merged['delivery_certificate'], $merged['third_party_inspection'], $merged['conformity_certificate'], $merged['origin_certificate']]);
    }

    public function test_a_customer_with_no_profile_has_no_extra_requirements(): void
    {
        $merged = app(RequirementResolver::class)->forCustomer(Customer::create(['code' => 'C1', 'name' => 'Plain Co']));

        $this->assertSame([0, false, 'NONE'], [$merged['ppap_level'], $merged['control_plan'], $merged['delivery_certificate']]);
    }

    public function test_the_strictest_value_across_profiles_applies(): void
    {
        $customer = Customer::create(['code' => 'C1', 'name' => 'Toyota Tier 1']);
        $custom = RequirementProfile::create(['code' => 'STRICT', 'name' => 'Strict', 'settings' => ['ppap_level' => 5, 'delivery_certificate' => 'EN10204_32', 'report_a3' => true]]);
        $this->put(route('customer-requirements.update', $customer), ['profiles' => [$this->profile('IATF16949')->id, $custom->id]])->assertSessionHasNoErrors();

        $merged = app(RequirementResolver::class)->forCustomer($customer);

        $this->assertSame([5, 'EN10204_32', true, true, 1.33], [$merged['ppap_level'], $merged['delivery_certificate'], $merged['report_a3'], $merged['report_8d'], $merged['cpk_ongoing']]);
    }

    public function test_a_profile_can_be_added_and_a_system_profile_edited_but_keeps_its_code(): void
    {
        $settings = fn (array $over = []) => ['settings' => $over + ['material_certificate' => 'NONE', 'delivery_certificate' => 'EN10204_31', 'ppap_level' => 2, 'ppk_initial' => 1.67, 'cpk_ongoing' => 1.33, 'fmea_format' => 'NONE', 'control_plan' => 0, 'msa_required' => 0, 'lpa_required' => 0, 'report_8d' => 0, 'report_a3' => 1, 'five_s_audit' => 0, 'imds_required' => 0]];

        $this->post(route('requirements.store'), ['name' => 'Thai OEM', 'code' => 'th_oem', 'region' => 'ASIA'] + $settings())->assertSessionHasNoErrors();
        $this->assertSame(2, RequirementProfile::where('code', 'TH_OEM')->firstOrFail()->value('ppap_level'));

        $jp = $this->profile('JP_OEM');
        $this->put(route('requirements.update', $jp), ['name' => 'Japanese OEM', 'code' => 'HACK', 'region' => 'ASIA', 'active' => 1] + $settings(['ppap_level' => 4]))->assertSessionHasNoErrors();
        $jp->refresh();
        $this->assertSame(['JP_OEM', 4], [$jp->code, $jp->value('ppap_level')]);

        $this->post(route('requirements.store'), ['name' => 'Bad', 'code' => 'bad', 'region' => 'ASIA'] + $settings(['ppap_level' => 9]))->assertSessionHasErrors('settings.ppap_level');
    }
}
