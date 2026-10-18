<?php

namespace App\Support;

/**
 * The trail in the top bar. Pages give it as plain text ("Patterns / SP-001") or with their own links; the first word of a plain
 * trail links to its list, and the nearest link is where the Back button goes when there is no page to return to.
 */
class Breadcrumb
{
    /** First word of a trail => the route of its list. */
    public const ROOTS = [
        'Customers' => 'customers.index',
        'Patterns' => 'patterns.index',
        'Moulding runs' => 'moulding-runs.index',
        'Thermal tests' => 'thermal.index',
        'Flasks' => 'flasks.index',
        'Gauges' => 'gauges.index',
        'Corrective actions' => 'capa.index',
        'Furnaces' => 'furnaces.index',
        'Iron grades' => 'iron-grades.index',
        'Sales orders' => 'sales-orders.index',
        'Settings' => 'settings.edit',
    ];

    /**
     * @return array{html: string, parent: ?string} the trail with its first word linked, and the link Back falls back to
     */
    public static function build(string $trail): array
    {
        $trail = trim($trail);
        if ($trail !== '' && ! str_contains($trail, '<a ')) {
            $parts = explode(' / ', $trail, 2);
            $route = self::ROOTS[html_entity_decode($parts[0])] ?? null;
            if ($route && count($parts) === 2) {
                $trail = '<a href="'.e(route($route)).'">'.$parts[0].'</a> / '.$parts[1];
            }
        }
        $parent = preg_match_all('/<a [^>]*href="([^"]+)"/', $trail, $links) ? html_entity_decode(end($links[1])) : null;

        return ['html' => $trail, 'parent' => $parent];
    }
}
