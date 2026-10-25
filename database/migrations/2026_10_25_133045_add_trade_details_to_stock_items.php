<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What tax and financial reporting need to know of every item kept in stock or sold: its inventory class (raw material, finished
 * goods, stock-in-trade, consumable, stores and spares), its customs tariff code (the PCT code in Pakistan), the unit tax documents
 * count it in, and its country of origin. A stock movement records the class it was posted under, so an item bought both to use
 * and to resell reports each in the right line.
 */
return new class extends Migration
{
    private const CLASSES = "'RAW_MATERIAL','FINISHED_GOODS','STOCK_IN_TRADE','CONSUMABLE','STORES_SPARES'";

    public function up(): void
    {
        foreach (['materials' => 'RAW_MATERIAL', 'products' => 'FINISHED_GOODS'] as $table => $default) {
            Schema::table($table, function (Blueprint $t) use ($default) {
                $t->string('inventory_class', 20)->default($default);
                $t->string('tariff_code', 20)->nullable();
                $t->string('tax_uom', 40)->nullable();
                $t->char('origin_country', 2)->nullable();
            });
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_inventory_class_valid CHECK (inventory_class IN (".self::CLASSES.'))');
        }
        DB::table('materials')->whereIn('material_type', ['FUEL', 'OTHER'])->update(['inventory_class' => 'CONSUMABLE']);

        Schema::table('stock_movements', function (Blueprint $t) {
            $t->string('inventory_class', 20)->nullable();
        });
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_inventory_class_valid CHECK (inventory_class IS NULL OR inventory_class IN (".self::CLASSES.'))');
        DB::statement("UPDATE stock_movements m SET inventory_class = x.inventory_class FROM materials x WHERE m.item_type = 'material' AND x.id = m.item_id");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_inventory_class_valid');
        Schema::table('stock_movements', fn (Blueprint $t) => $t->dropColumn('inventory_class'));
        foreach (['materials', 'products'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_inventory_class_valid");
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['inventory_class', 'tariff_code', 'tax_uom', 'origin_country']));
        }
    }
};
