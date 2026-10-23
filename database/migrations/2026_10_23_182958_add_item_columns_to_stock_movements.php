<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A movement names the item it moves (`item_type` + `item_id`), so stock of something other than a material can share the
     * ledger. Materials stay the only lot-bearing type: their movements keep `material_id` and `material_lot_id`, and a check keeps
     * those in step with the item columns.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_movements ADD COLUMN item_type varchar(40) NOT NULL DEFAULT 'material'");
        DB::statement('ALTER TABLE stock_movements ADD COLUMN item_id uuid NULL');
        DB::statement('UPDATE stock_movements SET item_id = material_id');
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN item_id SET NOT NULL');
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN material_id DROP NOT NULL');
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN material_lot_id DROP NOT NULL');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_material_item_check CHECK (item_type <> 'material' OR (material_id IS NOT NULL AND material_lot_id IS NOT NULL AND item_id = material_id))");
        DB::statement('CREATE INDEX stock_movements_item_occurred_index ON stock_movements (item_type, item_id, occurred_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS stock_movements_item_occurred_index');
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_material_item_check');
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN material_lot_id SET NOT NULL');
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN material_id SET NOT NULL');
        DB::statement('ALTER TABLE stock_movements DROP COLUMN item_id');
        DB::statement('ALTER TABLE stock_movements DROP COLUMN item_type');
    }
};
