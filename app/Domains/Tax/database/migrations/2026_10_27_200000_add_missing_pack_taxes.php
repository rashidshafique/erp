<?php

use App\Domains\Tax\Services\TaxSetup;
use App\Support\TaxJurisdiction;
use Illuminate\Database\Migrations\Migration;

/** Adds the purchase-side withholding taxes and rules to a country loaded before they were in its pack, leaving edited rows alone. */
return new class extends Migration
{
    public function up(): void
    {
        if ($pack = TaxJurisdiction::pack()) {
            app(TaxSetup::class)->addMissing($pack);
        }
    }

    public function down(): void {}
};
