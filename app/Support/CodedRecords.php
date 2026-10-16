<?php

namespace App\Support;

/** Everything that carries a scannable code: where it lives, what it is called, which page shows it. */
class CodedRecords
{
    /** key => [table, code column, label, show route (null = tool card), name column] */
    public const TYPES = [
        'product' => ['products', 'code', 'Product', 'products.show', 'name'],
        'pattern' => ['patterns', 'code', 'Pattern', 'patterns.show', 'name'],
        'core_box' => ['core_boxes', 'code', 'Core box', null, 'name'],
        'core_definition' => ['core_definitions', 'code', 'Core', null, 'name'],
        'mould_configuration' => ['mould_configurations', 'code', 'Mould configuration', null, 'name'],
        'casting_lot' => ['casting_lots', 'lot_number', 'Casting lot', 'lots.show', null],
        'heat' => ['heats', 'heat_number', 'Heat', 'heats.show', null],
        'pour' => ['pours', 'pour_number', 'Pour', 'pours.show', null],
        'production_order' => ['production_orders', 'production_order_number', 'Production order', null, null],
        'sales_order' => ['sales_orders', 'order_number', 'Sales order', 'sales-orders.show', null],
    ];

    public const TOOLS = ['pattern', 'core_box', 'core_definition', 'mould_configuration'];

    public static function url(string $type, string $id): string
    {
        $route = self::TYPES[$type][3] ?? null;
        if ($type === 'production_order') {
            return route('production-orders.index', ['search' => \DB::table('production_orders')->where('id', $id)->value('production_order_number')]);
        }

        return $route ? route($route, $id) : route('tools.show', [$type, $id]);
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

        return $mark ? [$mark->tool_type, (string) $mark->tool_id] : null;
    }
}
