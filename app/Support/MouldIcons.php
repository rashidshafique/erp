<?php

namespace App\Support;

use App\Models\Pattern;
use App\Models\PatternRevision;

/**
 * Small pictograms (24px grid, stroke style) for the two lists that are easy to mix up: the pattern type, which is the tooling,
 * and the mould arrangement, which is how the mould is built. Inline, like NavIcons, so they take the text colour.
 */
class MouldIcons
{
    /** The tooling: plates and patterns drawn as the pieces they are. */
    private const TYPES = [
        'MATCHPLATE' => '<rect x="3" y="11" width="18" height="2.4" rx=".6"/><rect x="8.5" y="5.5" width="7" height="5.5" rx="1"/><rect x="8.5" y="13.4" width="7" height="5.5" rx="1"/>',
        'COPE_DRAG' => '<rect x="3" y="3.5" width="18" height="2.6" rx=".6"/><rect x="8.5" y="6.1" width="7" height="4" rx="1"/><rect x="3" y="17.9" width="18" height="2.6" rx=".6"/><rect x="8.5" y="13.9" width="7" height="4" rx="1"/>',
        'STACK_PLATE' => '<path d="M4 5h16M4 10h16M4 15h16M4 20h16"/><path d="M12 2.5v19"/>',
        'VERTICAL_PLATES' => '<rect x="11" y="3" width="2.4" height="18" rx=".6"/><rect x="13.4" y="8" width="5.6" height="8" rx="1"/><rect x="5" y="8" width="6" height="8" rx="1"/>',
        'LOOSE_PATTERN' => '<path d="M4.5 18c0-6 3-10 7.5-10s7.5 4 7.5 10z"/><path d="M3 20.5h18"/>',
        'SPLIT_PATTERN' => '<path d="M4 18c0-6 3-10 7.2-10v10z"/><path d="M12.8 8c4.2 0 7.2 4 7.2 10h-7.2z"/><path d="M3 20.5h18"/>',
        'GATED' => '<circle cx="6" cy="14" r="3"/><circle cx="18" cy="14" r="3"/><path d="M9 14h6M12 14V4M9.5 4h5"/>',
        'SHELL_PLATE' => '<rect x="3" y="17" width="18" height="3" rx=".8"/><path d="M6.5 17a5.5 5.5 0 0 1 11 0"/><path d="M9 17a3 3 0 0 1 6 0"/>',
        'OTHER' => '<circle cx="6" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="18" cy="12" r="1.3"/>',
    ];

    /** The mould: flasks, joints and the way they are put together. */
    private const ARRANGEMENTS = [
        'COPE_DRAG' => '<rect x="3.5" y="3" width="17" height="7.5" rx="1"/><rect x="3.5" y="13.5" width="17" height="7.5" rx="1"/><path d="M2 12h20" stroke-dasharray="2.2 2"/><ellipse cx="12" cy="12" rx="3.2" ry="1.5"/>',
        'STACK' => '<rect x="4" y="2.5" width="16" height="3.6" rx=".8"/><rect x="4" y="7.6" width="16" height="3.6" rx=".8"/><rect x="4" y="12.7" width="16" height="3.6" rx=".8"/><rect x="4" y="17.8" width="16" height="3.6" rx=".8"/><path d="M12 1.5v21"/>',
        'VERTICAL' => '<rect x="3" y="4" width="8" height="16" rx="1"/><rect x="13" y="4" width="8" height="16" rx="1"/><path d="M12 2v20" stroke-dasharray="2.2 2"/><path d="M5.5 12h3M15.5 12h3"/>',
        'SHELL' => '<path d="M3.5 19a8.5 8.5 0 0 1 17 0z"/><path d="M7 19a5 5 0 0 1 10 0"/><path d="M2 21h20"/>',
        'CORE_PACKAGE' => '<rect x="6.5" y="2.5" width="11" height="5.4" rx="2.4"/><rect x="6.5" y="9.3" width="11" height="5.4" rx="2.4"/><rect x="6.5" y="16.1" width="11" height="5.4" rx="2.4"/>',
        'CLUSTER' => '<path d="M12 21.5V5M12 9.5 6.5 7M12 9.5 17.5 7M12 15 6.5 12.5M12 15l5.5-2.5"/><circle cx="5.5" cy="6.5" r="1.8"/><circle cx="18.5" cy="6.5" r="1.8"/><circle cx="5.5" cy="12" r="1.8"/><circle cx="18.5" cy="12" r="1.8"/><circle cx="12" cy="3.6" r="1.8"/>',
        'OTHER' => '<circle cx="6" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="18" cy="12" r="1.3"/>',
    ];

    public static function patternType(?string $type, int $size = 18): string
    {
        return self::svg(self::TYPES[$type] ?? self::TYPES['OTHER'], $size, 'Pattern type: '.Pattern::typeName($type, true));
    }

    public static function arrangement(?string $arrangement, int $size = 18): string
    {
        return self::svg(self::ARRANGEMENTS[$arrangement] ?? self::ARRANGEMENTS['OTHER'], $size, 'Mould arrangement: '.\Illuminate\Support\Str::before(PatternRevision::ARRANGEMENTS[$arrangement] ?? 'Other', ' ('));
    }

    /** Every icon by code, for a dropdown that swaps its preview as the choice changes. @return array{type: array<string, string>, arrangement: array<string, string>} */
    public static function all(int $size = 22): array
    {
        return [
            'type' => array_map(fn ($code) => self::patternType($code, $size), array_combine(array_keys(self::TYPES), array_keys(self::TYPES))),
            'arrangement' => array_map(fn ($code) => self::arrangement($code, $size), array_combine(array_keys(self::ARRANGEMENTS), array_keys(self::ARRANGEMENTS))),
        ];
    }

    private static function svg(string $paths, int $size, string $label): string
    {
        return '<svg class="mi" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="'.e($label).'"><title>'.e($label).'</title>'.$paths.'</svg>';
    }
}
