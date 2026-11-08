<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/** Everything that carries a scannable code: where it lives, what it is called, which page shows it. */
class CodedRecords
{
    /** key => [table, code column, label, show route (null = tool card), name column] */
    public const TYPES = [
        'product' => ['products', 'code', 'Product', 'products.show', 'name'],
        'pattern' => ['patterns', 'code', 'Pattern', 'patterns.show', 'name'],
        'core_box' => ['core_boxes', 'code', 'Core box', 'core-boxes.show', 'name'],
        'core_definition' => ['core_definitions', 'code', 'Core', 'cores.show', 'name'],
        'mould_configuration' => ['mould_configurations', 'code', 'Mould configuration', null, 'name'],
        'flask' => ['flasks', 'code', 'Flask', 'flasks.show', null],
        'casting_lot' => ['casting_lots', 'lot_number', 'Casting lot', 'lots.show', null],
        'heat' => ['heats', 'heat_number', 'Heat', 'heats.show', null],
        'pour' => ['pours', 'pour_number', 'Pour', 'pours.show', null],
        'production_order' => ['production_orders', 'production_order_number', 'Production order', null, null],
        'sales_order' => ['sales_orders', 'order_number', 'Sales order', 'sales-orders.show', null],
    ];

    public const TOOLS = ['pattern', 'core_box', 'core_definition', 'mould_configuration', 'flask'];

    public static function url(string $type, string $id): string
    {
        $route = self::TYPES[$type][3] ?? null;
        if ($type === 'production_order') {
            return route('production-orders.show', $id);
        }

        return $route && Route::has($route) ? route($route, $id) : (Route::has('tools.show') ? route('tools.show', [$type, $id]) : route('dashboard'));
    }

    /** Find any record by a scanned or typed code, including engraved marks. @return array{0:string,1:string}|null [type, id] */
    public static function find(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        foreach (self::TYPES as $type => [$table, $col]) {
            $id = \DB::table($table)->whereRaw("upper($col) = ?", [strtoupper($code)])->when(\Schema::hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))->value('id');
            if ($id) {
                return [$type, (string) $id];
            }
        }
        $mark = \DB::table('tool_marks')->whereRaw('upper(mark_code) = ?', [strtoupper($code)])->first();
        if ($mark) {
            return [$mark->tool_type, (string) $mark->tool_id];
        }
        // A label or stamp printed before renumbering carries the old number.
        $tables = array_flip(array_map(fn ($t) => $t[0], self::TYPES));
        foreach (\DB::table('number_history')->whereRaw('upper(old_number) = ?', [strtoupper($code)])->orderByDesc('id')->get(['table_name', 'record_id']) as $old) {
            if (isset($tables[$old->table_name])) {
                return [$tables[$old->table_name], (string) $old->record_id];
            }
        }

        return null;
    }
}
