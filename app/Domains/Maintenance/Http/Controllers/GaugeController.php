<?php

namespace App\Domains\Maintenance\Http\Controllers;

use App\Domains\Maintenance\Models\Gauge;
use App\Domains\Maintenance\Models\GaugeCalibration;
use App\Domains\Maintenance\Services\CalibrationStatus;
use App\Domains\Maintenance\Services\GaugeUsage;
use App\Domains\Maintenance\Support\CalibrationRules;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Controller;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GaugeController extends Controller
{
    public function index(Request $request): View
    {
        $soon = today()->addDays((int) CalibrationRules::get('due_soon_days'));
        $needsCal = fn ($q) => $q->where('status', 'IN_SERVICE')->where('requires_calibration', true);
        $state = $request->query('state');

        $gauges = Gauge::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('code', 'ilike', '%'.$request->query('q').'%')->orWhere('name', 'ilike', '%'.$request->query('q').'%')->orWhere('serial_number', 'ilike', '%'.$request->query('q').'%')))
            ->when($request->filled('type'), fn ($q) => $q->where('instrument_type', $request->query('type')))
            ->when($state === 'overdue', fn ($q) => $needsCal($q)->whereNotNull('next_due_on')->whereDate('next_due_on', '<', today()))
            ->when($state === 'soon', fn ($q) => $needsCal($q)->whereDate('next_due_on', '>=', today())->whereDate('next_due_on', '<=', $soon))
            ->when($state === 'never', fn ($q) => $needsCal($q)->whereNull('next_due_on'))
            ->when($state === 'out', fn ($q) => $q->where('status', 'OUT_OF_SERVICE'))
            ->when(! $request->filled('retired') && $state !== 'out', fn ($q) => $q->where('status', '!=', 'RETIRED'))
            ->orderByRaw("case when status = 'IN_SERVICE' and requires_calibration and (next_due_on is null or next_due_on < current_date) then 0 else 1 end")
            ->orderBy('next_due_on')->orderBy('code')
            ->paginate(25)->withQueryString();

        return view('maintenance::gauges.index', [
            'gauges' => $gauges, 'types' => Gauge::TYPES,
            'counts' => [
                'overdue' => $needsCal(Gauge::query())->whereNotNull('next_due_on')->whereDate('next_due_on', '<', today())->count(),
                'soon' => $needsCal(Gauge::query())->whereDate('next_due_on', '>=', today())->whereDate('next_due_on', '<=', $soon)->count(),
                'never' => $needsCal(Gauge::query())->whereNull('next_due_on')->count(),
                'out' => Gauge::where('status', 'OUT_OF_SERVICE')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('maintenance::gauges.form', ['gauge' => new Gauge(['status' => 'IN_SERVICE', 'requires_calibration' => true, 'calibration_interval_days' => 365]), 'types' => Gauge::TYPES, 'statuses' => Gauge::STATUSES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $gauge = Gauge::create($data);

        return redirect()->route('gauges.show', $gauge)->with('success', "{$gauge->code} added. Record its first calibration below.");
    }

    public function show(Gauge $gauge, GaugeUsage $usage): View
    {
        $gauge->load('calibrations');

        return view('maintenance::gauges.show', [
            'gauge' => $gauge, 'results' => GaugeCalibration::RESULTS, 'status' => app(CalibrationStatus::class)->of($gauge),
            'measured' => $usage->results($gauge), 'doubtfulSince' => $usage->doubtfulSince($gauge),
        ]);
    }

    public function edit(Gauge $gauge): View
    {
        return view('maintenance::gauges.form', ['gauge' => $gauge, 'types' => Gauge::TYPES, 'statuses' => Gauge::STATUSES]);
    }

    public function update(Request $request, Gauge $gauge): RedirectResponse
    {
        $gauge->update($this->validated($request, $gauge));

        return redirect()->route('gauges.show', $gauge)->with('success', "{$gauge->code} saved.");
    }

    /** Records a calibration: the result, the certificate, and the date the next one falls due. A failed calibration takes the instrument out of service. */
    public function calibrate(Request $request, Gauge $gauge, CalibrationStatus $status): RedirectResponse
    {
        $data = $request->validate([
            'calibrated_on' => ['required', 'date', 'before_or_equal:today'],
            'result' => ['required', Rule::in(array_keys(GaugeCalibration::RESULTS))],
            'due_on' => ['nullable', 'date', 'after:calibrated_on'],
            'performed_by' => ['nullable', 'string', 'max:150'],
            'external_lab' => ['nullable', 'boolean'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'as_found' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'certificate' => ['nullable', 'file', 'max:'.config('foundry.attachments.max_kb'), 'extensions:'.implode(',', config('foundry.attachments.extensions'))],
        ], [
            'certificate.max' => 'The certificate may be at most '.Uploads::megabytes((int) config('foundry.attachments.max_kb')).'.',
            'certificate.uploaded' => Uploads::failedMessage(),
            'due_on.after' => 'The next due date must be after the calibration date.',
        ]);
        if ($data['result'] !== 'FAIL' && blank($data['due_on'] ?? null) && ! $gauge->calibration_interval_days && $gauge->requires_calibration) {
            throw ValidationException::withMessages(['due_on' => 'This instrument has no calibration interval, so say when the next calibration is due.']);
        }
        $file = $request->file('certificate');
        unset($data['certificate']);

        DB::transaction(function () use ($gauge, $data, $file, $status) {
            $status->record($gauge, $data + ['recorded_by' => Auth::user()?->name, 'external_lab' => (bool) ($data['external_lab'] ?? false)]);
            if ($file) {
                $saved = 0;
                $error = AttachmentController::saveFiles([$file], 'gauge', (string) $gauge->id, 'CERTIFICATE', 'Calibration certificate '.($data['certificate_number'] ?? $data['calibrated_on']), $saved);
                if ($error) {
                    throw ValidationException::withMessages(['certificate' => $error]);
                }
            }
        });
        $gauge->refresh();

        return redirect()->route('gauges.show', $gauge)->with($data['result'] === 'FAIL' ? 'warning' : 'success', $data['result'] === 'FAIL'
            ? "{$gauge->code} failed calibration and is now out of service. Check the results measured with it since the last good calibration."
            : 'Calibration recorded. Next due '.($gauge->next_due_on?->displayDate() ?? 'not set').'.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Gauge $gauge = null): array
    {
        // Codes are kept in capitals, so "cal-1" and "CAL-1" count as the same code when checking for duplicates.
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('gauges', 'code')->ignore($gauge?->id)],
            'name' => ['required', 'string', 'max:150'],
            'instrument_type' => ['required', Rule::in(array_keys(Gauge::TYPES))],
            'manufacturer' => ['nullable', 'string', 'max:100'], 'model' => ['nullable', 'string', 'max:100'], 'serial_number' => ['nullable', 'string', 'max:100'],
            'range_description' => ['nullable', 'string', 'max:150'], 'location' => ['nullable', 'string', 'max:150'], 'responsible_person' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(array_keys(Gauge::STATUSES))],
            'requires_calibration' => ['nullable', 'boolean'],
            'calibration_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['requires_calibration'] = (bool) ($data['requires_calibration'] ?? false);
        if ($data['requires_calibration'] && blank($data['calibration_interval_days'] ?? null)) {
            throw ValidationException::withMessages(['calibration_interval_days' => 'Give the calibration interval in days, or untick "needs calibration".']);
        }
        if (blank($data['code'] ?? null)) {
            unset($data['code']);
        } else {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return $data;
    }
}
