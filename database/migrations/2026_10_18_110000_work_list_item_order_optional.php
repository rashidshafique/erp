<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A list item no longer needs an order when it is planned: it is linked to an open order automatically, or to a stock order when the run starts.
        DB::statement('ALTER TABLE moulding_work_list_items ALTER COLUMN production_order_id DROP NOT NULL');
        Schema::table('moulding_work_list_items', function (Blueprint $t) {
            $t->string('order_link', 10)->default('MANUAL');   // MANUAL (chosen), AUTO (found by the system), STOCK (stock order raised), NONE (not linked yet)
        });
        DB::statement("ALTER TABLE moulding_work_list_items ADD CONSTRAINT moulding_work_list_items_link_chk CHECK (order_link IN ('MANUAL','AUTO','STOCK','NONE'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE moulding_work_list_items DROP CONSTRAINT IF EXISTS moulding_work_list_items_link_chk');
        Schema::table('moulding_work_list_items', fn (Blueprint $t) => $t->dropColumn('order_link'));
        DB::statement('DELETE FROM moulding_work_list_items WHERE production_order_id IS NULL');
        DB::statement('ALTER TABLE moulding_work_list_items ALTER COLUMN production_order_id SET NOT NULL');
    }
};
