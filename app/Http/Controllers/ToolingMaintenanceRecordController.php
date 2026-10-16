<?php

namespace App\Http\Controllers;

use App\Models\ToolingMaintenanceRecord;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\CoreDefinition;
use App\Models\CoreBox;
use App\Models\MouldConfiguration;
use App\Models\MouldAssembly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ToolingMaintenanceRecordController extends Controller
{
    private const TOOLING = [
        'PATTERN' => ['pattern_id', Pattern::class],
        'PATTERN_REVISION' => ['pattern_revision_id', PatternRevision::class],
        'CORE_DEFINITION' => ['core_definition_id', CoreDefinition::class],
        'CORE_BOX' => ['core_box_id', CoreBox::class],
        'MOULD_CONFIGURATION' => ['mould_configuration_id', MouldConfiguration::class],
        'MOULD_ASSEMBLY' => ['mould_assembly_id', MouldAssembly::class],
    ];

    private const ACTIVITIES = [
        'INSPECTION', 'REPAIR', 'PREVENTIVE', 'MODIFICATION', 'BREAKDOWN',
    ];

    private const STATUSES = [
        'OPEN', 'IN_PROGRESS', 'ON_HOLD', 'COMPLETED', 'CANCELLED',
    ];

    private const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];

    private function toolingOptions(): array
    {
        $options = [];

        foreach (self::TOOLING as $type => [$foreignKey, $model]) {
            $options[$type] = $model::query()->get()->map(function ($item) {
                $attributes = $item->getAttributes();

                $label = $attributes['name']
                    ?? $attributes['code']
                    ?? $attributes['assembly_number']
                    ?? $attributes['revision']
                    ?? $item->getKey();

                $code = $attributes['code'] ?? null;

                if ($code && $label !== $code) {
                    $label = "{$label} ({$code})";
                }

                return [
                    'id' => $item->getKey(),
                    'label' => (string) $label,
                ];
            })->sortBy('label')->values();
        }

        return $options;
    }

    private function withTooling(ToolingMaintenanceRecord $record): ToolingMaintenanceRecord
    {
        return $record->load([
            'pattern', 'patternRevision', 'coreDefinition',
            'coreBox', 'mouldConfiguration', 'mouldAssembly',
        ]);
    }

    private function recordRules(?string $recordId = null): array
    {
        $rules = [
            'tooling_type' => ['required', Rule::in(array_keys(self::TOOLING))],
            'activity_type' => ['required', Rule::in(self::ACTIVITIES)],
            'status' => ['required', Rule::in(self::STATUSES)],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'reported_at' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'reported_by' => ['nullable', 'string', 'max:255'],
            'performed_by' => ['nullable', 'string', 'max:255'],
            'problem_description' => ['nullable', 'string', 'max:10000'],
            'inspection_findings' => ['nullable', 'string', 'max:10000'],
            'work_performed' => ['nullable', 'string', 'max:10000'],
            'parts_used' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'labour_hours' => ['nullable', 'numeric', 'min:0'],
            'parts_cost' => ['nullable', 'numeric', 'min:0'],
            'other_cost' => ['nullable', 'numeric', 'min:0'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0'],
            'next_inspection_due_on' => ['nullable', 'date'],
        ];

        foreach (self::TOOLING as $foreignKey => [$column, $model]) {
            $rules[$column] = [
                'nullable',
                'uuid',
                Rule::exists((new $model())->getTable(), 'id'),
            ];
        }

        return $rules;
    }

    private function validateRecord(Request $request, ?string $recordId = null): array
    {
        $data = $request->validate($this->recordRules($recordId));

        $selected = collect(self::TOOLING)
            ->pluck(0)
            ->filter(fn ($column) => !empty($data[$column] ?? null));

        if ($selected->count() !== 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tooling_type' => 'Select exactly one tooling item.',
            ]);
        }

        $selectedColumn = $selected->first();
        $selectedType = collect(self::TOOLING)
            ->first(fn ($config) => $config[0] === $selectedColumn);

        if ($data['tooling_type'] !== array_search($selectedType, self::TOOLING, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tooling_type' => 'The tooling type must match the selected tooling item.',
            ]);
        }

        foreach (self::TOOLING as [$column]) {
            if ($column !== $selectedColumn) {
                $data[$column] = null;
            }
        }

        return $data;
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'priority' => ['nullable', Rule::in(self::PRIORITIES)],
            'activity_type' => ['nullable', Rule::in(self::ACTIVITIES)],
            'tooling_type' => ['nullable', Rule::in(array_keys(self::TOOLING))],
        ]);

        $query = ToolingMaintenanceRecord::query()
            ->with([
                'pattern', 'patternRevision', 'coreDefinition',
                'coreBox', 'mouldConfiguration', 'mouldAssembly',
            ])
            ->latest('created_at');

        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        return view('tooling-maintenance.index', [
            'records' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('tooling-maintenance.form', [
            'record' => new ToolingMaintenanceRecord(),
            'toolingOptions' => $this->toolingOptions(),
            'activities' => self::ACTIVITIES,
            'statuses' => self::STATUSES,
            'priorities' => self::PRIORITIES,
            'editing' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRecord($request);

        $record = DB::transaction(function () use ($data) {
            do {
                $number = 'TM-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
            } while (ToolingMaintenanceRecord::where('record_number', $number)->exists());

            $data['record_number'] = $number;
            $data['reported_at'] = $data['reported_at'] ?? now();

            return ToolingMaintenanceRecord::create($data);
        });

        return redirect()
            ->route('tooling-maintenance.show', $record)
            ->with('success', "Maintenance record {$record->record_number} created.");
    }

    public function show(ToolingMaintenanceRecord $toolingMaintenance): View
    {
        return view('tooling-maintenance.show', [
            'record' => $this->withTooling($toolingMaintenance)->load('attachments'),
        ]);
    }

    public function edit(ToolingMaintenanceRecord $toolingMaintenance): View
    {
        return view('tooling-maintenance.form', [
            'record' => $this->withTooling($toolingMaintenance),
            'toolingOptions' => $this->toolingOptions(),
            'activities' => self::ACTIVITIES,
            'statuses' => self::STATUSES,
            'priorities' => self::PRIORITIES,
            'editing' => true,
        ]);
    }

    public function update(Request $request, ToolingMaintenanceRecord $toolingMaintenance): RedirectResponse
    {
        $data = $this->validateRecord($request);

        if ($data['status'] === 'COMPLETED' && empty($data['completed_at'])) {
            $data['completed_at'] = now();
        }

        $toolingMaintenance->update($data);

        return redirect()
            ->route('tooling-maintenance.show', $toolingMaintenance)
            ->with('success', "Maintenance record {$toolingMaintenance->record_number} updated.");
    }
}