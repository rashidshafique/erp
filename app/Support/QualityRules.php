<?php

namespace App\Support;

/** How lots are made and how quality records are raised, set through the process depth. */
class QualityRules
{
    use RuleSet;

    /** key => [setting, label, options (value => description), default] */
    public const RULES = [
        'ncr_threshold' => ['quality.ncr_threshold', 'A quick QC tally raises a nonconformance', [
            'ANY' => 'Any reject or rework',
            'OVER_5' => 'Over 5% rejected or reworked',
            'OVER_10' => 'Over 10% rejected or reworked',
            'NEVER' => 'Never: the defect tally is the record',
        ], 'ANY'],
        'lot_grouping' => ['quality.lot_grouping', 'Casting lots made when a pour is completed', [
            'MOULD' => 'Per mould and product: full traceability',
            'POUR' => 'Per pour and product: moulds handled together',
        ], 'MOULD'],
        'qc_after_steps' => ['quality.qc_after_steps', 'When castings reach quality control', [
            'STEPS' => 'After the pre-inspection steps (knock-out, fettling, blast)',
            'POUR' => 'Straight after the pour',
        ], 'POUR'],
        'defect_piece_ids' => ['quality.defect_piece_ids', 'Piece IDs on defects found at inspection', [
            'OFF' => 'Not asked',
            'OPTIONAL' => 'Optional',
            'REQUIRED' => 'Required: one ID per piece',
        ], 'OFF'],
    ];

    public static function groupsByPour(): bool
    {
        return self::get('lot_grouping') === 'POUR';
    }

    /** Whether a tally with this many bad pieces out of the checked total should raise a nonconformance. */
    public static function raisesNcr(int $bad, int $checked): bool
    {
        if ($bad <= 0 || $checked <= 0) {
            return false;
        }

        return match (self::get('ncr_threshold')) {
            'OVER_5' => $bad / $checked > 0.05,
            'OVER_10' => $bad / $checked > 0.10,
            'NEVER' => false,
            default => true,
        };
    }

    public static function qcWaitsForSteps(): bool
    {
        return self::get('qc_after_steps') === 'STEPS';
    }
}
