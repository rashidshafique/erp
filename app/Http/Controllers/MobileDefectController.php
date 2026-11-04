<?php

namespace App\Http\Controllers;

use App\Models\CastingLot;
use App\Models\QualityDefect;
use App\Models\QualityDefectMark;
use App\Services\Quality\LotDefectLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** Phone defect log (designs 26e to 26g): photos, what is wrong and where, then what has been logged on the lot today. */
class MobileDefectController extends Controller
{
    public function create(CastingLot $lot): View
    {
        $lot->load('product');
        $seen = QualityDefectMark::where('product_revision_id', $lot->product_revision_id)->selectRaw('quality_defect_id, count(*) n')->groupBy('quality_defect_id')->pluck('n', 'quality_defect_id');
        $defects = QualityDefect::where('is_active', true)->orderBy('category')->orderBy('name')->get()->sortByDesc(fn ($d) => $seen[$d->id] ?? 0)->values();
        $places = QualityDefectMark::where('product_revision_id', $lot->product_revision_id)->whereNotNull('location')->selectRaw('location, count(*) n')->groupBy('location')->orderByDesc('n')->limit(8)->pluck('location');

        return view('m.defect', ['lot' => $lot, 'defects' => $defects, 'common' => $seen, 'places' => $places]);
    }

    public function store(Request $request, CastingLot $lot, LotDefectLog $log): RedirectResponse
    {
        $d = $request->validate([
            'quality_defect_id' => ['required', 'uuid', Rule::exists('quality_defects', 'id')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'disposition' => ['required', Rule::in(array_keys(QualityDefectMark::DISPOSITIONS))],
            'location' => ['nullable', 'string', 'max:150'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['file', 'image', 'max:'.config('foundry.attachments.max_kb')],
        ]);
        try {
            $mark = $log->log($lot, QualityDefect::findOrFail($d['quality_defect_id']), (int) $d['quantity'], $d['disposition'], $d['location'] ?? null, $request->user()?->name);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()])->withInput();
        }
        if ($request->hasFile('photos')) {
            AttachmentController::saveFiles($request->file('photos'), 'inspection', (string) $mark->quality_inspection_id, 'PHOTO', 'Defect photo');
        }

        return redirect()->route('m.defects.lot', $lot)->with('logged', $mark->id);
    }

    /** After logging, and from the lot list: the lot's count and everything logged on it today. */
    public function lot(CastingLot $lot): View
    {
        $lot->load('product');
        $today = QualityDefectMark::with(['defect', 'inspection'])->where('casting_lot_id', $lot->id)->whereDate('created_at', today())->latest()->get();
        $latest = $today->first();
        $repeats = $latest ? QualityDefectMark::where('quality_defect_id', $latest->quality_defect_id)->where('product_revision_id', $lot->product_revision_id)
            ->when($latest->location, fn ($q) => $q->where('location', $latest->location))->whereDate('created_at', today())->sum('quantity') : 0;

        return view('m.defect-lot', ['lot' => $lot->fresh('product'), 'today' => $today, 'latest' => session('logged') ? $latest : null, 'repeats' => (int) $repeats]);
    }
}
