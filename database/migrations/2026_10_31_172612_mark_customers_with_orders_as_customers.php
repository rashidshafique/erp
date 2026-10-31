<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** A customer with a sales order is no longer a prospect; the flag was only ever cleared by hand. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')->where('is_prospect', true)->whereIn('id', DB::table('sales_orders')->select('customer_id'))->update(['is_prospect' => false]);
    }

    public function down(): void {}
};
