<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirement_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('region', 12)->default('GLOBAL');
            $table->text('description')->nullable();
            $table->jsonb('settings')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('customer_requirement_profiles', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('requirement_profile_id')->constrained('requirement_profiles')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['customer_id', 'requirement_profile_id']);
        });

        $automotive = ['material_certificate' => 'EN10204_31', 'delivery_certificate' => 'EN10204_31', 'ppap_level' => 3, 'ppk_initial' => 1.67, 'cpk_ongoing' => 1.33, 'fmea_format' => 'AIAG_VDA', 'control_plan' => true, 'msa_required' => true];
        $note = ' Starting values: confirm them against the customer\'s current supplier manual and edit as needed.';
        $profiles = [
            ['ISO9001', 'ISO 9001 base', 'GLOBAL', 'No customer-specific requirements beyond ISO 9001.', []],
            ['IATF16949', 'IATF 16949', 'GLOBAL', 'The automotive quality standard: control plan, FMEA, MSA, SPC targets and PPAP.', $automotive + ['lpa_required' => true, 'report_8d' => true]],
            ['JP_OEM', 'Japanese OEM supplier', 'ASIA', 'Japanese vehicle makers and their tier-1 suppliers, such as Toyota and Honda programmes. A3 problem reports and 5S audits are common.'.$note, $automotive + ['report_a3' => true, 'report_8d' => true, 'five_s_audit' => true, 'imds_required' => true]],
            ['KR_OEM', 'Korean OEM supplier', 'ASIA', 'Korean vehicle makers and their suppliers. Usually IATF 16949 plus their own supplier portal checks.'.$note, $automotive + ['report_8d' => true, 'lpa_required' => true, 'imds_required' => true]],
            ['CN_OEM', 'Chinese OEM supplier', 'ASIA', 'Chinese vehicle makers and their suppliers. Usually IATF 16949 plus customer-specific requirements.'.$note, $automotive + ['report_8d' => true, 'imds_required' => true]],
            ['IN_OEM', 'Indian OEM supplier', 'ASIA', 'Indian vehicle makers and their suppliers, based on IATF 16949 with their own supplier quality checks.'.$note, $automotive + ['report_8d' => true, 'lpa_required' => true]],
        ];
        foreach ($profiles as [$code, $name, $region, $description, $settings]) {
            DB::table('requirement_profiles')->insert([
                'id' => (string) Str::uuid(), 'code' => $code, 'name' => $name, 'region' => $region, 'description' => $description,
                'settings' => json_encode($settings), 'is_system' => true, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_requirement_profiles');
        Schema::dropIfExists('requirement_profiles');
    }
};
