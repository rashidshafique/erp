<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patterns', function (Blueprint $t) {
            $t->string('sand_system', 100)->nullable();   // the sand system the pattern was made for (its taper and allowances suit this sand)
            $t->jsonb('compatible_sands')->nullable();    // other sand systems it is known to work on
            $t->text('sand_note')->nullable();            // e.g. the taper allowance, or why it does not suit another sand
        });
        Schema::table('moulding_work_list_items', function (Blueprint $t) {
            $t->boolean('sand_override')->default(false); // the plate is not suited to this sand and someone chose to run it anyway
        });
    }

    public function down(): void
    {
        Schema::table('moulding_work_list_items', fn (Blueprint $t) => $t->dropColumn('sand_override'));
        Schema::table('patterns', fn (Blueprint $t) => $t->dropColumn(['sand_system', 'compatible_sands', 'sand_note']));
    }
};
