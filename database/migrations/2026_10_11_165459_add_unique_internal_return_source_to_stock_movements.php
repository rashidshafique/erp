<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX stock_movements_internal_return_source_unique
            ON stock_movements (source_id)
            WHERE source_type = 'HEAT_RETURN'
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS stock_movements_internal_return_source_unique
        ");
    }
};