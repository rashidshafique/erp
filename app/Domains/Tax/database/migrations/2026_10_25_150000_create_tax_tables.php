<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Taxation module's data: the authorities that collect tax in a country, the taxes they charge or have withheld (each rate
 * dated, so a budget change never alters what was already invoiced), the rules that pick taxes for a line, and each customer's
 * or supplier's tax status and registration numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_authorities', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->char('jurisdiction', 2);
            $t->string('code', 20);
            $t->string('name', 150);
            $t->string('level', 12);
            $t->string('region', 10)->nullable();
            $t->string('registration_label', 40)->nullable();
            $t->boolean('is_registered')->default(false);
            $t->timestamps();
            $t->unique(['jurisdiction', 'code']);
        });
        DB::statement("ALTER TABLE tax_authorities ADD CONSTRAINT tax_authorities_level_valid CHECK (level IN ('FEDERAL','PROVINCIAL'))");

        Schema::create('tax_components', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->char('jurisdiction', 2);
            $t->foreignUuid('tax_authority_id')->constrained('tax_authorities')->restrictOnDelete();
            $t->string('code', 30);
            $t->string('name', 150);
            $t->string('kind', 10);
            $t->decimal('rate', 7, 3);
            $t->string('base', 30)->default('NET');
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->string('note', 300)->nullable();
            $t->timestamps();
            $t->unique(['jurisdiction', 'code', 'effective_from']);
        });
        DB::statement("ALTER TABLE tax_components ADD CONSTRAINT tax_components_kind_valid CHECK (kind IN ('CHARGED','WITHHELD'))");
        DB::statement('ALTER TABLE tax_components ADD CONSTRAINT tax_components_dates_valid CHECK (effective_to IS NULL OR effective_to >= effective_from)');

        Schema::create('tax_rules', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->char('jurisdiction', 2);
            $t->string('name', 150);
            $t->unsignedInteger('priority')->default(100);
            $t->string('side', 10)->default('BOTH');
            $t->jsonb('conditions')->default('{}');
            $t->jsonb('components')->default('[]');
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['jurisdiction', 'priority']);
        });
        DB::statement("ALTER TABLE tax_rules ADD CONSTRAINT tax_rules_side_valid CHECK (side IN ('SALE','PURCHASE','BOTH'))");

        Schema::create('party_tax_profiles', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('party_id')->unique()->constrained('parties')->cascadeOnDelete();
            $t->string('tax_status', 30)->nullable();
            $t->string('region', 10)->nullable();
            $t->boolean('is_withholding_agent')->default(false);
            $t->jsonb('registrations')->default('{}');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_tax_profiles');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('tax_components');
        Schema::dropIfExists('tax_authorities');
    }
};
