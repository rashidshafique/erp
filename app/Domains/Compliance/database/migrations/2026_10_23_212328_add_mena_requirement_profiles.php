<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Starting requirement profiles for Middle East and North African customers: Gulf oil and gas, utilities, import paperwork and automotive. */
return new class extends Migration
{
    private const CODES = ['GCC_OILGAS', 'GCC_UTILITY', 'GCC_IMPORT', 'NA_AUTO', 'MENA_INDUSTRIAL'];

    public function up(): void
    {
        $note = ' Starting values: confirm them against the customer\'s current specification and edit as needed.';
        $profiles = [
            ['GCC_OILGAS', 'Gulf oil & gas operator', 'Saudi Aramco, ADNOC, KOC, QatarEnergy, PDO and their EPC contractors: pressure-containing castings for valves and pumps. Approved-vendor status, an inspection and test plan, and third-party witnessed tests are the norm.'.$note,
                ['material_certificate' => 'EN10204_31', 'delivery_certificate' => 'EN10204_32', 'control_plan' => true, 'msa_required' => true, 'report_8d' => true, 'third_party_inspection' => true, 'origin_certificate' => true]],
            ['GCC_UTILITY', 'GCC water & municipal utility', 'Water, power and municipal authorities such as NWC, DEWA, TAQA, Kahramaa and their contractors: ductile iron fittings, valves and manhole covers to EN 545, ISO 2531 or EN 124, often with third-party product certification.'.$note,
                ['material_certificate' => 'EN10204_22', 'delivery_certificate' => 'EN10204_31', 'third_party_inspection' => true, 'origin_certificate' => true]],
            ['GCC_IMPORT', 'GCC import paperwork', 'Shipping documents for Saudi Arabia, the UAE, Kuwait, Qatar, Oman and Bahrain: a shipment conformity certificate where the product is regulated (SABER, ECAS, KUCAS) and an attested certificate of origin. Add it alongside the customer\'s quality profile.'.$note,
                ['delivery_certificate' => 'ATTACHED', 'conformity_certificate' => true, 'origin_certificate' => true]],
            ['NA_AUTO', 'North African automotive', 'Vehicle plants and tier-1 suppliers in Morocco, Tunisia and Egypt, such as Renault and Stellantis programmes. IATF 16949 plus the carmaker\'s own requirements; Renault uses ANPQP in place of PPAP.'.$note,
                ['material_certificate' => 'EN10204_31', 'delivery_certificate' => 'EN10204_31', 'ppap_level' => 3, 'ppk_initial' => 1.67, 'cpk_ongoing' => 1.33, 'fmea_format' => 'AIAG_VDA', 'control_plan' => true, 'msa_required' => true, 'report_8d' => true, 'imds_required' => true]],
            ['MENA_INDUSTRIAL', 'MENA general industrial', 'Pump, valve, construction and machinery buyers across the region with no customer manual of their own: a mill certificate with each delivery and an attested certificate of origin.'.$note,
                ['delivery_certificate' => 'EN10204_31', 'origin_certificate' => true]],
        ];

        foreach ($profiles as [$code, $name, $description, $settings]) {
            if (DB::table('requirement_profiles')->where('code', $code)->exists()) {
                continue;
            }
            DB::table('requirement_profiles')->insert([
                'id' => (string) Str::uuid(), 'code' => $code, 'name' => $name, 'region' => 'MENA', 'description' => $description,
                'settings' => json_encode($settings), 'is_system' => true, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('requirement_profiles')->whereIn('code', self::CODES)->delete();
    }
};
