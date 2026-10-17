<?php

namespace App\Http\Controllers;

use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\Flask;
use App\Models\MouldAssembly;
use App\Models\MouldConfiguration;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\ToolingLifeLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ToolingLifeLimitController extends Controller
{
    private const TOOLING = [
        'PATTERN' => ['pattern_id', Pattern::class],
        'PATTERN_REVISION' => ['pattern_revision_id', PatternRevision::class],
        'CORE_DEFINITION' => ['core_definition_id', CoreDefinition::class],
        'CORE_BOX' => ['core_box_id', CoreBox::class],
        'MOULD_CONFIGURATION' => ['mould_configuration_id', MouldConfiguration::class],
        'MOULD_ASSEMBLY' => ['mould_assembly_id', MouldAssembly::class],
        'FLASK' => ['flask_id', Flask::class],
    ];

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

    public function create(): View
    {
        return view('tooling-life.form', [
            'limit' => new ToolingLifeLimit,
            'toolingOptions' => $this->toolingOptions(),
            'editing' => false,
        ]);
    }

    public function edit(ToolingLifeLimit $toolingLifeLimit): View
    {
        return view('tooling-life.form', [
            'limit' => $toolingLifeLimit,
            'toolingOptions' => $this->toolingOptions(),
            'editing' => true,
        ]);
    }

    private function validateLimit(Request $request): array
    {
        $data = $request->validate([
            'tooling_type' => [
                'required',
                Rule::in(array_keys(self::TOOLING)),
            ],
            'usage_unit' => ['required', 'string', 'max:30'],
            'service_life_quantity' => ['nullable', 'numeric', 'gt:0'],
            'inspection_interval_quantity' => ['nullable', 'numeric', 'gt:0'],
            'warning_threshold_percent' => ['required', 'integer', 'between:1,100'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'last_inspected_at' => ['nullable', 'date'],
            'usage_at_last_inspection' => ['nullable', 'numeric', 'min:0'],
            'tooling_id' => ['required', 'uuid'],
        ]);

        $type = $data['tooling_type'];
        [$column, $model] = self::TOOLING[$type];

        validator(
            ['tooling_id' => $data['tooling_id']],
            ['tooling_id' => ['required', 'uuid', Rule::exists((new $model)->getTable(), 'id')]]
        )->validate();

        $existing = ToolingLifeLimit::query()
            ->where($column, $data['tooling_id'])
            ->when(
                $request->route('toolingLifeLimit'),
                fn ($query, $routeLimit) => $query->where('id', '!=', $routeLimit->id)
            )
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'tooling_id' => 'A life-limit configuration already exists for this tooling item. Edit the existing configuration instead.',
            ]);
        }

        foreach (self::TOOLING as [$foreignKey]) {
            $data[$foreignKey] = $foreignKey === $column
                ? $data['tooling_id']
                : null;
        }

        unset($data['tooling_id']);

        $data['usage_at_last_inspection'] ??= 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateLimit($request);

        $limit = DB::transaction(
            fn () => ToolingLifeLimit::create($data)
        );

        return redirect()
            ->route('tooling-life.dashboard')
            ->with('success', 'Tooling life limit created successfully.');
    }

    public function update(
        Request $request,
        ToolingLifeLimit $toolingLifeLimit
    ): RedirectResponse {
        $data = $this->validateLimit($request);

        // Keep tooling identity immutable after creation.
        $identityColumns = array_column(self::TOOLING, 0);

        foreach ($identityColumns as $column) {
            unset($data[$column]);
        }

        unset($data['tooling_type']);

        $toolingLifeLimit->update($data);

        return redirect()
            ->route('tooling-life.dashboard')
            ->with('success', 'Tooling life limit updated successfully.');
    }
}
