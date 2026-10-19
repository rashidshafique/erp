<?php

namespace App\Domains\Purchasing\Services;

use App\Domains\Purchasing\Models\IncomingInspection;
use App\Domains\Purchasing\Support\IncomingRules;
use App\Models\Attachment;
use App\Models\MaterialLot;

/** Decides whether a lot has what the incoming rules ask for before it can be accepted. */
class IncomingGate
{
    private const CERTIFICATE_ORDER = ['NONE', 'ATTACHED', 'EN10204_21', 'EN10204_22', 'EN10204_31', 'EN10204_32'];

    /** @return list<string> What is still missing. Empty when the lot may be accepted. */
    public function blockers(MaterialLot $lot): array
    {
        $missing = [];
        $supplier = $lot->supplier;
        if ($supplier?->isBlocked()) {
            $missing[] = "{$supplier->name} is ".strtolower(\App\Domains\Purchasing\Models\Supplier::STATUSES[$supplier->approval_status]).'.';
        }

        $required = IncomingRules::get('certificate');
        if ($required !== 'NONE') {
            $attached = $lot->exists && Attachment::where('attachable_type', 'material_lot')->where('attachable_id', (string) $lot->id)->current()->where('category', 'CERTIFICATE')->exists();
            if (! $attached) {
                $missing[] = 'Attach the supplier certificate to the lot.';
            }
            if ($required !== 'ATTACHED' && $this->rank($lot->certificate_type) < $this->rank($required)) {
                $missing[] = 'The lot must be marked as an '.str_replace('_', ' ', $required === 'EN10204_32' ? 'EN 10204 3.2' : 'EN 10204 3.1 or 3.2').' certificate.';
            }
        }

        if (IncomingRules::get('inspection') === 'REQUIRED') {
            $latest = $lot->exists ? IncomingInspection::where('material_lot_id', $lot->id)->orderByDesc('inspected_on')->orderByDesc('created_at')->first() : null;
            if (! $latest || $latest->result === 'FAIL') {
                $missing[] = $latest ? 'The latest incoming inspection failed. Inspect again, or accept by concession.' : 'Record an incoming inspection for the lot.';
            }
        }

        return $missing;
    }

    private function rank(?string $certificate): int
    {
        $rank = array_search($certificate ?: 'NONE', self::CERTIFICATE_ORDER, true);

        return $rank === false ? 0 : $rank;
    }
}
