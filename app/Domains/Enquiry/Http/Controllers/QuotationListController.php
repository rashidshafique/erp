<?php

namespace App\Domains\Enquiry\Http\Controllers;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Enquiry\Services\QuotationFigures;
use App\Domains\Enquiry\Services\Rates;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Formats;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The quotations list (design 30a): one row per quotation showing its newest revision, with the price, what it is worth a year and
 * when it runs out. Tabs carry status; the panel shows the selected quotation with its revisions and what it is linked to.
 */
class QuotationListController extends Controller
{
    public const TABS = ['all' => 'All', 'draft' => 'Draft', 'approval' => 'Awaiting approval', 'sent' => 'Sent', 'accepted' => 'Accepted', 'lost' => 'Lost'];

    /** The columns the header can sort by, each with the SQL that orders it. */
    private const SORTS = [
        'quotation' => 'quotations.code',
        'customer' => '(select p.name from customers c join parties p on p.id = c.party_id where c.id = enquiries.customer_id)',
        'rev' => 'quotations.version',
        'piece' => '(select l.unit_price from quotation_lines l where l.quotation_id = quotations.id order by l.line_no limit 1)',
        'value' => '(select sum(coalesce(nullif(l.annual_qty, 0), nullif(i.annual_qty, 0), l.qty) * l.unit_price) from quotation_lines l join enquiry_items i on i.id = l.enquiry_item_id where l.quotation_id = quotations.id)',
        'valid' => 'quotations.valid_until',
        'status' => 'quotations.status',
    ];

    public function __construct(private QuotationFigures $figures) {}

    public function index(Request $request)
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'all';
        $rows = self::rows($request, $tab);
        $selected = $rows->firstWhere('id', $request->query('q')) ?? $rows->first();
        $since = today()->subDays(90);
        $quoted = $this->figures->quotedEnquiries($since);
        $won90 = Quotation::where('status', 'ACCEPTED')->where('decided_at', '>=', $since)->count();
        $waiting = Quotation::where('status', 'AWAITING_APPROVAL')->whereRaw(self::LATEST);
        $expiring = $this->tabQuery('sent')->whereBetween('quotations.valid_until', [today(), today()->addDays(7)])->with('lines.item')->get();

        return view('enquiry::quotations.index', [
            'rows' => $rows, 'tab' => $tab, 'selected' => $selected?->load('revisions', 'enquiry.items', 'enquiry.developments', 'enquiry.customer'),
            'counts' => collect(self::TABS)->map(fn ($l, $k) => $this->tabQuery($k)->count())->all(),
            'customers' => Customer::whereIn('id', Enquiry::select('customer_id'))->orderedByName()->get(),
            'floor' => (float) Rates::get()['min_margin_pct'],
            'quotable' => Enquiry::whereIn('status', ['NEW', 'FEASIBILITY', 'QUOTED'])->has('items')->whereDoesntHave('quotations', fn ($q) => $q->where('status', 'DRAFT'))->with('customer')->orderByDesc('created_at')->limit(12)->get(),
            'stats' => [
                'quoted' => $quoted->count(),
                'quotedValue' => Quotation::whereRaw(self::LATEST)->whereIn('enquiry_id', $quoted)->with('lines.item')->get()->sum(fn (Quotation $q) => $q->annualValue()),
                'won' => $won90, 'wonShare' => $quoted->count() > 0 ? round(100 * $won90 / $quoted->count()) : null,
                'daysToQuote' => $this->figures->daysToQuote($since),
                'waiting' => (clone $waiting)->count(), 'oldestWaiting' => (clone $waiting)->min('approval_asked_at'),
                'expiring' => $expiring->count(), 'expiringValue' => $expiring->sum(fn (Quotation $q) => $q->annualValue()),
            ],
            'openValue' => $this->tabQuery('all')->whereIn('quotations.status', ['DRAFT', 'AWAITING_APPROVAL', 'ISSUED'])->with('lines.item')->get()->sum(fn (Quotation $q) => $q->annualValue()),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'all';
        $rows = self::query($request, $tab)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Quotation', 'Revision', 'Part', 'Customer', 'Piece price', 'Value per year', 'Valid until', 'Status']);
            foreach ($rows as $q) {
                fputcsv($out, [$q->code, 'v'.$q->version, $q->lines->first()?->item?->description, $q->enquiry->customer?->name, $q->lines->first()?->unit_price, round($q->annualValue(), 2), $q->valid_until?->toDateString(), $q->statusLabel()]);
            }
            fclose($out);
        }, 'quotations.csv', ['Content-Type' => 'text/csv']);
    }

    /** Only the newest revision of each quotation. */
    private const LATEST = 'quotations.version = (select max(q2.version) from quotations q2 where q2.enquiry_id = quotations.enquiry_id and q2.code = quotations.code)';

    public static function rows(Request $request, string $tab): LengthAwarePaginator
    {
        return self::query($request, $tab)->paginate(25)->withQueryString();
    }

    private static function query(Request $request, string $tab): Builder
    {
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? $request->query('sort') : 'valid';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        return (new self(new QuotationFigures))->tabQuery($tab)
            ->with('lines.item', 'enquiry.customer', 'salesOrder:id,order_number')
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $s = '%'.trim($request->input('search')).'%';
                $q->where(fn ($w) => $w->where('quotations.code', 'ilike', $s)->orWhere('enquiries.customer_reference', 'ilike', $s)->orWhere('enquiries.title', 'ilike', $s)
                    ->orWhereHas('lines.item', fn ($i) => $i->where('description', 'ilike', $s))
                    ->orWhereHas('enquiry.customer', fn ($c) => $c->whereHas('party', fn ($p) => $p->where('name', 'ilike', $s))));
            })
            ->when($request->filled('customer'), fn (Builder $q) => $q->where('enquiries.customer_id', $request->input('customer')))
            ->when($request->query('valid') === '7', fn (Builder $q) => $q->where('quotations.status', 'ISSUED')->whereBetween('quotations.valid_until', [today(), today()->addDays(7)]))
            ->when($request->query('valid') === '30', fn (Builder $q) => $q->where('quotations.status', 'ISSUED')->whereBetween('quotations.valid_until', [today(), today()->addDays(30)]))
            ->when($request->query('valid') === 'past', fn (Builder $q) => $q->where('quotations.valid_until', '<', today()))
            ->orderByRaw(self::SORTS[$sort].' '.$dir.' nulls last')->orderByDesc('quotations.created_at');
    }

    /** The newest revisions the tab holds. A sent quotation counts as Sent until its date passes. */
    private function tabQuery(string $tab): Builder
    {
        return Quotation::query()->select('quotations.*')->join('enquiries', 'enquiries.id', '=', 'quotations.enquiry_id')->whereRaw(self::LATEST)
            ->when($tab === 'draft', fn (Builder $q) => $q->where('quotations.status', 'DRAFT'))
            ->when($tab === 'approval', fn (Builder $q) => $q->where('quotations.status', 'AWAITING_APPROVAL'))
            ->when($tab === 'sent', fn (Builder $q) => $q->where('quotations.status', 'ISSUED')->where(fn ($w) => $w->whereNull('quotations.valid_until')->orWhere('quotations.valid_until', '>=', today())))
            ->when($tab === 'accepted', fn (Builder $q) => $q->where('quotations.status', 'ACCEPTED'))
            ->when($tab === 'lost', fn (Builder $q) => $q->where('quotations.status', 'REJECTED'));
    }

    /** The money a list shows: whole units, or millions with a suffix from a million up. */
    public static function money(float $v): string
    {
        return $v >= 1000000 ? Formats::withCurrency(rtrim(rtrim(number_format($v / 1000000, 2), '0'), '.').'M') : Formats::money($v, 0);
    }
}
