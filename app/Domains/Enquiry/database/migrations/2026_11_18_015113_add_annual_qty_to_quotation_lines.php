<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A revision can change the annual quantity (design 30c), so each quotation line keeps its own instead of reading the enquiry item's,
     * which every revision shares. Existing lines take the item's figure.
     */
    public function up(): void
    {
        Schema::table('quotation_lines', function (Blueprint $t) {
            $t->unsignedInteger('annual_qty')->nullable();
        });
        DB::statement('UPDATE quotation_lines SET annual_qty = i.annual_qty FROM enquiry_items i WHERE i.id = quotation_lines.enquiry_item_id AND i.annual_qty IS NOT NULL AND i.annual_qty > 0');
    }

    public function down(): void
    {
        Schema::table('quotation_lines', function (Blueprint $t) {
            $t->dropColumn('annual_qty');
        });
    }
};
