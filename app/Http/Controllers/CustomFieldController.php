<?php

namespace App\Http\Controllers;

use App\Models\CustomFieldDefinition;
use App\Services\CustomFields\CustomFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomFieldController extends Controller
{
    public function index(CustomFields $cf): View
    {
        return view('settings.custom-fields', ['entities' => CustomFields::ENTITIES, 'types' => CustomFields::TYPES,
            'defs' => CustomFieldDefinition::orderBy('entity_type')->orderBy('sort_order')->orderBy('label')->get()->groupBy('entity_type')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->validated($request);
        $d['field_key'] = Str::snake(Str::ascii($d['label']));
        if (CustomFieldDefinition::where('entity_type', $d['entity_type'])->where('field_key', $d['field_key'])->exists()) {
            return back()->withErrors(['label' => 'A field with that name already exists for this object.'])->withInput();
        }
        CustomFieldDefinition::create($d);

        return back()->with('success', 'Field added.');
    }

    public function update(Request $request, CustomFieldDefinition $field): RedirectResponse
    {
        $d = $this->validated($request, false);
        // The key and type are fixed once created, so stored values keep their meaning.
        $field->update(collect($d)->except(['entity_type', 'data_type'])->all());

        return back()->with('success', 'Field updated.');
    }

    public function saveValues(Request $request, CustomFields $cf, string $entity, string $id): RedirectResponse
    {
        abort_unless(isset(CustomFields::ENTITIES[$entity]), 404);
        abort_unless(\Illuminate\Support\Facades\DB::table($cf->table($entity))->where('id', $id)->exists(), 404);
        $cf->save($entity, $id, (array) $request->input('cf', []));

        return back()->with('success', 'Additional information saved.');
    }

    private function validated(Request $request, bool $creating = true): array
    {
        $d = $request->validate([
            'entity_type' => [$creating ? 'required' : 'sometimes', Rule::in(array_keys(CustomFields::ENTITIES))],
            'label' => ['required', 'string', 'max:120'], 'data_type' => [$creating ? 'required' : 'sometimes', Rule::in(array_keys(CustomFields::TYPES))],
            'options_text' => ['nullable', 'string', 'max:2000'], 'unit' => ['nullable', 'string', 'max:20'],
            'min_value' => ['nullable', 'numeric'], 'max_value' => ['nullable', 'numeric'], 'is_required' => ['nullable', 'boolean'],
            'help_text' => ['nullable', 'string', 'max:255'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $opts = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) ($d['options_text'] ?? '')))));
        if (($d['data_type'] ?? $request->input('data_type')) === 'SELECT' && $creating && $opts === []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['options_text' => 'A choice list needs at least one choice.']);
        }
        unset($d['options_text']);
        $d['options'] = $opts ?: null;
        $d['is_required'] = (bool) ($d['is_required'] ?? false);
        if (array_key_exists('is_active', $d) || ! $creating) {
            $d['is_active'] = (bool) ($d['is_active'] ?? false);
        }
        $d['sort_order'] = $d['sort_order'] ?? 100;

        return $d;
    }
}
