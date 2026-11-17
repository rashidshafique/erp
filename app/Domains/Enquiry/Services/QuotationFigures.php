<?php

namespace App\Domains\Enquiry\Services;

use App\Domains\Enquiry\Models\Quotation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** The figures the quotations list (design 30a) and the enquiries list (14a) both show for a window of days. */
class QuotationFigures
{
    /** Quotations first sent since the date, one per enquiry. @return \Illuminate\Support\Collection<int, string> enquiry ids */
    public function quotedEnquiries(Carbon $since): \Illuminate\Support\Collection
    {
        return Quotation::whereNotNull('issued_at')->where('issued_at', '>=', $since)->distinct()->pluck('enquiry_id');
    }

    /** Average days from an enquiry being received to its first quotation going out, for those first sent since the date. */
    public function daysToQuote(Carbon $since): ?float
    {
        $firstIssue = Quotation::whereNotNull('issued_at')->selectRaw('enquiry_id, min(issued_at) as first_issued')->groupBy('enquiry_id');
        $days = DB::query()->fromSub($firstIssue, 'q')->join('enquiries', 'enquiries.id', '=', 'q.enquiry_id')
            ->where('q.first_issued', '>=', $since)->selectRaw('avg(extract(epoch from (q.first_issued - enquiries.received_on::timestamptz)) / 86400) as days')->value('days');

        return $days !== null ? round((float) $days, 1) : null;
    }
}
