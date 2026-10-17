<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\Pattern;
use App\Models\ProductionOrder;
use App\Models\QualityDefect;
use App\Services\Production\BulkRunEntry;
use App\Services\Production\BulkRunPaste;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** The bulk moulding-run grid: many runs entered at once, or a shift's runs opened again to correct them. */
class MouldingRunBulkController extends Controller
{
    /** The grid. With ?date= (and ?shift=) it opens that shift's saved runs for correction. */
    public function edit(Request $request): View
    {
        $opened = $request->filled('date');
        $runs = $opened
            ? MouldingRun::with('patternRevision.pattern', 'station', 'team', 'defect', 'productionOrder')->withCount('mouldAssemblies')
                ->whereDate('run_date', $request->date('date'))
                ->when($request->filled('shift'), fn ($q) => $q->where('shift', $request->input('shift')))
                ->orderBy('created_at')->orderBy('run_number')->get()
            : collect();

        $rows = [];
        foreach ($runs as $run) {
            $rows[$run->id] = [
                'id' => $run->id, 'run_date' => $run->run_date->toDateString(), 'shift' => $run->shift, 'moulding_station_id' => $run->moulding_station_id,
                'pattern_id' => $run->patternRevision?->pattern_id, 'pattern_revision_id' => $run->pattern_revision_id,
                'cavity_numbers' => $run->cavity_numbers ? implode(', ', $run->cavity_numbers) : null, 'production_order_id' => $run->production_order_id,
                'moulds_made_qty' => $run->moulds_made_qty, 'damaged_mould_qty' => $run->damaged_mould_qty,
                'quality_defect_id' => $run->quality_defect_id, 'notes' => $run->notes,
            ];
        }

        $patterns = Pattern::where('status', 'ACTIVE')->orWhereIn('id', collect($rows)->pluck('pattern_id')->filter())->orderBy('name')->orderBy('code')
            ->with(['revisions' => fn ($q) => $q->orderByDesc('is_current')->orderByDesc('sequence'), 'revisions.cavityMap'])->get();

        $stations = MouldingStation::with('defaultTeam')->where('status', '!=', 'INACTIVE')->orderBy('code')->get();

        return view('moulding-runs.bulk', [
            'opened' => $opened,
            'runs' => $runs->keyBy('id'),
            'rows' => $rows,
            'header' => [
                'run_date' => $request->input('date', today()->toDateString()), 'shift' => $request->input('shift', 'General'),
                'moulding_station_id' => null,
            ],
            'patterns' => $patterns,
            'stations' => $stations,
            'stationCrew' => $stations->mapWithKeys(fn (MouldingStation $st) => [$st->id => trim(($st->defaultTeam?->code ?? 'no team').' · '.($st->defaultSand() ?? 'no sand'))]),
            'patternPhotos' => $this->coverPhotos($patterns->pluck('id')->all()),
            'orders' => ProductionOrder::open()->with('product')->orderBy('production_order_number')->get(),
            'defects' => QualityDefect::where('is_active', true)->orderBy('code')->get(),
            'shifts' => WorkListController::SHIFTS,
        ]);
    }

    /** Saves every row or none. "Load pasted rows" instead reads spreadsheet text into the grid without saving. */
    public function save(Request $request, BulkRunEntry $entry, BulkRunPaste $paste): RedirectResponse
    {
        $rows = (array) $request->input('rows', []);
        $header = (array) $request->input('header', []);
        $back = array_filter(['date' => $request->input('opened_date'), 'shift' => $request->input('opened_shift')]);

        if ($request->input('action') === 'paste') {
            $parsed = $paste->parse((string) $request->input('paste', ''));
            $kept = array_filter($rows, fn ($r) => filled($r['id'] ?? null) || filled($r['pattern_id'] ?? null) || filled($r['moulds_made_qty'] ?? null));
            $count = count($parsed['rows']);

            return redirect()->route('moulding-runs.bulk', $back)
                ->withInput(['header' => $header, 'rows' => $kept + $parsed['rows'], 'opened_date' => $request->input('opened_date'), 'opened_shift' => $request->input('opened_shift')])
                ->withErrors($parsed['errors'])
                ->with('success', $count ? "{$count} pasted ".($count === 1 ? 'row' : 'rows').' added to the grid. Check them, then save.' : null);
        }

        try {
            $result = $entry->save($rows, $header);
        } catch (ValidationException $e) {
            return redirect()->route('moulding-runs.bulk', $back)->withErrors($e->errors())->withInput();
        }

        $first = collect($rows)->first(fn ($r) => filled($r['run_date'] ?? null));
        $date = $header['run_date'] ?? $first['run_date'] ?? today()->toDateString();
        $shift = $header['shift'] ?? $first['shift'] ?? null;
        $saved = [];
        if ($result['created']) {
            $saved[] = $result['created'].' new '.($result['created'] === 1 ? 'run' : 'runs');
        }
        if ($result['updated']) {
            $saved[] = $result['updated'].' '.($result['updated'] === 1 ? 'run' : 'runs').' corrected';
        }
        $response = redirect()->route('moulding-runs.bulk', array_filter(['date' => $date, 'shift' => $shift]))
            ->with('success', $saved ? 'Saved: '.implode(', ', $saved).'.' : 'Nothing changed.');

        return $result['warnings'] ? $response->with('warning', implode(' ', $result['warnings'])) : $response;
    }

    /**
     * A photo per pattern to recognise it by in the picker: its cover, else its newest photo, else its newest image.
     *
     * @param  array<int, string>  $patternIds
     * @return array<string, string> pattern id => image URL
     */
    private function coverPhotos(array $patternIds): array
    {
        return Attachment::where('attachable_type', 'pattern')->whereIn('attachable_id', $patternIds)->whereNull('removed_at')
            ->whereIn('mime_type', ['image/png', 'image/jpeg', 'image/webp'])
            ->orderByDesc('is_cover')->orderByRaw("category = 'PHOTO' desc")->orderByDesc('created_at')
            ->get(['id', 'attachable_id'])->unique('attachable_id')
            ->mapWithKeys(fn (Attachment $a) => [$a->attachable_id => route('attachments.download', $a)])->all();
    }
}
