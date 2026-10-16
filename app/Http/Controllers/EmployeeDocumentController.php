<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeDocumentController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $d = $request->validate([
            'kind' => ['required', Rule::in(array_keys(config('hr.document_kinds')))], 'title' => ['nullable', 'string', 'max:200'], 'number' => ['nullable', 'string', 'max:80'],
            'issued_on' => ['nullable', 'date'], 'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'file' => ['nullable', 'file', 'max:'.config('foundry.attachments.max_kb'), 'extensions:'.implode(',', config('foundry.attachments.extensions'))],
        ]);
        $doc = EmployeeDocument::create(collect($d)->except('file')->map(fn ($v) => $v === '' ? null : $v)->all() + ['employee_id' => $employee->id]);
        if ($request->hasFile('file')) {
            $saved = 0;
            $err = AttachmentController::saveFiles([$request->file('file')], 'employee_document', $doc->id, in_array($d['kind'], ['CNIC', 'PASSPORT'], true) ? 'ID_DOCUMENT' : 'CERTIFICATE', $d['title'] ?? null, $saved);
            if ($err) {
                return back()->with('error', 'Document recorded, but the file was not saved: '.$err);
            }
        }

        return back()->with('success', 'Document recorded.');
    }

    public function destroy(EmployeeDocument $document): RedirectResponse
    {
        \App\Models\Attachment::where('attachable_type', 'employee_document')->where('attachable_id', $document->id)->whereNull('removed_at')->update(['removed_at' => now(), 'removal_reason' => 'Document record deleted']);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }
}
