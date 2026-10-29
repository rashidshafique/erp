<?php

namespace App\Support;

/**
 * Puts the sidebar's links in the order of the workflow design (1a): Sales, Engineering, Production, Quality, Logistics, each
 * with its design entries first. Links the design has no place for stay in their old groups, which follow. The links are the
 * ones the menu already drew, so permissions, menu filters, counts and the current page all carry over unchanged.
 */
class MenuLayout
{
    /**
     * Group label, icon, then its links as [path, label shown or null to keep, icon for a link made here]. A path with a query
     * that no drawn link has is a view of another link's page (for example quotes are enquiries filtered by status).
     *
     * @var list<array{0: string, 1: string, 2: list<array{0: string, 1: string|null, 2?: string}>}>
     */
    public const GROUPS = [
        ['Sales', 'briefcase', [
            ['/', 'Overview'], ['/enquiries', 'Enquiries'], ['/sales-orders', null],
            ['/customers', null], ['/products', null], ['/price-lists', null], ['/partners', null],
        ]],
        ['Engineering', 'layers', [
            ['/feasibility', 'Feasibility', 'clipboard-check'], ['/patterns', 'Patterns & tooling'], ['/cores', null], ['/core-lots', null], ['/casting-analyses', null],
        ]],
        ['Production', 'factory', [
            ['/planner', 'Planning'], ['/heats', 'Melt & heats'], ['/calendar', null], ['/production-orders', null], ['/lots/queue', null],
            ['/moulding-runs', null], ['/pours', null], ['/work-lists', null], ['/moulding-teams', null], ['/sand-systems', null],
            ['/mould-readiness', null], ['/mould-count', null], ['/furnaces', null], ['/heat-plan', null],
        ]],
        ['Quality', 'check-circle', [
            ['/inspections', 'Inspection'], ['/ncrs', 'NCR & CAPA'], ['/documents', 'Audits & documents'], ['/quick-qc', null],
            ['/lots', null], ['/reports/yield', null], ['/defects', null], ['/corrective-actions', null],
        ]],
        ['Logistics', 'truck', [
            ['/dispatches', 'Dispatch'], ['/packing', null], ['/finished-goods', null],
        ]],
    ];

    /** Rearranges the menu's inner HTML (its top links and groups) into the design's groups. */
    public static function arrange(string $html): string
    {
        $chunks = preg_split('/(?=<div class="nav-group)/', $html);
        $links = [];   // key => ['html' => anchor markup, 'label' => text]
        foreach ($chunks as $i => $chunk) {
            $chunks[$i] = preg_replace_callback('/<a href="([^"]*)" class="nav-link[^"]*">.*?<\/a>/s', function (array $m) use (&$links): string {
                $key = self::key($m[1]);
                if (isset($links[$key]) || ! str_contains($m[0], 'nav-link')) {
                    return $m[0];
                }
                $links[$key] = $m[0];

                return "\0".$key."\0";
            }, $chunk);
        }
        if ($links === []) {
            return $html;
        }

        $all = $links;
        $groups = '';
        foreach (self::GROUPS as [$label, $icon, $items]) {
            $drawn = '';
            $active = false;
            $count = 0;
            foreach ($items as $item) {
                $link = self::take($links, $all, $item);
                if ($link === null) {
                    continue;
                }
                $active = $active || str_contains($link, 'nav-link active');
                $count += self::count($link);
                $drawn .= $link;
            }
            if ($drawn !== '') {
                $groups .= self::group($label, $icon, $drawn, $active, $count);
            }
        }

        $rest = '';
        foreach ($chunks as $i => $chunk) {
            $chunk = preg_replace_callback('/\0([^\0]*)\0/', fn (array $m) => $links[$m[1]] ?? '', $chunk);
            if ($i > 0 && ! str_contains($chunk, 'class="nav-link')) {
                continue;
            }
            $rest .= $chunk;
        }

        return $groups.$rest;
    }

    private static function key(string $href): string
    {
        $path = (string) parse_url(html_entity_decode($href), PHP_URL_PATH);
        $query = parse_url(html_entity_decode($href), PHP_URL_QUERY);

        return ($path === '' ? '/' : $path).($query ? '?'.$query : '');
    }

    /**
     * The anchor for one entry of a design group, taken out of the links still waiting: renamed when the design names it
     * differently, or made here when it is a filtered view of a link that was drawn (shown only if that link was).
     *
     * @param  array<string, string>  $links  the anchors not yet placed
     * @param  array<string, string>  $all  every anchor the menu drew
     * @param  array{0: string, 1: string|null, 2?: string}  $item
     */
    private static function take(array &$links, array $all, array $item): ?string
    {
        [$key, $label] = $item;
        if (isset($item[2])) {
            if (! isset($all[strtok($key, '?')])) {
                return null;
            }

            return '<a href="'.e(url($key)).'" class="nav-link">'.NavIcons::get($item[2]).'<span>'.e($label).'</span></a>';
        }
        $anchor = $links[$key] ?? null;
        if ($anchor === null) {
            return null;
        }
        unset($links[$key]);
        $anchor = preg_replace('/ nav-top/', '', $anchor, 1);

        return $label === null ? $anchor : preg_replace('/(<span>)[^<]*(<\/span>)/', '$1'.e($label).'$2', $anchor, 1);
    }

    private static function count(string $anchor): int
    {
        return preg_match('/class="nav-count"[^>]*>(\d+)</', $anchor, $m) ? (int) $m[1] : 0;
    }

    private static function group(string $label, string $icon, string $links, bool $active, int $count): string
    {
        $badge = $count > 0 ? '<span class="nav-count" title="Waiting for someone">'.$count.'</span>' : '';

        return '<div class="nav-group '.($active ? 'is-active' : '').'"><button type="button" class="nav-group-toggle" aria-expanded="false"><span class="nav-lab">'
            .NavIcons::get($icon).'<span>'.e($label).'</span>'.$badge.'</span><span class="nav-chevron">›</span></button><div class="nav-submenu" hidden>'.$links.'</div></div>';
    }
}
