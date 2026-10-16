<?php

namespace App\Http\Controllers;

use App\Models\CharacteristicLimit;
use App\Services\Quality\Measurements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Limits for tests, per iron grade or per sand system. */
class CharacteristicLimitController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $d = $this->data($request);
        $key = $d['characteristic_key'] ?: Str::snake(Str::ascii($d['name']));
        $d['characteristic_key'] = $key;
        if (CharacteristicLimit::where('subject_type', $d['subject_type'])->where('subject_id', $d['subject_id'])->where('characteristic_key', $key)->exists()) {
            return back()->withErrors(['limit' => 'That characteristic already has limits here. Edit the existing row.'])->withInput();
        }
        if ($blocked = $this->needsApproval('new:'.$d['subject_type'].':'.$d['subject_id'].':'.$key, $d['name'].' (new limit)')) {
            return back()->withErrors(['limit' => $blocked])->withInput();
        }
        CharacteristicLimit::create($d);

        return back()->with('success', 'Limit added.');
    }

    public function update(Request $request, CharacteristicLimit $limit): RedirectResponse
    {
        $d = $request->validate([
            'min_value' => ['nullable', 'numeric'], 'max_value' => ['nullable', 'numeric', 'gte:min_value'], 'unit' => ['nullable', 'string', 'max:20'],
            'is_required_for_release' => ['nullable', 'boolean'],
        ]);
        if ($blocked = $this->needsApproval((string) $limit->id, $limit->name.' (change)')) {
            return back()->withErrors(['limit' => $blocked]);
        }
        $limit->update($d + ['is_required_for_release' => $request->boolean('is_required_for_release')]);

        return back()->with('success', 'Limit updated.');
    }

    public function destroy(CharacteristicLimit $limit): RedirectResponse
    {
        if ($blocked = $this->needsApproval((string) $limit->id, $limit->name.' (remove)')) {
            return back()->withErrors(['limit' => $blocked]);
        }
        $limit->delete();

        return back()->with('success', 'Limit removed.');
    }

    /** Null when the change may go ahead, otherwise the message to show (an approval request has been raised). */
    private function needsApproval(string $subjectId, string $label): ?string
    {
        $ok = app(\App\Services\Approvals\ApprovalGate::class)->passes('limits.change', 'CharacteristicLimit', $subjectId, $label, $msg);

        return $ok ? null : $msg;
    }

    private function data(Request $request): array
    {
        $d = $request->validate([
            'subject_type' => ['required', Rule::in(['IRON_GRADE', 'SAND_SYSTEM'])], 'subject_id' => ['required', 'uuid'],
            'test_group' => ['required', Rule::in(array_keys(Measurements::KINDS))], 'characteristic_key' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]*$/'],
            'name' => ['required', 'string', 'max:120'], 'unit' => ['nullable', 'string', 'max:20'],
            'min_value' => ['nullable', 'numeric'], 'max_value' => ['nullable', 'numeric', 'gte:min_value'], 'is_required_for_release' => ['nullable', 'boolean'],
        ]);
        $d['is_required_for_release'] = $request->boolean('is_required_for_release');
        $d['characteristic_key'] = $d['characteristic_key'] ?? '';

        return $d;
    }
}
